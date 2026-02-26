<?php

namespace App\Filament\Resources\EventTemplateQtyResource\Pages;

use App\Filament\Resources\EventTemplateQtyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEventTemplateQties extends ListRecords
{
    protected static string $resource = EventTemplateQtyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
