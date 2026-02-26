<?php

namespace App\Console\Commands;

use App\Jobs\RecalculateAllEventTemplatePricesJob;
use Illuminate\Console\Command;

class RecalcAllPricesNow extends Command
{
    protected $signature = 'prices:recalc-all {--user=1}';
    protected $description = 'Dedupikuj i przelicz ceny dla wszystkich szablonów teraz (sync)';

    public function handle(): int
    {
        $userId = (int) $this->option('user');
        $this->info('Start: deduplikacja + przeliczenie wszystkich cen (synchronnie)...');
        try {
            // Wewnątrz joba jest deduplikacja po zakończeniu
            RecalculateAllEventTemplatePricesJob::dispatchSync($userId);
            $this->info('Zakończone. Sprawdź powiadomienia w aplikacji po szczegółach.');
            return 0;
        } catch (\Throwable $e) {
            $this->error('Błąd: ' . $e->getMessage());
            return 1;
        }
    }
}
