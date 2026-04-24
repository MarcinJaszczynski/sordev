<?php

namespace App\Filament\Resources\PaymentStatusResource\Pages;

use App\Filament\Resources\PaymentStatusResource;
use Filament\Resources\Pages\ListRecords;

class ListPaymentStatuses extends ListRecords
{
    protected static string $resource = PaymentStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
