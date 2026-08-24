<?php

namespace App\Filament\Resources\FaqEntryResource\Pages;

use App\Filament\Resources\FaqEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFaqEntry extends EditRecord
{
    protected static string $resource = FaqEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
