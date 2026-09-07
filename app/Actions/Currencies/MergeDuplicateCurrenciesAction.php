<?php

declare(strict_types=1);

namespace App\Actions\Currencies;

use App\Models\Currency;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Scala powielone waluty (ten sam symbol) do najstarszego rekordu.
 * Przepisuje FK, usuwa konflikty unique, kasuje duplikaty.
 */
final class MergeDuplicateCurrenciesAction
{
    /**
     * @return array{
     *     dry_run: bool,
     *     groups: list<array{symbol: string, keeper_id: int, duplicate_ids: list<int>, rates: list<string>}>,
     *     rewrites: list<array{table: string, column: string, from: int, to: int, updated: int, deleted_conflicts: int}>,
     *     deleted_currency_ids: list<int>
     * }
     */
    public function __invoke(bool $dryRun = true): array
    {
        $groups = $this->duplicateGroups();

        $report = [
            'dry_run' => $dryRun,
            'groups' => [],
            'rewrites' => [],
            'deleted_currency_ids' => [],
        ];

        if ($groups->isEmpty()) {
            return $report;
        }

        $columns = $this->currencyForeignColumns();

        $run = function () use ($groups, $columns, $dryRun, &$report): void {
            foreach ($groups as $symbol => $currencies) {
                /** @var Collection<int, Currency> $currencies */
                $keeper = $currencies->sortBy('id')->first();
                $duplicates = $currencies->where('id', '!=', $keeper->id)->values();
                $duplicateIds = $duplicates->pluck('id')->map(fn ($id): int => (int) $id)->all();

                $report['groups'][] = [
                    'symbol' => (string) $symbol,
                    'keeper_id' => (int) $keeper->id,
                    'duplicate_ids' => $duplicateIds,
                    'rates' => $currencies
                        ->sortBy('id')
                        ->map(fn (Currency $c): string => "#{$c->id}={$c->exchange_rate}")
                        ->values()
                        ->all(),
                ];

                foreach ($columns as [$table, $column]) {
                    foreach ($duplicateIds as $fromId) {
                        $rows = DB::table($table)->where($column, $fromId)->pluck('id');
                        $updated = 0;
                        $deletedConflicts = 0;

                        foreach ($rows as $rowId) {
                            if ($dryRun) {
                                $updated++;

                                continue;
                            }

                            try {
                                DB::table($table)->where('id', $rowId)->update([$column => $keeper->id]);
                                $updated++;
                            } catch (UniqueConstraintViolationException) {
                                DB::table($table)->where('id', $rowId)->delete();
                                $deletedConflicts++;
                            }
                        }

                        if ($updated > 0 || $deletedConflicts > 0) {
                            $report['rewrites'][] = [
                                'table' => $table,
                                'column' => $column,
                                'from' => $fromId,
                                'to' => (int) $keeper->id,
                                'updated' => $updated,
                                'deleted_conflicts' => $deletedConflicts,
                            ];
                        }
                    }
                }

                if (! $dryRun) {
                    Currency::query()->whereIn('id', $duplicateIds)->delete();
                }

                $report['deleted_currency_ids'] = array_merge(
                    $report['deleted_currency_ids'],
                    $duplicateIds,
                );
            }

            if (! $dryRun) {
                Currency::clearPlnIdsCache();
            }
        };

        if ($dryRun) {
            $run();
        } else {
            DB::transaction($run);
        }

        return $report;
    }

    /**
     * @return Collection<string, Collection<int, Currency>>
     */
    private function duplicateGroups(): Collection
    {
        return Currency::query()
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Currency $currency): string => strtoupper(trim((string) $currency->symbol)))
            ->filter(fn (Collection $group): bool => $group->count() > 1);
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function currencyForeignColumns(): array
    {
        $result = [];

        foreach (Schema::getTableListing() as $table) {
            if ($table === 'currencies') {
                continue;
            }

            foreach (Schema::getColumnListing($table) as $column) {
                if ($column === 'currency_id' || str_ends_with($column, '_currency_id')) {
                    $result[] = [$table, $column];
                }
            }
        }

        return $result;
    }
}
