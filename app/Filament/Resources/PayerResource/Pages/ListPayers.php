<?php

namespace App\Filament\Resources\PayerResource\Pages;

use App\Filament\Resources\PayerResource;
use Filament\Resources\Pages\ListRecords;

class ListPayers extends ListRecords
{
    protected static string $resource = PayerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
