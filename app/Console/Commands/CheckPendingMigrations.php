<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;

class CheckPendingMigrations extends Command
{
    protected $signature = 'app:migrate-check {--fail : Zakończ kodem błędu gdy są oczekujące migracje}';

    protected $description = 'Sprawdź, czy są nieuruchomione migracje (uruchom po git pull na dev z MySQL)';

    public function handle(Migrator $migrator): int
    {
        if (! $migrator->repositoryExists()) {
            $this->warn('Brak tabeli migrations — uruchom: php artisan migrate');

            return $this->option('fail') ? self::FAILURE : self::SUCCESS;
        }

        $files = $migrator->getMigrationFiles($migrator->paths());
        $ran = $migrator->getRepository()->getRan();
        $pending = array_values(array_diff(array_keys($files), $ran));

        if ($pending === []) {
            $this->info('Wszystkie migracje są uruchomione.');

            return self::SUCCESS;
        }

        $this->warn('Oczekujące migracje ('.count($pending).'):');
        foreach ($pending as $migration) {
            $this->line('  - '.$migration);
        }
        $this->line('');
        $this->line('Uruchom: php artisan migrate');

        return $this->option('fail') ? self::FAILURE : self::SUCCESS;
    }
}
