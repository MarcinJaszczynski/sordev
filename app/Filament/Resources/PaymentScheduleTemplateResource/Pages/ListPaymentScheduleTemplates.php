<?php

namespace App\Filament\Resources\PaymentScheduleTemplateResource\Pages;

use App\Filament\Resources\PaymentScheduleTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPaymentScheduleTemplates extends ListRecords
{
    protected static string $resource = PaymentScheduleTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
