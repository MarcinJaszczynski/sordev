<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Payer;

class PayerSeeder extends Seeder
{
    public function run(): void
    {
        $payers = [
            [
                'name' => 'Firma ABC Sp. z o.o.',
                'description' => 'Duża firma IT z siedzibą w Warszawie',
            ],
            [
                'name' => 'Szkoła Podstawowa nr 5',
                'description' => 'Publiczna szkoła podstawowa organizująca wycieczki edukacyjne',
            ],
            [
                'name' => 'Bank XYZ S.A.',
                'description' => 'Instytucja finansowa organizująca eventy dla pracowników',
            ],
            [
                'name' => 'Stowarzyszenie Emerytów',
                'description' => 'Organizacja pozarządowa dla seniorów',
            ],
            [
                'name' => 'Uniwersytet Warszawski',
                'description' => 'Uczelnia wyższa organizująca wyjazdy studenckie',
            ],
        ];

        foreach ($payers as $payer) {
            Payer::firstOrCreate([
                'name' => $payer['name'],
            ], $payer);
        }
    }
}
