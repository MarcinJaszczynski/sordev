<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('+1 month', '+6 months');
        $end = (clone $start)->modify('+'.rand(2, 7).' days');

        return [
            'name' => $this->faker->words(4, true),
            'client_name' => $this->faker->company(),
            'client_email' => $this->faker->safeEmail(),
            'client_phone' => $this->faker->phoneNumber(),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'duration_days' => $start->diff($end)->days + 1,
            'participant_count' => $this->faker->numberBetween(10, 50),
            'total_cost' => $this->faker->randomFloat(2, 5000, 50000),
            'status' => Event::STATUS_INQUIRY,
            'created_by' => User::factory(),
            'transfer_km' => 0,
            'program_km' => 0,
        ];
    }

    public function withBus(?\App\Models\Bus $bus = null): static
    {
        return $this->state(fn () => [
            'bus_id' => ($bus ?? \App\Models\Bus::factory()->create())->id,
        ]);
    }
}
