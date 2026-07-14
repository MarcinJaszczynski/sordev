<?php

namespace App\Filament\Resources\TfgDictionaryResource\Pages;

use App\Filament\Resources\TfgDictionaryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTfgDictionaryItems extends ListRecords
{
    protected static string $resource = TfgDictionaryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
