<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Currency;
use Carbon\Carbon;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currencies = [
            [
                'name' => 'Złoty polski',
                'symbol' => 'PLN',
                'exchange_rate' => 1.0000,
                'last_updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Euro',
                'symbol' => 'EUR',
                'exchange_rate' => 4.3000,
                'last_updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Dolar amerykański',
                'symbol' => 'USD',
                'exchange_rate' => 3.9500,
                'last_updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Funt brytyjski',
                'symbol' => 'GBP',
                'exchange_rate' => 5.0000,
                'last_updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Frank szwajcarski',
                'symbol' => 'CHF',
                'exchange_rate' => 4.3500,
                'last_updated_at' => Carbon::now(),
            ],
        ];

        foreach ($currencies as $currency) {
            Currency::create($currency);
        }
    }
}
