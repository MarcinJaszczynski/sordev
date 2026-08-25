<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\User;
use App\Notifications\EventMarginDiscrepancyNotification;
use App\Services\EventCalculationPresenter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class NotifyEventMarginDiscrepancies extends Command
{
    protected $signature = 'app:notify-margin-discrepancies {--threshold=5 : Próg różnicy w procentach}';

    protected $description = 'Powiadom administratorów o imprezach z rozbieżnością szablon vs planowane';

    public function handle(): int
    {
        $threshold = (float) $this->option('threshold');
        $admins = User::role(['admin', 'super_admin'])->get();

        if ($admins->isEmpty()) {
            $this->warn('Brak użytkowników z rolą admin.');

            return self::SUCCESS;
        }

        $notified = 0;

        Event::query()
            ->whereHas('activeSettlement')
            ->with('activeSettlement')
            ->chunkById(50, function ($events) use ($admins, $threshold, &$notified) {
                foreach ($events as $event) {
                    $presenter = EventCalculationPresenter::for($event);
                    if (! $presenter->hasSignificantDiscrepancy($threshold)) {
                        continue;
                    }

                    Notification::send($admins, new EventMarginDiscrepancyNotification(
                        $event,
                        $presenter->plannedTotalPln(),
                        $presenter->settlementPlannedPln(),
                        $presenter->marginDeltaPercent(),
                    ));
                    $notified++;
                }
            });

        $this->info("Wysłano powiadomienia dla {$notified} imprez.");

        return self::SUCCESS;
    }
}
