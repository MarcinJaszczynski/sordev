<?php

namespace App\Filament\Resources\TfgDictionaryResource\Pages;

use App\Filament\Resources\TfgDictionaryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTfgDictionaryItem extends EditRecord
{
    protected static string $resource = TfgDictionaryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
