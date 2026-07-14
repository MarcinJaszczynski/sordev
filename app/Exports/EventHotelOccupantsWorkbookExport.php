<?php

namespace App\Exports;

use App\Models\Event;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class EventHotelOccupantsWorkbookExport implements WithMultipleSheets
{
    public function __construct(
        private readonly Event $event,
    ) {}

    public function sheets(): array
    {
        return [
            new EventHotelOccupantsListSheet($this->event),
            new EventHotelOccupantsInstructionsSheet,
        ];
    }
}
