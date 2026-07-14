<?php

namespace App\Exports;

use App\Services\EventHotelOccupantsTemplateBuilder;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class EventHotelOccupantsInstructionsSheet implements FromArray, WithTitle
{
    public function title(): string
    {
        return 'Instrukcja';
    }

    public function array(): array
    {
        return app(EventHotelOccupantsTemplateBuilder::class)->instructionLines();
    }
}
