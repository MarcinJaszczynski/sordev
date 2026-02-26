<?php

namespace App\Filament\Resources\EventPriceDescriptionResource\Pages;

use App\Filament\Resources\EventPriceDescriptionResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\CreateAction;

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
