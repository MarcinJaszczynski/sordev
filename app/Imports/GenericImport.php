<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Schema;

class GenericImport implements ToModel, WithHeadingRow
{
    protected $modelClass;

    public function __construct($modelClass)
    {
        $this->modelClass = $modelClass;
    }

    public function model(array $row)
    {
        // Pomijaj wiersz, jeśli wartości są identyczne jak klucze (przykładowy wiersz z eksportu)
        $isExample = true;
        foreach ($row as $key => $value) {
            if ((string)$value !== (string)$key) {
                $isExample = false;
                break;
            }
        }
        if ($isExample) {
            return null;
        }

        // Uzupełnij domyślne wartości dla pól liczbowych (nie klucze obce)
        $numericFields = [
            'transfer_km',
            'program_km',
            'duration_days',
            'participant_count',
            'total_cost',
            'capacity',
            'package_price_per_day',
            'package_km_per_day',
            'extra_km_price',
            'price',
            'people_count',
            'unit_price',
            'group_size',
            'price_per_person'
        ];

        foreach ($numericFields as $field) {
            if (!isset($row[$field]) || $row[$field] === '' || $row[$field] === '?') {
                $row[$field] = 0;
            }
        }

        // Ustaw null dla kluczy obcych (aby uniknąć FOREIGN KEY constraint)
        $foreignKeyFields = [
            'bus_id',
            'markup_id',
            'event_template_id',
            'created_by',
            'assigned_to'
        ];

        foreach ($foreignKeyFields as $field) {
            if (!isset($row[$field]) || $row[$field] === '' || $row[$field] === '?' || $row[$field] === '0') {
                $row[$field] = null;
            }
        }

        // Uzupełnij domyślne wartości dla pól tekstowych
        $textFields = [
            'client_email',
            'client_phone',
            'notes',
            'description',
            'currency'
        ];

        foreach ($textFields as $field) {
            if (!isset($row[$field]) || $row[$field] === '?') {
                $row[$field] = '';
            }
        }

        // Tworzy nowy rekord na podstawie CSV (nagłówki muszą odpowiadać polom modelu)
        return new $this->modelClass($row);
    }
}
