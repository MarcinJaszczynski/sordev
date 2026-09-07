<?php

namespace App\Filament\Resources\ClientUserResource\Pages;

use App\Filament\Resources\ClientUserResource;
use App\Models\User;
use App\Support\UserRoleManagement;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListClientUsers extends ListRecords
{
    protected static string $resource = ClientUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Dodaj konto portalu')
                ->fillForm(fn (): array => [
                    'status' => UserRoleManagement::DEFAULT_STATUS,
                    'roles' => UserRoleManagement::defaultClientRoleIds(),
                ])
                ->mutateFormDataUsing(function (array $data): array {
                    $data = UserRoleManagement::applyClientDefaults($data);

                    if (filled($data['password'] ?? null)) {
                        $data['password'] = bcrypt($data['password']);
                    }

                    return $data;
                })
                ->after(function (User $record): void {
                    UserRoleManagement::ensureClientPortalRole($record->fresh());
                })
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('Konto uczestnika zapisane')
                        ->body('Nadaj dostęp do imprezy w sekcji portalu klienta na karcie imprezy.'),
                ),
        ];
    }
}
