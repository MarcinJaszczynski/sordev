<?php

namespace Database\Factories;

use App\Models\EventProgramPoint;
use App\Models\EventTemplateProgramPoint;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventProgramPointFactory extends Factory
{
    protected $model = EventProgramPoint::class;

    public function definition(): array
    {
        return [
            'event_template_program_point_id' => EventTemplateProgramPoint::factory(),
            'name' => $this->faker->sentence(3),
            'day' => 1,
            'order' => 1,
            'include_in_program' => true,
            'include_in_calculation' => false,
            'active' => true,
            'show_title_style' => false,
            'show_description' => true,
        ];
    }
}
