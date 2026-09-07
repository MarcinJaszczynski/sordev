<?php

namespace App\Console\Commands;

use App\Services\Legacy\OldSorImportService;
use Illuminate\Console\Command;

class ImportOldSorSqlCommand extends Command
{
    protected $signature = 'legacy:import-old-sql
                            {path? : Ścieżka do dumpu SQL starego SOR}
                            {--dry-run : Tylko raport bez zapisu (domyślne zachowanie gdy flaga podana; bez flagi zapisuje)}
                            {--write : Wykonaj zapis (wymagane do realnego importu)}';

    protected $description = 'Import kontrahentów i imprez archiwalnych ze starego dumpu SOR (bez duplikatów, tylko braki).';

    public function handle(OldSorImportService $importer): int
    {
        $path = $this->argument('path') ?: base_path('bazastaregsoru/host803729_bazasor.sql');
        $write = (bool) $this->option('write');
        $dryRun = ! $write || (bool) $this->option('dry-run');

        if ($write && $this->option('dry-run')) {
            $this->error('Nie łącz --write z --dry-run.');

            return self::FAILURE;
        }

        if (! is_readable($path)) {
            $this->error("Brak pliku dumpu: {$path}");

            return self::FAILURE;
        }

        $this->info($dryRun ? 'TRYB: dry-run (bez zapisu)' : 'TRYB: zapis do bazy');
        $this->line('Źródło: '.$path);
        $this->newLine();

        $started = microtime(true);
        $report = $importer->import($path, $dryRun);
        $seconds = round(microtime(true) - $started, 1);

        $this->table(
            ['Obszar', 'Akcja', 'Liczba'],
            [
                ['Typy kontrahentów', 'utworzyć', $report['contractor_types']['create']],
                ['Typy kontrahentów', 'już istnieją', $report['contractor_types']['skip']],
                ['Kontrahenci', 'utworzyć', $report['contractors']['create']],
                ['Kontrahenci', 'match legacy_id', $report['contractors']['match_legacy_id']],
                ['Kontrahenci', 'match NIP', $report['contractors']['match_nip']],
                ['Kontrahenci', 'match nazwa', $report['contractors']['match_name']],
                ['Kontrahenci', 'ustawić legacy_contractor_id', $report['contractors']['set_legacy_id']],
                ['Powiązania typów', 'dodać', $report['type_links']['attach']],
                ['Kontakty', 'utworzyć (przy nowych kontrahentach)', $report['contacts']['create']],
                ['Legacy events', 'utworzyć', $report['legacy_events']['create']],
                ['Legacy events', 'pominąć (już są)', $report['legacy_events']['skip']],
                ['Legacy events', 'podpiąć contractor_id', $report['legacy_events']['link_contractor']],
                ['Legacy events', 'uzupełnić JSON/pola', $report['legacy_events']['enrich_json']],
            ]
        );

        if ($report['legacy_events']['by_status_create'] !== []) {
            $this->newLine();
            $this->info('Nowe imprezy wg statusu:');
            $statusRows = [];
            foreach ($report['legacy_events']['by_status_create'] as $status => $count) {
                $statusRows[] = [$status, $count];
            }
            $this->table(['Status', 'Liczba'], $statusRows);
        }

        if ($report['contractors']['samples_create'] !== []) {
            $this->newLine();
            $this->info('Przykłady kontrahentów do utworzenia:');
            foreach ($report['contractors']['samples_create'] as $sample) {
                $this->line('  • '.$sample);
            }
        }

        if ($report['legacy_events']['samples_create'] !== []) {
            $this->newLine();
            $this->info('Przykłady imprez do utworzenia:');
            foreach ($report['legacy_events']['samples_create'] as $sample) {
                $this->line('  • '.$sample);
            }
        }

        $this->newLine();
        $this->info("Czas: {$seconds}s");

        if ($dryRun) {
            $this->warn('To był dry-run. Aby zapisać: php artisan legacy:import-old-sql --write');
        } else {
            $this->info('Import zapisany.');
        }

        return self::SUCCESS;
    }
}
