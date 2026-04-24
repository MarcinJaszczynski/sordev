<?php

namespace App\Filament\Resources\EventPriceDescriptionResource\Pages;

use App\Filament\Resources\EventPriceDescriptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEventPriceDescriptions extends ListRecords
{
    protected static string $resource = EventPriceDescriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
