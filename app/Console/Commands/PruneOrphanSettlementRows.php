<?php

namespace App\Console\Commands;

use App\Models\EventProgramPoint;
use App\Models\EventSettlementCost;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneOrphanSettlementRows extends Command
{
    protected $signature = 'app:prune-orphan-settlement-rows {--dry-run : Only report counts}';

    protected $description = 'Remove settlement costs pointing at deleted program points or settlements';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $orphanProgramCosts = EventSettlementCost::query()
            ->where('source_type', 'program_point')
            ->whereNotNull('source_id')
            ->whereNotIn('source_id', EventProgramPoint::withTrashed()->select('id'))
            ->count();

        $orphanPayments = EventSettlementCost::query()
            ->where('source_type', 'program_point_payment')
            ->whereNotNull('source_id')
            ->whereNotIn('source_id', EventProgramPoint::withTrashed()->select('id'))
            ->count();

        $this->info("Orphan program_point costs: {$orphanProgramCosts}");
        $this->info("Orphan program_point_payment rows: {$orphanPayments}");

        if ($dryRun) {
            return self::SUCCESS;
        }

        DB::transaction(function (): void {
            EventSettlementCost::query()
                ->whereIn('source_type', ['program_point', 'program_point_payment'])
                ->whereNotNull('source_id')
                ->whereNotIn('source_id', EventProgramPoint::withTrashed()->select('id'))
                ->delete();
        });

        $this->info('Orphan settlement rows pruned.');

        return self::SUCCESS;
    }
}
