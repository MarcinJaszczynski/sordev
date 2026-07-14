<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use App\Support\UserRoleManagement;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->fillForm(fn (): array => [
                    'status' => UserRoleManagement::DEFAULT_STATUS,
                    ...(UserRoleManagement::canManageRolesAndPermissions(auth()->user())
                        ? ['roles' => UserRoleManagement::defaultPilotRoleIds()]
                        : []),
                ])
                ->mutateFormDataUsing(function (array $data): array {
                    $data = UserRoleManagement::applyPilotDefaults($data);

                    if (filled($data['password'] ?? null)) {
                        $data['password'] = bcrypt($data['password']);
                    }

                    return $data;
                })
                ->after(function (User $record): void {
                    if (! UserRoleManagement::canManageRolesAndPermissions(auth()->user())) {
                        UserRoleManagement::ensurePilotRole($record->fresh());
                    }
                })
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('Użytkownik zapisany')
                        ->body('Konto pilota zostało utworzone. Użyj akcji «Wyślij dane logowania», aby wysłać e-mail z dostępem do panelu.'),
                ),
        ];
    }
}
