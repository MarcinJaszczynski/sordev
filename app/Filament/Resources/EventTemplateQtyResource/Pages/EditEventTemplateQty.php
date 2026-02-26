<?php

namespace App\Filament\Resources\EventTemplateQtyResource\Pages;

use App\Filament\Resources\EventTemplateQtyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEventTemplateQty extends EditRecord
{
    protected static string $resource = EventTemplateQtyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
