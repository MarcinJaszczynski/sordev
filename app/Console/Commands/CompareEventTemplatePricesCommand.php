<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\EventTemplatePriceComparisonService;
use Illuminate\Console\Command;

class CompareEventTemplatePricesCommand extends Command
{
    protected $signature = 'eventtemplate:compare-prices
        {--template= : ID szablonu (domyślnie wszystkie z cennikiem)}
        {--start-place= : ID miejsca wyjazdu}
        {--no-stored : Pomiń zapisane ceny local}
        {--no-calculated : Pomiń kalkulację live}
        {--prod : Dołącz produkcję (bprafa.pl)}
        {--dev : Dołącz dev (sor41.webgarage.pl)}
        {--all : Pokaż wszystkie wiersze, nie tylko różnice}
        {--threshold= : Próg różnicy w PLN (domyślnie z config)}
        {--export= : Zapisz wynik do pliku CSV}
        {--recalc : Przelicz i zapisz ceny dla par z różnicami (local stored → kalkulator)}';

    protected $description = 'Porównuje ceny szablonów imprez między źródłami (local stored/calc, prod, dev)';

    public function handle(EventTemplatePriceComparisonService $service): int
    {
        $templateId = $this->option('template') ? (int) $this->option('template') : null;
        $startPlaceId = $this->option('start-place') ? (int) $this->option('start-place') : null;

        $this->info('Porównywanie cen szablonów…');
        if ($templateId) {
            $this->line("  Szablon: #{$templateId}");
        }
        if ($startPlaceId) {
            $this->line("  Miejsce wyjazdu: #{$startPlaceId}");
        }

        $threshold = $this->option('threshold') !== null
            ? (float) $this->option('threshold')
            : (float) config('price-comparison.default_threshold', 1.0);

        $result = $service->runComparison([
            'template_id' => $templateId,
            'start_place_id' => $startPlaceId,
            'include_stored' => ! $this->option('no-stored'),
            'include_calculated' => ! $this->option('no-calculated'),
            'include_prod' => (bool) $this->option('prod'),
            'include_dev' => (bool) $this->option('dev'),
            'only_diffs' => ! $this->option('all'),
            'threshold' => $threshold,
        ]);

        foreach ($result['errors'] as $error) {
            $this->warn($error);
        }

        $summary = $result['summary'];
        $this->newLine();
        $this->info(sprintf(
            'Kombinacji: %d | Wierszy: %d | Różnice > progu: %d | Brakujące: %d',
            $summary['total_keys'] ?? 0,
            $summary['shown_rows'] ?? 0,
            $summary['diff_rows'] ?? 0,
            $summary['missing_rows'] ?? 0,
        ));

        $exportPath = $this->option('export');
        if ($exportPath && $result['rows'] !== []) {
            $sources = $summary['sources'] ?? [];
            $csv = $service->buildCsvContent($result['rows'], $sources, $threshold);
            file_put_contents($exportPath, $csv);
            $this->info("Zapisano CSV: {$exportPath}");
        }

        if ($this->option('recalc')) {
            if ($this->option('no-calculated')) {
                $this->error('Opcja --recalc wymaga kalkulacji live (nie używaj --no-calculated).');

                return self::FAILURE;
            }

            if ($result['rows'] === []) {
                $this->info('Brak różnic — nic do przeliczenia.');
            } else {
                $recalc = $service->recalculateStoredPricesFromRows($result['rows'], $threshold);
                $this->info(sprintf(
                    'Bulk recalc: %d/%d par przeliczonych, pominięto %d.',
                    $recalc['recalculated'],
                    $recalc['pairs'],
                    $recalc['skipped'],
                ));
                foreach ($recalc['errors'] as $error) {
                    $this->warn($error);
                }
            }
        }

        if ($result['rows'] === []) {
            $this->info('Brak różnic do wyświetlenia.');

            return self::SUCCESS;
        }

        $headers = ['Template', 'Start', 'Qty'];
        $sources = $summary['sources'] ?? [];
        foreach ($sources as $source) {
            $headers[] = ucfirst($source);
        }
        $headers[] = 'Max Δ';

        $tableRows = [];
        foreach ($result['rows'] as $row) {
            $line = [
                '#'.$row['template_id'].' '.$row['template_name'],
                $row['start_place_name'] ?: '—',
                $row['qty'],
            ];
            foreach ($sources as $source) {
                $price = $row["{$source}_price"] ?? null;
                $line[] = $price !== null ? number_format((float) $price, 0, ',', ' ').' zł' : '—';
            }
            $line[] = number_format((float) ($row['max_diff'] ?? 0), 0, ',', ' ').' zł';
            $tableRows[] = $line;
        }

        $this->table($headers, $tableRows);

        return self::SUCCESS;
    }
}
