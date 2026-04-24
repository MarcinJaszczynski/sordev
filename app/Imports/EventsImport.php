<?php

namespace App\Imports;

use App\Models\Event;
use Maatwebsite\Excel\Concerns\ToModel;

class EventsImport implements ToModel
{
    public function model(array $row)
    {
        return new Event([
            'name' => $row[0] ?? null,
            'start_date' => $row[1] ?? null,
            'end_date' => $row[2] ?? null,
            // Dodaj tu kolejne pola zgodnie z kolejnością kolumn w CSV
        ]);
    }
}
