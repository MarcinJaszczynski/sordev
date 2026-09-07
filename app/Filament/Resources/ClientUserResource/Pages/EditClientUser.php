<?php

namespace App\Filament\Resources\ClientUserResource\Pages;

use App\Filament\Forms\ClientInvoiceRequestFormFields;
use App\Filament\Resources\ClientUserResource;
use App\Support\ClientInvoiceRequestAdminHelper;
use App\Support\UserRoleManagement;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Schema;

class EditClientUser extends EditRecord
{
    protected static string $resource = ClientUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create_invoice_request')
                ->label('Wniosek o fakturę')
                ->icon('heroicon-o-document-plus')
                ->color('gray')
                ->visible(fn (): bool => Schema::hasTable('client_invoice_requests')
                    && filled($this->record->email))
                ->modalHeading('Wniosek o fakturę dla użytkownika')
                ->modalIcon('heroicon-o-receipt-percent')
                ->modalWidth('3xl')
                ->modalSubmitActionLabel('Zapisz wniosek')
                ->fillForm(fn (): array => ClientInvoiceRequestAdminHelper::prefillFromUser($this->record))
                ->form(ClientInvoiceRequestFormFields::adminCreateSchema())
                ->action(function (array $data): void {
                    ClientInvoiceRequestAdminHelper::createFromAdminForm(
                        $data,
                        linkedUser: $this->record,
                    );

                    Notification::make()
                        ->title('Utworzono wniosek o fakturę')
                        ->success()
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = UserRoleManagement::applyClientDefaults($data);

        if (filled($data['password'] ?? null)) {
            $data['password'] = bcrypt($data['password']);
        } else {
            unset($data['password']);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        UserRoleManagement::ensureClientPortalRole($this->record->fresh());
    }
}
