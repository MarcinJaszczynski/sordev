<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\EventTemplateQty;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EventTemplateQty>
 */
class EventTemplateQtyFactory extends Factory
{
    protected $model = EventTemplateQty::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $qty = $this->faker->numberBetween(10, 40);
        return [
            'qty' => $qty,
            'gratis' => (int) ceil($qty / 15),
            'staff' => 1,
            'driver' => 1,
        ];
    }
}
