<?php

namespace App\Filament\Resources\HotelRoomResource\Pages;

use App\Filament\Resources\HotelRoomResource;
use Filament\Resources\Pages\ListRecords;

class ListHotelRooms extends ListRecords
{
    protected static string $resource = HotelRoomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
