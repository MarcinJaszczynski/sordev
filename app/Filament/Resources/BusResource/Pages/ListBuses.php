<?php

namespace App\Filament\Resources\BusResource\Pages;

use App\Filament\Resources\BusResource;
use Filament\Resources\Pages\ListRecords;

class ListBuses extends ListRecords
{
    protected static string $resource = BusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
