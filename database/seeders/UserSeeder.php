<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'm.jaszczynski@gmail.com'],
            [
                'name' => 'Michał Jaszczyński',
                'password' => Hash::make('1234'),
                'type' => 'user',
                'status' => 'active',
            ]
        );
        User::firstOrCreate(
            ['email' => 'anna.kowalska@example.com'],
            [
                'name' => 'Anna Kowalska',
                'password' => Hash::make('haslo123'),
                'type' => 'user',
                'status' => 'active',
            ]
        );
        User::firstOrCreate(
            ['email' => 'jan.nowak@example.com'],
            [
                'name' => 'Jan Nowak',
                'password' => Hash::make('qwerty123'),
                'type' => 'user',
                'status' => 'active',
            ]
        );
        User::firstOrCreate([
            'email' => 'piotr.zielinski@example.com'
        ], [
            'name' => 'Piotr Zieliński',
            'password' => Hash::make('zielony2024'),
            'type' => 'pilot',
            'status' => 'active',
        ]);
        User::firstOrCreate([
            'email' => 'biuro@firma.pl'
        ], [
            'name' => 'Biuro Obsługi',
            'password' => Hash::make('biuro123'),
            'type' => 'biuro',
            'status' => 'active',
        ]);
        User::firstOrCreate([
            'email' => 'ksiegowosc@firma.pl'
        ], [
            'name' => 'Dział Księgowości',
            'password' => Hash::make('ksiegowosc123'),
            'type' => 'ksiegowosc',
            'status' => 'active',
        ]);
    }
}
