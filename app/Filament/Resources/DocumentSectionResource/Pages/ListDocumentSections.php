<?php

namespace App\Filament\Resources\DocumentSectionResource\Pages;

use App\Filament\Resources\DocumentSectionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDocumentSections extends ListRecords
{
    protected static string $resource = DocumentSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
