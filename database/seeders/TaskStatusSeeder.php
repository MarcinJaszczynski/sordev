<?php

namespace Database\Seeders;

use App\Models\TaskStatus;
use Illuminate\Database\Seeder;

class TaskStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            [
                'name' => 'Do zrobienia',
                'color' => '#gray',
                'order' => 1,
                'is_default' => true,
            ],
            [
                'name' => 'W trakcie',
                'color' => '#blue',
                'order' => 2,
                'is_default' => false,
            ],
            [
                'name' => 'Oczekuje na weryfikację',
                'color' => '#yellow',
                'order' => 3,
                'is_default' => false,
            ],
            [
                'name' => 'Zakończone',
                'color' => '#green',
                'order' => 4,
                'is_default' => false,
            ],
            [
                'name' => 'Anulowane',
                'color' => '#red',
                'order' => 5,
                'is_default' => false,
            ],
        ];

        foreach ($statuses as $status) {
            TaskStatus::firstOrCreate([
                'name' => $status['name'],
            ], $status);
        }
    }
}
