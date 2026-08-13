<?php

namespace App\Console\Commands;

use App\Services\EventPaymentReminderSyncService;
use Illuminate\Console\Command;

class RetireLegacyPaymentReminderTasksCommand extends Command
{
    protected $signature = 'tasks:retire-legacy-payment-reminders
                            {--no-resync : Tylko zamknij legacy taski, bez tworzenia zbiorczych}';

    protected $description = 'Zamyka zadania 1:1 per rata kontraktu/umowy i tworzy zbiorcze przypomnienia per impreza + etykieta';

    public function handle(EventPaymentReminderSyncService $sync): int
    {
        $resync = ! (bool) $this->option('no-resync');

        $result = $sync->retireLegacyScheduleRemindersAndResync($resync);

        $this->info('Zamknięto legacy zadań: '.$result['retired_legacy']);

        if ($resync) {
            $this->info('Zsynchronizowano imprez: '.$result['events_synced']);
        } else {
            $this->comment('Pominięto re-sync (--no-resync).');
        }

        return self::SUCCESS;
    }
}
