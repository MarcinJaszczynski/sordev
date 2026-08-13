<?php

namespace App\Filament\Resources\PaymentScheduleTemplateResource\Pages;

use App\Filament\Resources\PaymentScheduleTemplateResource;
use App\Models\PaymentScheduleTemplate;
use App\Services\PaymentScheduleTemplateService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPaymentScheduleTemplate extends EditRecord
{
    protected static string $resource = PaymentScheduleTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('newVersion')
                ->label('Nowa wersja')
                ->icon('heroicon-o-document-duplicate')
                ->requiresConfirmation()
                ->action(function (): void {
                    /** @var PaymentScheduleTemplate $record */
                    $record = $this->record;
                    $clone = $record->createNewVersion('Edycja jako nowa wersja');
                    Notification::make()->title('Utworzono v'.$clone->version)->success()->send();
                    $this->redirect(PaymentScheduleTemplateResource::getUrl('edit', ['record' => $clone]));
                }),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return PaymentScheduleTemplateResource::mutateFormDataBeforeFill(
            array_merge($data, ['id' => $this->record->getKey()])
        );
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->installmentRows = is_array($data['installments'] ?? null) ? $data['installments'] : [];
        unset($data['installments']);

        return $data;
    }

    /** @var array<int, array<string, mixed>> */
    protected array $installmentRows = [];

    protected function afterSave(): void
    {
        app(PaymentScheduleTemplateService::class)->syncInstallments($this->record, $this->installmentRows);
    }
}
