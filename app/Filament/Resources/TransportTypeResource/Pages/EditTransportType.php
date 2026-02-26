<?php

namespace App\Filament\Resources\TransportTypeResource\Pages;

use App\Filament\Resources\TransportTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTransportType extends EditRecord
{
    protected static string $resource = TransportTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
