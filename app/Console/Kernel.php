<?php

namespace App\Console;

use App\Jobs\RecalculateAllEventTemplatePricesJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Codzienne przeliczanie w tle o 02:15 (opcjonalne)
        $schedule->call(function () {
            $userId = 1; // domyślny użytkownik do powiadomień
            RecalculateAllEventTemplatePricesJob::dispatch($userId);
        })->dailyAt('02:15');

        // Generowanie sitemapy codziennie o 3:00 AM
        $schedule->command('sitemap:generate')
            ->dailyAt('03:00')
            ->withoutOverlapping()
            ->onOneServer();

        $schedule->command('app:prune-orphan-settlement-rows')
            ->weeklyOn(1, '04:30')
            ->withoutOverlapping()
            ->onOneServer();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
