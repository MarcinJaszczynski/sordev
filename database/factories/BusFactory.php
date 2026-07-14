<?php

namespace Database\Factories;

use App\Models\Bus;
use Illuminate\Database\Eloquent\Factories\Factory;

class BusFactory extends Factory
{
    protected $model = Bus::class;

    public function definition(): array
    {
        return [
            'name' => 'Autokar '.$this->faker->unique()->word(),
            'description' => $this->faker->optional()->sentence(),
            'capacity' => 49,
            'package_price_per_day' => 2430,
            'package_km_per_day' => 300,
            'extra_km_price' => 8.1,
            'currency' => 'PLN',
            'convert_to_pln' => false,
        ];
    }
}
