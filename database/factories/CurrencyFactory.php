<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Currency;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Currency>
 */
class CurrencyFactory extends Factory
{
    protected $model = Currency::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->word() . ' Currency',
            'symbol' => strtoupper($this->faker->lexify('???')),
            'exchange_rate' => $this->faker->randomFloat(2, 0.1, 10),
        ];
    }

    /**
     * Indicate that the currency is PLN.
     */
    public function pln(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => 'Polski Złoty',
            'symbol' => 'PLN',
            'exchange_rate' => 1.0,
        ]);
    }

    /**
     * Indicate that the currency is EUR.
     */
    public function eur(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => 'Euro',
            'symbol' => 'EUR',
            'exchange_rate' => 4.5,
        ]);
    }
}
