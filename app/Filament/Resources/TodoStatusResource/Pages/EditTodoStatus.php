<?php

namespace App\Filament\Resources\TodoStatusResource\Pages;

use App\Filament\Resources\TodoStatusResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTodoStatus extends EditRecord
{
    protected static string $resource = TodoStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
