<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Catalog\ProdSqliteCatalogSync;
use Illuminate\Console\Command;
use Throwable;

class SyncCatalogFromProdSqliteCommand extends Command
{
    protected $signature = 'catalog:sync-from-prod-sqlite
        {path? : Ścieżka do database.sqlite z produkcji}
        {--dry-run : Tylko raport, bez zapisu}
        {--skip-prices : Pomiń synchronizację event_template_price_per_person}';

    protected $description = 'Synchronizuje katalog szablonów, punktów programu i cenników z SQLite produkcji do bieżącej bazy';

    public function handle(): int
    {
        $path = $this->argument('path') ?: base_path('bazazserweraprod/database.sqlite');
        $dryRun = (bool) $this->option('dry-run');
        $skipPrices = (bool) $this->option('skip-prices');

        if (! is_file($path)) {
            $this->error("Nie znaleziono pliku: {$path}");

            return self::FAILURE;
        }

        $this->info(($dryRun ? '[DRY-RUN] ' : '').'Źródło: '.$path);
        $this->info('Cel: '.config('database.default').' / '.config('database.connections.'.config('database.default').'.database'));

        if (! $dryRun) {
            $this->warn('Zapisze zmiany w transakcji (FK checks wyłączone na czas sync).');
        }

        try {
            $result = (new ProdSqliteCatalogSync($path, $dryRun, $skipPrices))->run();
        } catch (Throwable $e) {
            $this->error('Sync nieudany: '.$e->getMessage());
            $this->line($e->getFile().':'.$e->getLine());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Statystyki:');
        ksort($result['stats']);
        foreach ($result['stats'] as $key => $value) {
            $this->line(sprintf('  %-55s %s', $key, number_format((int) $value, 0, ',', ' ')));
        }

        if ($result['warnings'] !== []) {
            $this->newLine();
            $this->warn('Ostrzeżenia:');
            foreach ($result['warnings'] as $warning) {
                $this->line('  - '.$warning);
            }
        }

        $this->newLine();
        $this->info($dryRun ? 'Dry-run zakończony — nic nie zapisano.' : 'Sync zakończony pomyślnie.');

        return self::SUCCESS;
    }
}
