<?php

namespace App\Filament\Resources\PaymentScheduleTemplateResource\Pages;

use App\Filament\Resources\PaymentScheduleTemplateResource;
use App\Services\PaymentScheduleTemplateService;
use Filament\Resources\Pages\CreateRecord;

class CreatePaymentScheduleTemplate extends CreateRecord
{
    protected static string $resource = PaymentScheduleTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['installments']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $rows = is_array($this->data['installments'] ?? null) ? $this->data['installments'] : [];
        if ($rows === []) {
            $rows = app(PaymentScheduleTemplateService::class)->defaultInstallmentRows();
        }

        app(PaymentScheduleTemplateService::class)->syncInstallments($this->record, $rows);
    }
}
