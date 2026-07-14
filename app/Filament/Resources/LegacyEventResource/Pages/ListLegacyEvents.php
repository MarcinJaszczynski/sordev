<?php

namespace App\Filament\Resources\LegacyEventResource\Pages;

use App\Filament\Resources\LegacyEventResource;
use Filament\Resources\Pages\ListRecords;

class ListLegacyEvents extends ListRecords
{
    protected static string $resource = LegacyEventResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
