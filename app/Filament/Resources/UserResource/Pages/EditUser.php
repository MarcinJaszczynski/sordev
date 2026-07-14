<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Services\PilotOnboardingService;
use App\Support\UserRoleManagement;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->makeSendPilotCredentialsAction(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = UserRoleManagement::applyPilotDefaults($data);

        if (filled($data['password'] ?? null)) {
            $data['password'] = bcrypt($data['password']);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        if (! UserRoleManagement::canManageRolesAndPermissions(auth()->user())) {
            UserRoleManagement::ensurePilotRole($this->record->fresh());
        }
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
