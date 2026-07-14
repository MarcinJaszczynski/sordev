<?php

namespace Database\Seeders;

use App\Models\ChecklistTemplate;
use Illuminate\Database\Seeder;

class ChecklistTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Wyjazd krajowy',
                'description' => 'Uniwersalna checklista dla wycieczek na terenie Polski.',
                'sort_order' => 1,
                'items' => [
                    'Potwierdzić autokar i dane kierowcy',
                    'Sprawdzić rezerwacje hoteli',
                    'Przygotować gotówkę / zaliczkę pilota',
                    'Wydrukować pakiet pilota (lista uczestników, program)',
                    'Potwierdzić ubezpieczenie grupy',
                    'Potwierdzić rezerwacje atrakcji / biletów wstępu',
                    'Zebrać numery kontaktowe do uczestników / opiekunów',
                ],
            ],
            [
                'name' => 'Wyjazd zagraniczny',
                'description' => 'Checklista rozszerzona o formalności i transport zagraniczny.',
                'sort_order' => 2,
                'items' => [
                    'Sprawdzić ważność dokumentów (paszport / dowód) uczestników',
                    'Potwierdzić bilety lotnicze / transport',
                    'Potwierdzić transfery lotnisko - hotel',
                    'Sprawdzić ubezpieczenie zagraniczne (KL, NNW, bagaż)',
                    'Przygotować walutę / kartę na wydatki',
                    'Potwierdzić rezerwacje hoteli i wyżywienie',
                    'Wydrukować pakiet pilota i dokumenty podróży',
                    'Sprawdzić wymagania wjazdowe (wizy, EKUZ)',
                    'Zebrać kontakty alarmowe i dane ambasady',
                ],
            ],
            [
                'name' => 'Wycieczka jednodniowa',
                'description' => 'Skrócona checklista dla wyjazdów jednodniowych.',
                'sort_order' => 3,
                'items' => [
                    'Potwierdzić autokar i godzinę zbiórki',
                    'Wydrukować listę uczestników',
                    'Potwierdzić rezerwacje atrakcji / biletów',
                    'Przygotować apteczkę',
                    'Przygotować gotówkę / zaliczkę pilota',
                    'Potwierdzić ubezpieczenie grupy',
                ],
            ],
        ];

        foreach ($templates as $data) {
            $items = $data['items'];
            unset($data['items']);

            $template = ChecklistTemplate::query()->updateOrCreate(
                ['name' => $data['name']],
                array_merge($data, ['is_active' => true]),
            );

            $template->items()->delete();

            foreach ($items as $index => $title) {
                $template->items()->create([
                    'title' => $title,
                    'sort_order' => $index + 1,
                ]);
            }
        }
    }
}
