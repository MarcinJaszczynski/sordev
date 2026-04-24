<?php

namespace App\Exports;

use App\Models\Event;
use Maatwebsite\Excel\Concerns\FromCollection;

class EventsExport implements FromCollection
{
    public function collection()
    {
        return Event::all();
    }
}
