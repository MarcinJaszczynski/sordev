<?php

namespace App\Filament\Resources\KategoriaSzablonuResource\Pages;

use App\Filament\Resources\KategoriaSzablonuResource;
use Filament\Resources\Pages\ListRecords;

class ListKategoriaSzablonu extends ListRecords
{
    protected static string $resource = KategoriaSzablonuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make()
                ->label('Dodaj kategorię szablonu')
                ->icon('heroicon-o-plus'),
        ];
    }
}
