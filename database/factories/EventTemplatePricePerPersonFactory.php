<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\EventTemplatePricePerPerson;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EventTemplatePricePerPerson>
 */
class EventTemplatePricePerPersonFactory extends Factory
{
    protected $model = EventTemplatePricePerPerson::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_template_id' => null, // Will be set in test
            'event_template_qty_id' => null, // Will be set in test
            'currency_id' => null, // Will be set in test
            'start_place_id' => null, // Will be set in test
            'price_per_person' => $this->faker->randomFloat(2, 100, 1000),
            'transport_cost' => $this->faker->optional()->randomFloat(2, 50, 200),
            'price_base' => $this->faker->randomFloat(2, 200, 2000),
            'markup_amount' => $this->faker->randomFloat(2, 20, 200),
            'tax_amount' => $this->faker->randomFloat(2, 10, 100),
            'price_with_tax' => $this->faker->randomFloat(2, 250, 2500),
            'tax_breakdown' => [],
            'updated_at' => now(),
            'created_at' => now(),
        ];
    }

    /**
     * Set the price per person.
     */
    public function price(float $price): static
    {
        return $this->state(fn(array $attributes) => [
            'price_per_person' => $price,
        ]);
    }

    /**
     * Set as local price (with start_place_id).
     */
    public function local(int $startPlaceId): static
    {
        return $this->state(fn(array $attributes) => [
            'start_place_id' => $startPlaceId,
        ]);
    }

    /**
     * Set as global price (null start_place_id).
     */
    public function global(): static
    {
        return $this->state(fn(array $attributes) => [
            'start_place_id' => null,
        ]);
    }
}
