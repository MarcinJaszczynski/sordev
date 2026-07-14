<?php

namespace App\Observers;

use App\Models\ContractPaymentSchedule;
use App\Services\EventPaymentReminderSyncService;

class ContractPaymentScheduleObserver
{
    public function created(ContractPaymentSchedule $schedule): void
    {
        app(EventPaymentReminderSyncService::class)->syncContractSchedule($schedule);
    }

    public function updated(ContractPaymentSchedule $schedule): void
    {
        if ($schedule->wasChanged(['due_date', 'amount', 'label'])) {
            app(EventPaymentReminderSyncService::class)->syncContractSchedule($schedule);
        }
    }

    public function deleted(ContractPaymentSchedule $schedule): void
    {
        app(EventPaymentReminderSyncService::class)->syncContractSchedule($schedule);
    }
}
