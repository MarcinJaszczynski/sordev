<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EventTemplateProgramPoint;
use App\Models\Currency;

class EventTemplateProgramPointSeeder extends Seeder
{
    public function run(): void
    {
        // Upewnij się, że mamy walutę PLN
        $pln = Currency::firstOrCreate([
            'name' => 'Złoty polski',
            'symbol' => 'PLN',
        ]);

        $programPoints = [
            [
                'name' => 'Śniadanie w hotelu',
                'description' => 'Śniadanie w formie bufetu w restauracji hotelowej',
                'duration_hours' => 1,
                'duration_minutes' => 30,
                'unit_price' => 50.00,
                'currency_id' => $pln->id,
                'group_size' => 1,
                'convert_to_pln' => true,
            ],
            [
                'name' => 'Zwiedzanie Starego Miasta',
                'description' => 'Spacer z przewodnikiem po zabytkowej części miasta',
                'duration_hours' => 2,
                'duration_minutes' => 0,
                'unit_price' => 300.00,
                'currency_id' => $pln->id,
                'group_size' => 20,
                'convert_to_pln' => true,
            ],
            [
                'name' => 'Obiad w restauracji regionalnej',
                'description' => 'Tradycyjne dania kuchni polskiej',
                'duration_hours' => 1,
                'duration_minutes' => 30,
                'unit_price' => 80.00,
                'currency_id' => $pln->id,
                'group_size' => 1,
                'convert_to_pln' => true,
            ],
            [
                'name' => 'Kolacja w hotelu',
                'description' => 'Kolacja serwowana w restauracji hotelowej',
                'duration_hours' => 1,
                'duration_minutes' => 0,
                'unit_price' => 60.00,
                'currency_id' => $pln->id,
                'group_size' => 1,
                'convert_to_pln' => true,
            ],
            [
                'name' => 'Warsztaty rękodzieła',
                'description' => 'Warsztaty tworzenia tradycyjnych wyrobów rzemieślniczych',
                'duration_hours' => 3,
                'duration_minutes' => 0,
                'unit_price' => 150.00,
                'currency_id' => $pln->id,
                'group_size' => 10,
                'convert_to_pln' => true,
            ],
            // Dodane przykładowe punkty programu, aby EventTemplateSeeder mógł je znaleźć
            [
                'name' => 'Warsztaty integracyjne',
                'description' => 'Warsztaty służące integracji zespołu',
                'duration_hours' => 2,
                'duration_minutes' => 0,
                'unit_price' => 200.00,
                'currency_id' => $pln->id,
                'group_size' => 15,
                'convert_to_pln' => true,
            ],
            [
                'name' => 'Kolacja w restauracji',
                'description' => 'Kolacja w eleganckiej restauracji',
                'duration_hours' => 1,
                'duration_minutes' => 30,
                'unit_price' => 100.00,
                'currency_id' => $pln->id,
                'group_size' => 1,
                'convert_to_pln' => true,
            ],
            [
                'name' => 'Warsztaty kulinarne',
                'description' => 'Warsztaty gotowania i degustacji',
                'duration_hours' => 2,
                'duration_minutes' => 30,
                'unit_price' => 180.00,
                'currency_id' => $pln->id,
                'group_size' => 10,
                'convert_to_pln' => true,
            ],
            [
                'name' => 'Warsztaty team building',
                'description' => 'Warsztaty budowania zespołu',
                'duration_hours' => 3,
                'duration_minutes' => 0,
                'unit_price' => 250.00,
                'currency_id' => $pln->id,
                'group_size' => 20,
                'convert_to_pln' => true,
            ],
        ];

        foreach ($programPoints as $point) {
            EventTemplateProgramPoint::create($point);
        }
    }
} 