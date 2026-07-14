<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(4),
            'priority' => $this->faker->randomElement(['normal', 'urgent']),
            'order' => 0,
            'status_id' => TaskStatus::factory(),
            'author_id' => User::factory(),
        ];
    }
}
