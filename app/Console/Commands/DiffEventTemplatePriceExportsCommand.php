<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\EventTemplatePriceComparisonService;
use Illuminate\Console\Command;

class DiffEventTemplatePriceExportsCommand extends Command
{
    protected $signature = 'eventtemplate:diff-exports
        {files* : Pliki CSV (min. 2), np. local.csv prod.csv dev.csv}
        {--labels= : Etykiety kolumn po przecinku (domyślnie: local, prod, dev, …)}
        {--output= : Plik wynikowy CSV}
        {--threshold=1 : Próg różnicy PLN}
        {--all : Wszystkie wiersze, nie tylko różnice}';

    protected $description = 'Scala eksporty CSV z wielu środowisk w jeden arkusz porównawczy';

    public function handle(EventTemplatePriceComparisonService $service): int
    {
        $paths = $this->argument('files');
        if (count($paths) < 2) {
            $this->error('Podaj co najmniej 2 pliki CSV.');

            return self::FAILURE;
        }

        $labels = $this->option('labels')
            ? array_map('trim', explode(',', (string) $this->option('labels')))
            : array_map(fn ($i) => match ($i) {
                0 => 'local',
                1 => 'prod',
                2 => 'dev',
                default => 'src_'.$i,
            }, array_keys($paths));

        if (count($labels) !== count($paths)) {
            $this->error('Liczba etykiet (--labels) musi odpowiadać liczbie plików.');

            return self::FAILURE;
        }

        $files = [];
        foreach ($paths as $i => $path) {
            $files[] = ['label' => $labels[$i], 'path' => $path];
        }

        $output = $this->option('output')
            ?: storage_path('app/exports/porownanie-scalone-'.now()->format('Y-m-d_His').'.csv');

        $this->info('Scalanie '.count($files).' plików → '.$output);

        $result = $service->mergePriceExportFiles(
            $files,
            $output,
            (float) $this->option('threshold'),
            ! $this->option('all'),
        );

        foreach ($result['errors'] as $error) {
            $this->warn($error);
        }

        $this->info(sprintf(
            'Gotowe: %d wierszy, kolumny cen: %s',
            $result['rows'],
            implode(', ', $result['sources']),
        ));

        return $result['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
