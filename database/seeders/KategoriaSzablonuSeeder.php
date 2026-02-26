<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\KategoriaSzablonu;

class KategoriaSzablonuSeeder extends Seeder
{
    public function run(): void
    {
        KategoriaSzablonu::create([
            'nazwa' => 'Wycieczki szkolne',
            'opis' => 'Szablony dla wycieczek szkolnych i edukacyjnych.',
            'uwagi' => 'Wymaga zgody opiekuna.',
        ]);
        KategoriaSzablonu::create([
            'nazwa' => 'Obozy letnie',
            'opis' => 'Szablony dla obozów letnich, sportowych i rekreacyjnych.',
            'uwagi' => null,
        ]);
        KategoriaSzablonu::create([
            'nazwa' => 'Imprezy firmowe',
            'opis' => null,
            'uwagi' => 'Możliwość tagowania według branży.',
        ]);
        KategoriaSzablonu::create([
            'nazwa' => 'Wyjazdy integracyjne',
            'opis' => 'Szablony dla wyjazdów integracyjnych i motywacyjnych.',
            'uwagi' => 'Możliwość łączenia z warsztatami.',
        ]);
        KategoriaSzablonu::create([
            'nazwa' => 'Konferencje',
            'opis' => 'Szablony dla konferencji i spotkań biznesowych.',
            'uwagi' => null,
        ]);
    }
}
