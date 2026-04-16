<?php

namespace App\Filament\Resources\CurrencyRateSnapshotResource\Pages;

use App\Filament\Resources\CurrencyRateSnapshotResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCurrencyRateSnapshots extends ListRecords
{
    protected static string $resource = CurrencyRateSnapshotResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Dodaj kurs')];
    }
}
