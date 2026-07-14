<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Services\Contracts\ContractNumberAllocator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class BackfillContractOperationalNumbersCommand extends Command
{
    protected $signature = 'contracts:backfill-operational-numbers {--dry-run : Pokaż zmiany bez zapisu}';

    protected $description = 'Uzupełnia numery operacyjne umów (kod imprezy + U001)';

    public function handle(ContractNumberAllocator $allocator): int
    {
        if (! Schema::hasTable('contracts') || ! Schema::hasColumn('contracts', 'operational_number')) {
            $this->error('Brak kolumny operational_number — uruchom migracje.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;

        Contract::query()
            ->with('event')
            ->whereNull('operational_number')
            ->orderBy('event_id')
            ->orderBy('id')
            ->chunkById(100, function ($contracts) use ($allocator, $dryRun, &$updated): void {
                foreach ($contracts as $contract) {
                    $number = $allocator->allocate($contract);

                    $this->line(sprintf(
                        'Contract #%d (%s) → %s',
                        $contract->id,
                        $contract->contract_type,
                        $number,
                    ));

                    if (! $dryRun) {
                        $contract->forceFill(['operational_number' => $number])->saveQuietly();
                    }

                    $updated++;
                }
            });

        $this->info($dryRun
            ? "Dry-run: {$updated} umów do uzupełnienia."
            : "Zaktualizowano {$updated} umów.");

        return self::SUCCESS;
    }
}
