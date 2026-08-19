<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\TaskStatus;
use App\Support\Tasks\SystemTaskPolicy;
use Illuminate\Console\Command;

class RetireDisallowedSystemTasksCommand extends Command
{
    protected $signature = 'tasks:retire-disallowed-system
                            {--dry-run : Policz bez zamykania}';

    protected $description = 'Zamyka otwarte zadania systemowe poza allowlistą (zapytanie, liczba uczestników, potwierdzenie imprezy)';

    public function handle(): int
    {
        $completedStatusId = TaskStatus::query()->where('name', 'Zakończone')->value('id');

        if (! $completedStatusId) {
            $this->error('Brak statusu „Zakończone”.');

            return self::FAILURE;
        }

        $query = Task::query();
        SystemTaskPolicy::constrainDisallowedOpenSystem($query);

        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("Do zamknięcia: {$count} (dry-run).");

            return self::SUCCESS;
        }

        $updated = $query->update(['status_id' => $completedStatusId]);

        $this->info("Zamknięto zadań systemowych poza allowlistą: {$updated}");

        return self::SUCCESS;
    }
}
