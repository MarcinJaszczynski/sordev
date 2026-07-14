<?php

namespace App\Console\Commands;

use App\Support\ConsoleAuditUrlCollector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ConsoleAuditUrlsCommand extends Command
{
    protected $signature = 'app:console-audit-urls
                            {--group= : Filtruj grupę: events, finance, executive, contacts, dictionaries, system, pilot}
                            {--output= : Ścieżka pliku JSON (domyślnie storage/console-audit/urls.json)}';

    protected $description = 'Generuj listę URL paneli admin/pilot do audytu błędów konsoli';

    public function handle(ConsoleAuditUrlCollector $collector): int
    {
        $group = $this->option('group');
        $outputPath = $this->option('output') ?: storage_path('console-audit/urls.json');

        $result = $collector->collect(is_string($group) && $group !== '' ? $group : null);

        File::ensureDirectoryExists(dirname($outputPath));

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'group_filter' => $group ?: null,
            'urls' => $result['urls'],
            'skipped' => $result['skipped'],
        ];

        File::put($outputPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

        $this->info('Zapisano '.count($result['urls']).' URL do: '.$outputPath);

        if ($result['skipped'] !== []) {
            $this->warn('Pominięto '.count($result['skipped']).' pozycji:');
            foreach ($result['skipped'] as $skip) {
                $this->line('  - '.$skip['label'].': '.$skip['reason']);
            }
        }

        return self::SUCCESS;
    }
}
