<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Bus;

class BusSeeder extends Seeder
{
    public function run(): void
    {
        $buses = [
            [
                'name' => 'Mercedes Sprinter 19+1',
                'description' => 'Nowoczesny autobus klasy premium z klimatyzacją i systemem audio',
                'capacity' => 19,
                'package_price_per_day' => 800,
                'package_km_per_day' => 300,
                'extra_km_price' => 2.5,
            ],
            [
                'name' => 'Mercedes Tourismo 55+2',
                'description' => 'Autokar turystyczny z toaletą, Wi-Fi i dużymi oknami',
                'capacity' => 55,
                'package_price_per_day' => 1200,
                'package_km_per_day' => 500,
                'extra_km_price' => 3.0,
            ],
            [
                'name' => 'Iveco Daily 15+1',
                'description' => 'Kompaktowy autobus idealny na krótkie trasy miejskie',
                'capacity' => 15,
                'package_price_per_day' => 600,
                'package_km_per_day' => 200,
                'extra_km_price' => 2.0,
            ],
            [
                'name' => 'Setra S515 HD 49+2',
                'description' => 'Luksusowy autokar z fotolami skórzanymi i systemem rozrywki',
                'capacity' => 49,
                'package_price_per_day' => 1500,
                'package_km_per_day' => 600,
                'extra_km_price' => 3.5,
            ],
        ];

        foreach ($buses as $bus) {
            Bus::firstOrCreate([
                'name' => $bus['name'],
            ], $bus);
        }
    }
}
