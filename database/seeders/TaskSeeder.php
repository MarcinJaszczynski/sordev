<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;

class TaskSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();
        $status = TaskStatus::first();
        
        if (!$user || !$status) {
            return; // Nie można utworzyć zadań bez użytkowników i statusów
        }

        $tasks = [
            [
                'title' => 'Przygotowanie oferty dla klienta ABC',
                'description' => 'Sporządzenie szczegółowej oferty na wyjazd integracyjny dla 30 osób',
                'due_date' => now()->addDays(3),
                'status_id' => $status->id,
                'priority' => 'high',
                'author_id' => $user->id,
                'assignee_id' => $user->id,
                'order' => 1,
            ],
            [
                'title' => 'Rezerwacja hotelu na weekend firmowy',
                'description' => 'Znalezienie i zarezerwowanie odpowiedniego hotelu na 25-26 maja',
                'due_date' => now()->addDays(5),
                'status_id' => $status->id,
                'priority' => 'medium',
                'author_id' => $user->id,
                'assignee_id' => $user->id,
                'order' => 2,
            ],
            [
                'title' => 'Kontakt z przewoźnikiem',
                'description' => 'Ustalenie szczegółów transportu i potwierdzenie godzin odjazdów',
                'due_date' => now()->addDays(1),
                'status_id' => $status->id,
                'priority' => 'high',
                'author_id' => $user->id,
                'assignee_id' => $user->id,
                'order' => 3,
            ],
        ];

        foreach ($tasks as $task) {
            Task::firstOrCreate([
                'title' => $task['title'],
            ], $task);
        }
    }
}
