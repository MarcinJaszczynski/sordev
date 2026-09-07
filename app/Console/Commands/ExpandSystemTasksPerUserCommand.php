<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Tasks\SystemTaskFactory;
use Illuminate\Console\Command;

class ExpandSystemTasksPerUserCommand extends Command
{
    protected $signature = 'tasks:expand-system-per-user';

    protected $description = 'Tworzy brakujące kopie otwartych zadań systemowych dla każdego użytkownika biura';

    public function handle(): int
    {
        $created = SystemTaskFactory::expandOpenForOfficeUsers();

        $this->info($created === 0
            ? 'Brak brakujących kopii zadań systemowych.'
            : "Utworzono {$created} kopii zadań systemowych.");

        return self::SUCCESS;
    }
}
