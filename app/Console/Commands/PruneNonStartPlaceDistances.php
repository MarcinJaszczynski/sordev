<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneNonStartPlaceDistances extends Command
{
    protected $signature = 'db:prune-non-start-place-distances
        {--dry : Tylko policz i pokaż, bez usuwania}
        {--force : Wymuś usunięcie bez potwierdzenia}
        {--chunk=1000 : Ile rekordów usuwać w jednej paczce (SQLite-safe)}';

    protected $description = 'Usuwa rekordy place_distances dla par non-start↔non-start (gdy oba miejsca nie są startowe)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry');
        $force = (bool) $this->option('force');
        $chunkSize = max(1, (int) $this->option('chunk'));

        $baseQuery = DB::table('place_distances as pd')
            ->join('places as p_from', 'p_from.id', '=', 'pd.from_place_id')
            ->join('places as p_to', 'p_to.id', '=', 'pd.to_place_id')
            ->where('p_from.starting_place', false)
            ->where('p_to.starting_place', false);

        $count = (clone $baseQuery)->count();

        if ($count === 0) {
            $this->info('Brak par non-start↔non-start do usunięcia.');
            return 0;
        }

        $this->info("Znaleziono {$count} rekordów non-start↔non-start w place_distances.");

        if ($dryRun) {
            $exampleIds = (clone $baseQuery)->select('pd.id')->limit(10)->pluck('id')->all();
            if (!empty($exampleIds)) {
                $this->line('Przykładowe ID: ' . implode(', ', $exampleIds));
            }
            $this->info('Tryb --dry: nic nie usunięto.');
            return 0;
        }

        if (!$force) {
            if (!$this->confirm('Czy na pewno chcesz usunąć te rekordy?')) {
                $this->info('Operacja anulowana.');
                return 0;
            }
        }

        $deletedTotal = 0;

        while (true) {
            $ids = (clone $baseQuery)
                ->select('pd.id')
                ->limit($chunkSize)
                ->pluck('id')
                ->all();

            if (empty($ids)) {
                break;
            }

            $deleted = DB::table('place_distances')->whereIn('id', $ids)->delete();
            $deletedTotal += $deleted;

            $this->line("Usunięto {$deleted} (łącznie {$deletedTotal}/{$count})...");

            if ($deleted <= 0) {
                break;
            }
        }

        $this->info("✅ Usunięto {$deletedTotal} rekordów non-start↔non-start.");
        return 0;
    }
}
