<?php

namespace App\Filament\Resources\TodoStatusResource\Pages;

use App\Filament\Resources\TodoStatusResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTodoStatuses extends ListRecords
{
    protected static string $resource = TodoStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
