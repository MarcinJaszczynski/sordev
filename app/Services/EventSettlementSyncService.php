<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Models\EventSettlement;
use Illuminate\Support\Facades\Log;

/**
 * Sync aktywnego rozliczenia z danymi imprezy.
 * Model Event / EventSettlement delegują tu create + refresh kosztów.
 */
final class EventSettlementSyncService
{
    public function findOrCreateActive(Event $event): EventSettlement
    {
        return EventSettlement::findOrCreateActiveForEvent($event);
    }

    /**
     * Odświeża koszty aktywnego rozliczenia (bez tworzenia nowego, jeśli brak).
     */
    public function refreshActiveCosts(Event $event): void
    {
        $settlement = $event->settlements()
            ->whereIn('status', ['draft', 'active', 'pilot_settled'])
            ->latest('id')
            ->first();

        if (! $settlement) {
            return;
        }

        try {
            $settlement->importFromEvent();
        } catch (\Throwable $e) {
            Log::warning('EventSettlementSyncService::refreshActiveCosts failed', [
                'event_id' => $event->id,
                'settlement_id' => $settlement->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
