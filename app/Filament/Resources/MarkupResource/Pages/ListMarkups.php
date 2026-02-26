<?php

namespace App\Filament\Resources\MarkupResource\Pages;

use App\Filament\Resources\MarkupResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListMarkups extends ListRecords
{
    protected static string $resource = MarkupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
