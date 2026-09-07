<?php

namespace App\Filament\Resources\EventPriceDescriptionResource\Pages;

use App\Filament\Resources\EventPriceDescriptionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEventPriceDescription extends EditRecord
{
    protected static string $resource = EventPriceDescriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
