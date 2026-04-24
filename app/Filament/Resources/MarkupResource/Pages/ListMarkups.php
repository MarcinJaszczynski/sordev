<?php

namespace App\Filament\Resources\MarkupResource\Pages;

use App\Filament\Resources\MarkupResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

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
