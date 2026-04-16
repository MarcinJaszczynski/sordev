<?php

namespace App\Filament\Resources\CurrencyRateSnapshotResource\Pages;

use App\Filament\Resources\CurrencyRateSnapshotResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCurrencyRateSnapshot extends EditRecord
{
    protected static string $resource = CurrencyRateSnapshotResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
