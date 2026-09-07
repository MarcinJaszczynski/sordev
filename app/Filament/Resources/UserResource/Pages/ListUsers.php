<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use App\Services\PilotContractorAssignmentService;
use App\Support\UserRoleManagement;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected mixed $pendingCreateBirthDate = null;

    protected ?string $pendingCreatePesel = null;

    protected mixed $pendingCreatePhone = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Dodaj pilota')
                ->fillForm(fn (): array => [
                    'status' => UserRoleManagement::DEFAULT_STATUS,
                ])
                ->mutateFormDataUsing(function (array $data): array {
                    $data = UserRoleManagement::applyPilotDefaults($data);

                    $this->pendingCreateBirthDate = $data['birth_date'] ?? null;
                    $this->pendingCreatePesel = isset($data['pesel'])
                        ? (filled($data['pesel']) ? (string) $data['pesel'] : null)
                        : null;
                    $this->pendingCreatePhone = $data['phone'] ?? null;
                    unset($data['birth_date'], $data['pesel']);

                    if (filled($data['password'] ?? null)) {
                        $data['password'] = bcrypt($data['password']);
                    }

                    return $data;
                })
                ->after(function (User $record): void {
                    UserRoleManagement::ensurePilotRole($record->fresh());

                    app(PilotContractorAssignmentService::class)->persistPilotDemographicsFromPortalUser(
                        $record->fresh(),
                        $this->pendingCreateBirthDate,
                        $this->pendingCreatePesel,
                        filled($this->pendingCreatePhone) ? (string) $this->pendingCreatePhone : null,
                    );
                })
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('Pilot zapisany')
                        ->body('Konto pilota zostało utworzone. Użyj akcji «Wyślij dane logowania», aby wysłać e-mail z dostępem do panelu.'),
                ),
        ];
    }
}
