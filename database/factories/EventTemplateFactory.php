<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EventTemplate>
 */
class EventTemplateFactory extends Factory
{
    protected $model = \App\Models\EventTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(3),
            'slug' => Str::slug($this->faker->sentence(3)),
            'duration_days' => $this->faker->numberBetween(1, 10),
            'featured_image' => $this->faker->imageUrl(),
            'event_description' => $this->faker->paragraph(),
            'gallery' => [$this->faker->imageUrl(), $this->faker->imageUrl()],
            'office_description' => $this->faker->paragraph(),
            'notes' => $this->faker->sentence(),
        ];
    }
}
