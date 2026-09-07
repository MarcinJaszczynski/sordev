<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Services\PilotContractorAssignmentService;
use App\Services\PilotOnboardingService;
use App\Support\UserRoleManagement;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected mixed $pendingPilotBirthDate = null;

    protected ?string $pendingPilotPesel = null;

    protected mixed $pendingPilotPhone = null;

    protected function getHeaderActions(): array
    {
        return [
            $this->makeSendPilotCredentialsAction(),
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $state = app(PilotContractorAssignmentService::class)
            ->demographicsFormStateForPortalUser($this->record);

        $data['birth_date'] = $state['birth_date'];
        $data['pesel'] = $state['pesel'];

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = UserRoleManagement::applyPilotDefaults($data);

        $this->pendingPilotBirthDate = $data['birth_date'] ?? null;
        $this->pendingPilotPesel = isset($data['pesel']) ? (filled($data['pesel']) ? (string) $data['pesel'] : null) : null;
        $this->pendingPilotPhone = $data['phone'] ?? null;
        unset($data['birth_date'], $data['pesel']);

        if (filled($data['password'] ?? null)) {
            $data['password'] = bcrypt($data['password']);
        } else {
            unset($data['password']);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        UserRoleManagement::ensurePilotRole($this->record->fresh());

        app(PilotContractorAssignmentService::class)->persistPilotDemographicsFromPortalUser(
            $this->record->fresh(),
            $this->pendingPilotBirthDate,
            $this->pendingPilotPesel,
            filled($this->pendingPilotPhone) ? (string) $this->pendingPilotPhone : null,
        );

        $this->record->refresh();
    }

    protected function makeSendPilotCredentialsAction(): Actions\Action
    {
        return Actions\Action::make('sendPilotCredentials')
            ->label(fn (): string => $this->record->pilot_panel_access_sent_at
                ? 'Wyślij ponownie dane logowania'
                : 'Wyślij dane logowania')
            ->icon('heroicon-o-envelope')
            ->color('success')
            ->visible(fn (): bool => $this->record->hasRole('pilot')
                && $this->record->status === 'active'
                && filled($this->record->email))
            ->requiresConfirmation()
            ->modalHeading('Wyślij e-mail z danymi logowania do panelu pilota')
            ->modalDescription('Pilot otrzyma adres panelu i hasło podane poniżej. Hasło zostanie zapisane na koncie.')
            ->form([
                Forms\Components\TextInput::make('password')
                    ->label('Hasło do wysłania')
                    ->password()
                    ->required()
                    ->minLength(8)
                    ->maxLength(255),
            ])
            ->action(function (array $data): void {
                $plainPassword = (string) $data['password'];

                $this->record->update([
                    'password' => bcrypt($plainPassword),
                ]);

                $sent = app(PilotOnboardingService::class)->sendPanelAccessCredentials(
                    $this->record->fresh(),
                    $plainPassword,
                    auth()->id(),
                );

                if (! $sent) {
                    Notification::make()
                        ->title('Nie udało się wysłać e-maila')
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Wysłano dane logowania')
                    ->body('E-mail z danymi dostępu do panelu pilota został wysłany.')
                    ->success()
                    ->send();

                $this->record->refresh();
            });
    }
}
