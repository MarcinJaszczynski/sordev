<?php

namespace App\Filament\Client\Resources\ClientEventResource\Pages;

use App\Filament\Client\Resources\ClientEventResource;
use Filament\Resources\Pages\ListRecords;

class ListClientEvents extends ListRecords
{
    protected static string $resource = ClientEventResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
