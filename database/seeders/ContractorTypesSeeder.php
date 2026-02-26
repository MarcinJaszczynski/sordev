<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContractorTypesSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['id' => 1, 'name' => 'hotel'],
            ['id' => 2, 'name' => 'przewodnik'],
            ['id' => 3, 'name' => 'muzeum'],
            ['id' => 4, 'name' => 'klient'],
            ['id' => 5, 'name' => 'pilot'],
            ['id' => 6, 'name' => 'kierowca'],
            ['id' => 7, 'name' => 'przewoźnik'],
            ['id' => 8, 'name' => 'restauracja'],
            ['id' => 9, 'name' => 'Biuro przewodnickie'],
            ['id' => 10, 'name' => 'Warsztaty'],
            ['id' => 11, 'name' => 'Inne'],
            ['id' => 12, 'name' => 'Kontrahent zagraniczny'],
        ];

    // insertOrIgnore to be idempotent on repeated runs (SQLite/MySQL safe)
    DB::table('contractor_types')->insertOrIgnore($types);
    }
}
