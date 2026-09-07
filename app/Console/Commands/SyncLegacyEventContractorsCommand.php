<?php

namespace App\Console\Commands;

use App\Services\Legacy\LegacyEventContractorSync;
use Illuminate\Console\Command;

class SyncLegacyEventContractorsCommand extends Command
{
    protected $signature = 'legacy:sync-event-contractors';

    protected $description = 'Buduje pivot udziału kontrahentów w imprezach archiwalnych (klient + wykonawcy).';

    public function handle(LegacyEventContractorSync $sync): int
    {
        $this->info('Synchronizacja contractor_legacy_event…');
        $bar = null;

        $result = $sync->syncAll(function (int $done) use (&$bar): void {
            if ($bar === null) {
                return;
            }
            $bar->setProgress($done);
        });

        $this->table(
            ['Metryka', 'Wartość'],
            [
                ['Imprezy', $result['synced_events']],
                ['Powiązania', $result['links']],
            ]
        );

        return self::SUCCESS;
    }
}
