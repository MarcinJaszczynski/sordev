<?php

namespace App\Filament\Resources\StaffUserResource\Pages;

use App\Filament\Resources\StaffUserResource;
use App\Support\UserRoleManagement;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListStaffUsers extends ListRecords
{
    protected static string $resource = StaffUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Dodaj użytkownika')
                ->fillForm(fn (): array => [
                    'status' => UserRoleManagement::DEFAULT_STATUS,
                ])
                ->mutateFormDataUsing(function (array $data): array {
                    $data = UserRoleManagement::applyStaffDefaults($data);

                    if (filled($data['password'] ?? null)) {
                        $data['password'] = bcrypt($data['password']);
                    }

                    return $data;
                })
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('Użytkownik zapisany'),
                ),
        ];
    }
}
