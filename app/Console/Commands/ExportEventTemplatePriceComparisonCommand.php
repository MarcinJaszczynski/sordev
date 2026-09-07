<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\EventTemplatePriceComparisonService;
use Illuminate\Console\Command;

class ExportEventTemplatePriceComparisonCommand extends Command
{
    protected $signature = 'eventtemplate:export-comparison
        {--output= : Ścieżka pliku CSV}
        {--template= : ID szablonu}
        {--start-place= : ID miejsca wyjazdu}
        {--no-stored : Pomiń zapisane local}
        {--no-calculated : Pomiń kalkulację live}
        {--prod : Dołącz produkcję}
        {--dev : Dołącz dev}
        {--all : Wszystkie wiersze, nie tylko różnice}
        {--threshold= : Próg różnicy PLN}';

    protected $description = 'Eksport porównawczy CSV (kolumny: local, kalkulacja, prod, dev)';

    public function handle(EventTemplatePriceComparisonService $service): int
    {
        $templateId = $this->option('template') ? (int) $this->option('template') : null;
        $startPlaceId = $this->option('start-place') ? (int) $this->option('start-place') : null;

        if (! $templateId && ! $startPlaceId) {
            $this->error('Podaj --template= lub --start-place= (porównanie wielu źródeł wymaga filtra).');

            return self::FAILURE;
        }

        $output = $this->option('output')
            ?: storage_path('app/exports/porownanie-cen-'.now()->format('Y-m-d_His').'.csv');

        $dir = dirname($output);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $remote = [];
        if ($this->option('prod')) {
            $remote[] = ['key' => 'prod', 'label' => 'prod', 'url' => config('price-comparison.environments.prod.base_url'), 'is_local' => false];
        }
        if ($this->option('dev')) {
            $remote[] = ['key' => 'dev', 'label' => 'dev', 'url' => config('price-comparison.environments.dev.base_url'), 'is_local' => false];
        }

        $handle = fopen($output, 'w');
        if ($handle === false) {
            $this->error('Nie można utworzyć pliku: '.$output);

            return self::FAILURE;
        }

        $rows = $service->streamComparisonCsv($handle, [
            'template_id' => $templateId,
            'start_place_id' => $startPlaceId,
            'include_stored' => ! $this->option('no-stored'),
            'include_calculated' => ! $this->option('no-calculated'),
            'only_diffs' => ! $this->option('all'),
            'threshold' => $this->option('threshold') !== null
                ? (float) $this->option('threshold')
                : (float) config('price-comparison.default_threshold', 1.0),
            'remote_environments' => $remote,
        ]);
        fclose($handle);

        $this->info("Zapisano {$rows} wierszy porównania → {$output}");

        return self::SUCCESS;
    }
}
