<?php

namespace App\Console\Commands;

use App\Models\EventProgramPoint;
use App\Models\EventSettlementCost;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneOrphanSettlementRows extends Command
{
    protected $signature = 'app:prune-orphan-settlement-rows {--dry-run : Only report counts}';

    protected $description = 'Remove empty settlement costs pointing at deleted program points; keep rows with payments';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $orphans = EventSettlementCost::query()
            ->whereIn('source_type', ['program_point', 'program_point_payment'])
            ->whereNotNull('source_id')
            ->whereNotIn('source_id', EventProgramPoint::withTrashed()->select('id'))
            ->orderByRaw("CASE WHEN source_type = 'program_point' THEN 0 ELSE 1 END")
            ->get();

        $preserved = $orphans->filter(fn (EventSettlementCost $cost): bool => $cost->mustBePreserved());
        $prunable = $orphans->reject(fn (EventSettlementCost $cost): bool => $cost->mustBePreserved());

        $this->info('Orphan settlement rows: '.$orphans->count());
        $this->info('With payments (kept): '.$preserved->count());
        $this->info('Empty (prunable): '.$prunable->count());

        if ($dryRun) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($orphans): void {
            foreach ($orphans as $cost) {
                $cost->discardIfNotPreserved('osierocony po usunięciu punktu');
            }
        });

        $this->info('Empty orphan settlement rows pruned. Payments were kept.');

        return self::SUCCESS;
    }
}
