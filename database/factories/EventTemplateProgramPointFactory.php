<?php

namespace Database\Factories;

use App\Models\EventTemplateProgramPoint;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventTemplateProgramPointFactory extends Factory
{
    protected $model = EventTemplateProgramPoint::class;

    public function definition()
    {
        // ensure there's a currency available (tests run on sqlite in-memory)
        $currency = \App\Models\Currency::first() ?? \App\Models\Currency::create([
            'name' => 'Polski Zloty',
            'symbol' => 'PLN',
            'exchange_rate' => 1.0,
        ]);

        return [
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->optional()->text(120),
            'parent_id' => null,
            'order' => $this->faker->numberBetween(1, 10),
            'office_notes' => $this->faker->optional()->text(60),
            'pilot_notes' => $this->faker->optional()->text(60),
            'duration_hours' => $this->faker->numberBetween(0, 4),
            'duration_minutes' => $this->faker->randomElement([0, 15, 30, 45]),
            'featured_image' => null,
            'gallery_images' => [],
            'unit_price' => $this->faker->randomFloat(2, 0, 1000),
            'group_size' => $this->faker->numberBetween(1, 10),
            'currency_id' => $currency->id,
            'convert_to_pln' => false,
        ];
    }
}
