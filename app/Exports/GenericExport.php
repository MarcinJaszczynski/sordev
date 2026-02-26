<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Support\Facades\Schema;

class GenericExport implements FromCollection, WithHeadings, WithMapping
{
    protected $modelClass;

    public function __construct($modelClass)
    {
        $this->modelClass = $modelClass;
    }

    public function collection()
    {
        // Dodajemy pusty rekord z nazwami pól jako pierwszy wiersz (przykład dla importu)
        $fields = $this->headings();
        $exampleRow = [];
        foreach ($fields as $field) {
            $exampleRow[$field] = $field;
        }
        $collection = collect([$exampleRow]);
        return $collection->concat(($this->modelClass)::all());
    }

    public function headings(): array
    {
        // Preferuj fillable, potem wszystkie kolumny
        $model = ($this->modelClass)::getModel();
        $fillable = $model->getFillable();
        if (!empty($fillable)) {
            return $fillable;
        }
        // Jeśli fillable puste, pobierz wszystkie kolumny z bazy
        if (method_exists($model, 'getTable')) {
            $table = $model->getTable();
            try {
                $columns = Schema::getColumnListing($table);
                return $columns;
            } catch (\Throwable $e) {
                // fallback
            }
        }
        // fallback: z pierwszego rekordu
        $first = ($this->modelClass)::first();
        if ($first) {
            return array_keys($first->getAttributes());
        }
        return [];
    }

    public function map($row): array
    {
        $fields = $this->headings();
        $result = [];
        foreach ($fields as $field) {
            $result[] = $row[$field] ?? '';
        }
        return $result;
    }
}
