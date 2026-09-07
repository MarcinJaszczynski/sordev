<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Currencies\MergeDuplicateCurrenciesAction;
use Illuminate\Console\Command;

class MergeDuplicateCurrenciesCommand extends Command
{
    protected $signature = 'currencies:merge-duplicates {--dry-run : Pokaż plan bez zapisu}';

    protected $description = 'Scala powielone waluty (ten sam symbol) do najstarszego rekordu i przepisuje FK';

    public function handle(MergeDuplicateCurrenciesAction $action): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $report = $action($dryRun);

        if ($report['groups'] === []) {
            $this->info('Brak duplikatów walut.');

            return self::SUCCESS;
        }

        foreach ($report['groups'] as $group) {
            $this->line(sprintf(
                '%s → keeper #%d, usuń: %s (kursy: %s)',
                $group['symbol'],
                $group['keeper_id'],
                implode(', ', array_map(fn (int $id): string => '#'.$id, $group['duplicate_ids'])),
                implode(', ', $group['rates']),
            ));
        }

        foreach ($report['rewrites'] as $rewrite) {
            $this->line(sprintf(
                '  %s.%s: %d→%d (update=%d, konflikty unique usunięte=%d)',
                $rewrite['table'],
                $rewrite['column'],
                $rewrite['from'],
                $rewrite['to'],
                $rewrite['updated'],
                $rewrite['deleted_conflicts'],
            ));
        }

        $prefix = $dryRun ? 'Dry-run: ' : '';
        $this->info(sprintf(
            '%sgrup: %d, walut do usunięcia: %d.',
            $prefix,
            count($report['groups']),
            count($report['deleted_currency_ids']),
        ));

        if ($dryRun) {
            $this->comment('Uruchom bez --dry-run, aby zapisać zmiany.');
        }

        return self::SUCCESS;
    }
}
