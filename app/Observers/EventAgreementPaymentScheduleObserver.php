<?php

namespace App\Observers;

use App\Models\EventAgreementPaymentSchedule;
use App\Services\EventPaymentReminderSyncService;

class EventAgreementPaymentScheduleObserver
{
    public function created(EventAgreementPaymentSchedule $schedule): void
    {
        app(EventPaymentReminderSyncService::class)->syncAgreementSchedule($schedule);
    }

    public function updated(EventAgreementPaymentSchedule $schedule): void
    {
        if ($schedule->wasChanged(['due_date', 'amount', 'label'])) {
            app(EventPaymentReminderSyncService::class)->syncAgreementSchedule($schedule);
        }
    }

    public function deleted(EventAgreementPaymentSchedule $schedule): void
    {
        app(EventPaymentReminderSyncService::class)->syncAgreementSchedule($schedule);
    }
}
