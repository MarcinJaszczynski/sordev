<?php

namespace App\Filament\Resources\EventSettlementResource\Pages;

use App\Filament\Resources\EventSettlementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEventSettlements extends ListRecords
{
    protected static string $resource = EventSettlementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nowe rozliczenie'),
        ];
    }
}
