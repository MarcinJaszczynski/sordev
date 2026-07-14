<?php

namespace App\Observers;

use App\Models\EventSettlementCost;
use App\Services\EventPaymentReminderSyncService;

class EventSettlementCostObserver
{
    /** @var list<string> */
    private const SYNC_FIELDS = [
        'advance_due_date',
        'payment_status',
        'planned_amount',
        'advance_amount',
        'paid_at',
        'actual_amount',
    ];

    public function created(EventSettlementCost $cost): void
    {
        app(EventPaymentReminderSyncService::class)->syncSettlementCost($cost);
    }

    public function updated(EventSettlementCost $cost): void
    {
        if ($cost->wasChanged(self::SYNC_FIELDS)) {
            app(EventPaymentReminderSyncService::class)->syncSettlementCost($cost);
        }
    }

    public function deleted(EventSettlementCost $cost): void
    {
        app(EventPaymentReminderSyncService::class)->syncSettlementCost($cost);
    }
}
