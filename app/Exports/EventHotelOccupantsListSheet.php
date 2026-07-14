<?php

namespace App\Exports;

use App\Models\Event;
use App\Services\EventHotelOccupantsTemplateBuilder;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class EventHotelOccupantsListSheet implements FromArray, WithHeadings, WithTitle
{
    public function __construct(
        private readonly Event $event,
    ) {}

    public function title(): string
    {
        return 'Lista osób';
    }

    public function headings(): array
    {
        return app(EventHotelOccupantsTemplateBuilder::class)->headings();
    }

    public function array(): array
    {
        return app(EventHotelOccupantsTemplateBuilder::class)->dataRows($this->event);
    }
}
