<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Tag;
use App\Enums\Visibility;
use App\Enums\Status;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tags = [
            [
                'name' => 'Impreza integracyjna',
                'description' => 'Imprezy służące integracji zespołu',
                'visibility' => Visibility::PUBLIC,
                'status' => Status::ACTIVE,
            ],
            [
                'name' => 'Impreza firmowa',
                'description' => 'Imprezy organizowane dla pracowników firmy',
                'visibility' => Visibility::PUBLIC,
                'status' => Status::ACTIVE,
            ],
            [
                'name' => 'Warsztaty',
                'description' => 'Aktywne warsztaty i szkolenia',
                'visibility' => Visibility::PUBLIC,
                'status' => Status::ACTIVE,
            ],
            [
                'name' => 'Wycieczka',
                'description' => 'Wycieczki krajoznawcze i turystyczne',
                'visibility' => Visibility::PUBLIC,
                'status' => Status::ACTIVE,
            ],
            [
                'name' => 'Konferencja',
                'description' => 'Konferencje i spotkania biznesowe',
                'visibility' => Visibility::PUBLIC,
                'status' => Status::ACTIVE,
            ],
            [
                'name' => 'Kolacja',
                'description' => 'Kolacje i spotkania przy stole',
                'visibility' => Visibility::PUBLIC,
                'status' => Status::ACTIVE,
            ],
            [
                'name' => 'Aktywność na świeżym powietrzu',
                'description' => 'Zajęcia i aktywności na zewnątrz',
                'visibility' => Visibility::PUBLIC,
                'status' => Status::ACTIVE,
            ],
            [
                'name' => 'Warsztaty kulinarne',
                'description' => 'Warsztaty gotowania i degustacji',
                'visibility' => Visibility::PUBLIC,
                'status' => Status::ACTIVE,
            ],
            [
                'name' => 'Warsztaty artystyczne',
                'description' => 'Warsztaty plastyczne i artystyczne',
                'visibility' => Visibility::PUBLIC,
                'status' => Status::ACTIVE,
            ],
            [
                'name' => 'Warsztaty team building',
                'description' => 'Warsztaty budowania zespołu',
                'visibility' => Visibility::PUBLIC,
                'status' => Status::ACTIVE,
            ],
        ];

        foreach ($tags as $tag) {
            Tag::create($tag);
        }
    }
}
