<?php

namespace App\Filament\Pilot\Resources\PilotEventResource\Pages;

use App\Filament\Pilot\Resources\PilotEventResource;
use Filament\Resources\Pages\ListRecords;

class ListPilotEvents extends ListRecords
{
    protected static string $resource = PilotEventResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
