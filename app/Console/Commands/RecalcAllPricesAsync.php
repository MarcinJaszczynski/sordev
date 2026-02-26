<?php

namespace App\Console\Commands;

use App\Jobs\RecalculateAllEventTemplatePricesJob;
use Illuminate\Console\Command;

class RecalcAllPricesAsync extends Command
{
    protected $signature = 'prices:recalc-all-async {--user=1}';
    protected $description = 'Wyślij w tło: przelicz ceny dla wszystkich szablonów (deduplikacja na końcu)';

    public function handle(): int
    {
        $userId = (int) $this->option('user');
        RecalculateAllEventTemplatePricesJob::dispatch($userId);
        $this->info('Zadanie przeliczania cen dodane do kolejki.');
        return 0;
    }
}
