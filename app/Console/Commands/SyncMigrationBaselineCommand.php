<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Schema;

class SyncMigrationBaselineCommand extends Command
{
    protected $signature = 'app:sync-migration-baseline';

    protected $description = 'Oznacz migracje jako uruchomione, gdy tabele/kolumny z dumpu legacy już istnieją';

    /**
     * Migracje Laravel 11 dodane do repo, których nie ma w dumpie produkcyjnym.
     *
     * @var array<string, list<string>>
     */
    private array $baselineTableChecks = [
        '0001_01_01_000000_create_users_table' => ['users'],
        '0001_01_01_000001_create_cache_table' => ['cache'],
        '0001_01_01_000002_create_jobs_table' => ['jobs'],
    ];

    public function handle(Migrator $migrator): int
    {
        if (! $migrator->repositoryExists()) {
            $this->warn('Brak tabeli migrations — uruchom najpierw import dumpu (make setup).');

            return self::FAILURE;
        }

        $ran = $migrator->getRepository()->getRan();
        $batch = max(1, (int) $migrator->getRepository()->getNextBatchNumber() - 1);
        $marked = 0;

        foreach ($this->baselineTableChecks as $migration => $tables) {
            if (in_array($migration, $ran, true)) {
                continue;
            }

            $allExist = collect($tables)->every(fn (string $table) => Schema::hasTable($table));

            if (! $allExist) {
                continue;
            }

            $migrator->getRepository()->log($migration, $batch);
            $this->line("  baseline: {$migration}");
            $marked++;
        }

        $paths = array_merge($migrator->paths(), [database_path('migrations')]);
        $files = $migrator->getMigrationFiles($paths);
        $ran = $migrator->getRepository()->getRan();

        foreach ($files as $migration => $path) {
            if (in_array($migration, $ran, true)) {
                continue;
            }

            if (isset($this->baselineTableChecks[$migration])) {
                continue;
            }

            if (! $this->shouldSkipMigration($migration)) {
                continue;
            }

            $migrator->getRepository()->log($migration, $batch);
            $this->line("  skip (już w bazie): {$migration}");
            $marked++;
        }

        if ($marked === 0) {
            $this->info('Brak migracji baseline do oznaczenia.');
        } else {
            $this->info("Oznaczono {$marked} migracji baseline (batch {$batch}).");
        }

        return self::SUCCESS;
    }

    private function shouldSkipMigration(string $migration): bool
    {
        if (preg_match('/_create_(.+)_table$/', $migration, $matches)) {
            return Schema::hasTable($matches[1]);
        }

        if (preg_match('/_add_(.+)_to_(.+)_table$/', $migration, $matches)) {
            $table = $matches[2];
            if (! Schema::hasTable($table)) {
                return false;
            }

            $columns = str_contains($matches[1], '_and_')
                ? explode('_and_', $matches[1])
                : [$matches[1]];

            return collect($columns)->every(fn (string $column) => Schema::hasColumn($table, $column));
        }

        if ($migration === '2026_06_09_100000_rename_contact_contractor_to_contractor_contact') {
            return Schema::hasTable('contractor_contact') && ! Schema::hasTable('contact_contractor');
        }

        if ($migration === '2026_05_12_140000_create_contractor_contact_table') {
            return Schema::hasTable('contractor_contact') || Schema::hasTable('contact_contractor');
        }

        if ($migration === '2026_05_12_121000_change_reservations_status_to_string') {
            if (! Schema::hasTable('reservations') || ! Schema::hasColumn('reservations', 'status')) {
                return false;
            }

            $type = Schema::getColumnType('reservations', 'status');

            return in_array($type, ['string', 'varchar'], true);
        }

        return false;
    }
}
