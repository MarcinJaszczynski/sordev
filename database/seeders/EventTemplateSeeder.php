<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\EventTemplate;
use App\Models\Tag;
use App\Models\EventTemplateProgramPoint;
use Illuminate\Support\Str;

class EventTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Pobierz tagi
        $integrationTag = Tag::where('name', 'Impreza integracyjna')->first();
        $companyTag = Tag::where('name', 'Impreza firmowa')->first();
        $workshopTag = Tag::where('name', 'Warsztaty')->first();
        $dinnerTag = Tag::where('name', 'Kolacja')->first();

        // Pobierz punkty programu
        $workshopPoint = EventTemplateProgramPoint::where('name', 'Warsztaty integracyjne')->first();
        $dinnerPoint = EventTemplateProgramPoint::where('name', 'Kolacja w restauracji')->first();
        $cookingPoint = EventTemplateProgramPoint::where('name', 'Warsztaty kulinarne')->first();
        $teamBuildingPoint = EventTemplateProgramPoint::where('name', 'Warsztaty team building')->first();

        $templates = [
            [
                'name' => 'Jednodniowa impreza integracyjna',
                'slug' => 'jednodniowa-impreza-integracyjna',
                'duration_days' => 1,
                'event_description' => 'Intensywna jednodniowa impreza integracyjna z warsztatami i kolacją.',
                'office_description' => 'Impreza przeznaczona dla zespołów do 30 osób. Wymaga rezerwacji z wyprzedzeniem.',
                'notes' => 'Sprawdzić dostępność sali konferencyjnej i restauracji.',
            ],
            [
                'name' => 'Weekend integracyjny',
                'slug' => 'weekend-integracyjny',
                'duration_days' => 2,
                'event_description' => 'Weekendowy wyjazd integracyjny z warsztatami, aktywnościami i wspólnymi posiłkami.',
                'office_description' => 'Wymaga rezerwacji hotelu i transportu. Maksymalnie 40 osób.',
                'notes' => 'Uwzględnić czas na dojazd i powrót uczestników.',
            ],
            [
                'name' => 'Wieczór firmowy',
                'slug' => 'wieczor-firmowy',
                'duration_days' => 1,
                'event_description' => 'Elegancki wieczór firmowy z kolacją i programem rozrywkowym.',
                'office_description' => 'Impreza wieczorna, wymaga eleganckiej restauracji.',
                'notes' => 'Sprawdzić dress code i menu.',
            ],
        ];

        foreach ($templates as $template) {
            $eventTemplate = \App\Models\EventTemplate::firstOrCreate(
                ['slug' => $template['slug']],
                $template
            );

            // Dodaj tagi
            if ($template['slug'] === 'jednodniowa-impreza-integracyjna') {
                $eventTemplate->tags()->attach([$integrationTag->id, $workshopTag->id, $dinnerTag->id]);
                
                // Dodaj punkty programu
                $eventTemplate->programPoints()->attach($workshopPoint->id, [
                    'day' => 1,
                    'order' => 1,
                    'notes' => 'Warsztaty poranne',
                    'include_in_program' => true,
                    'include_in_calculation' => true,
                    'active' => true,
                ]);
                
                $eventTemplate->programPoints()->attach($dinnerPoint->id, [
                    'day' => 1,
                    'order' => 2,
                    'notes' => 'Kolacja wieczorna',
                    'include_in_program' => true,
                    'include_in_calculation' => true,
                    'active' => true,
                ]);
            } elseif ($template['slug'] === 'weekend-integracyjny') {
                $eventTemplate->tags()->attach([$integrationTag->id, $companyTag->id, $workshopTag->id]);
                
                // Dodaj punkty programu
                $eventTemplate->programPoints()->attach($teamBuildingPoint->id, [
                    'day' => 1,
                    'order' => 1,
                    'notes' => 'Warsztaty team building',
                    'include_in_program' => true,
                    'include_in_calculation' => true,
                    'active' => true,
                ]);
                
                $eventTemplate->programPoints()->attach($cookingPoint->id, [
                    'day' => 1,
                    'order' => 2,
                    'notes' => 'Warsztaty kulinarne',
                    'include_in_program' => true,
                    'include_in_calculation' => true,
                    'active' => true,
                ]);
                
                $eventTemplate->programPoints()->attach($dinnerPoint->id, [
                    'day' => 1,
                    'order' => 3,
                    'notes' => 'Kolacja integracyjna',
                    'include_in_program' => true,
                    'include_in_calculation' => true,
                    'active' => true,
                ]);
            } elseif ($template['slug'] === 'wieczor-firmowy') {
                $eventTemplate->tags()->attach([$companyTag->id, $dinnerTag->id]);
                
                // Dodaj punkty programu
                $eventTemplate->programPoints()->attach($dinnerPoint->id, [
                    'day' => 1,
                    'order' => 1,
                    'notes' => 'Elegancka kolacja firmowa',
                    'include_in_program' => true,
                    'include_in_calculation' => true,
                    'active' => true,
                ]);
            }
        }
    }
}
