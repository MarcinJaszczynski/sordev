<?php

namespace Database\Factories;

use App\Models\EventTemplateStartingPlaceAvailability;
use App\Models\EventTemplate;
use App\Models\Place;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventTemplateStartingPlaceAvailabilityFactory extends Factory
{
    protected $model = EventTemplateStartingPlaceAvailability::class;

    public function definition(): array
    {
        return [
            'event_template_id' => EventTemplate::factory(),
            'start_place_id' => Place::factory(),
            'end_place_id' => null,
            'available' => true,
            'note' => null,
        ];
    }
}
