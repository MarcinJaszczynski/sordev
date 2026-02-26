<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Place;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Place>
 */
class PlaceFactory extends Factory
{
    protected $model = Place::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->city(),
            // 'slug' => Str::slug($this->faker->city()), // tabela places nie posiada kolumny slug
            'starting_place' => $this->faker->boolean(),
            'description' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the place is a starting place.
     */
    public function starting(): static
    {
        return $this->state(fn(array $attributes) => [
            'starting_place' => true,
        ]);
    }
}
