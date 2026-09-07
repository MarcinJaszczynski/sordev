<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\EventTemplatePriceComparisonService;
use Illuminate\Console\Command;

class ExportEventTemplatePricesCommand extends Command
{
    protected $signature = 'eventtemplate:export-prices
        {--output= : Ścieżka pliku CSV (domyślnie storage/app/exports/cennik-{data}.csv)}
        {--template= : Tylko jeden szablon}
        {--start-place= : Tylko jedno miejsce wyjazdu}
        {--include-inactive : Uwzględnij nieaktywne szablony}
        {--from= : Źródło: local (domyślnie), prod, dev lub pełny URL}';

    protected $description = 'Eksportuje zapisany cennik PLN do CSV (cała oferta lub z filtrem)';

    public function handle(EventTemplatePriceComparisonService $service): int
    {
        $filters = [
            'template_id' => $this->option('template') ? (int) $this->option('template') : null,
            'start_place_id' => $this->option('start-place') ? (int) $this->option('start-place') : null,
            'only_active' => ! $this->option('include-inactive'),
        ];

        $output = $this->option('output')
            ?: storage_path('app/exports/cennik-'.now()->format('Y-m-d_His').'.csv');

        $dir = dirname($output);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $from = (string) ($this->option('from') ?: 'local');
        $sourceLabel = match ($from) {
            'local' => 'local',
            'prod' => 'prod',
            'dev' => 'dev',
            default => 'remote',
        };

        $this->info('Eksport cennika do: '.$output);
        $this->line('  Źródło: '.$sourceLabel);

        if ($from === 'local') {
            $estimated = $service->countStoredPrices($filters);
            $this->line('  Wierszy (szacunkowo): '.number_format($estimated, 0, ',', ' '));

            $handle = fopen($output, 'w');
            if ($handle === false) {
                $this->error('Nie można utworzyć pliku: '.$output);

                return self::FAILURE;
            }

            $rows = $service->streamStoredPricesCsv($handle, $filters);
            fclose($handle);

            $this->info("Zapisano {$rows} wierszy.");

            return self::SUCCESS;
        }

        $url = match ($from) {
            'prod' => config('price-comparison.environments.prod.base_url'),
            'dev' => config('price-comparison.environments.dev.base_url'),
            default => rtrim($from, '/'),
        };

        $this->line('  Źródło zdalne: '.$url);

        $handle = fopen($output, 'w');
        if ($handle === false) {
            $this->error('Nie można utworzyć pliku: '.$output);

            return self::FAILURE;
        }

        $result = $service->streamRemoteStoredPricesCsv($handle, $url, config('price-comparison.token'), $filters, $from);
        fclose($handle);

        if ($result === null) {
            $this->error('Brak PRICE_COMPARE_TOKEN lub błąd połączenia.');

            return self::FAILURE;
        }

        foreach ($result['errors'] as $error) {
            $this->warn($error);
        }

        $this->info("Zapisano {$result['rows']} wierszy.");

        return $result['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
