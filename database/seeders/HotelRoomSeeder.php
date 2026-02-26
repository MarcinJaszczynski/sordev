<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\HotelRoom;

class HotelRoomSeeder extends Seeder
{
    public function run(): void
    {
        $rooms = [
            [
                'name' => 'Pokój 1-osobowy',
                'people_count' => 1,
                'description' => 'Mały pokój dla jednej osoby',
                'notes' => 'Widok na ogród',
                'price' => 180,
                'currency' => 'PLN',
                'convert_to_pln' => true,
            ],
            [
                'name' => 'Pokój 2-osobowy',
                'people_count' => 2,
                'description' => 'Standardowy pokój dwuosobowy',
                'notes' => 'Możliwość dostawki',
                'price' => 250,
                'currency' => 'PLN',
                'convert_to_pln' => true,
            ],
            [
                'name' => 'Apartament',
                'people_count' => 4,
                'description' => 'Apartament z salonem',
                'notes' => 'Luksusowy standard',
                'price' => 600,
                'currency' => 'PLN',
                'convert_to_pln' => true,
            ],
            [
                'name' => 'Studio rodzinne',
                'people_count' => 5,
                'description' => 'Pokój rodzinny z aneksem',
                'notes' => 'Idealny dla rodzin',
                'price' => 400,
                'currency' => 'PLN',
                'convert_to_pln' => true,
            ],
            [
                'name' => 'Pokój 3-osobowy',
                'people_count' => 3,
                'description' => 'Pokój dla trzech osób',
                'notes' => 'Dostępne dwa łóżka',
                'price' => 320,
                'currency' => 'PLN',
                'convert_to_pln' => true,
            ],
        ];
        foreach ($rooms as $room) {
            HotelRoom::firstOrCreate([
                'name' => $room['name'],
            ], $room);
        }
    }
}
