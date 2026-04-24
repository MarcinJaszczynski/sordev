<?php

namespace App\Observers;

use App\Models\Event;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;

class EventObserver
{
    /**
     * Pola imprezy, których zmiana wymaga przeliczenia kosztu całkowitego i odświeżenia rozliczenia.
     */
    private static array $costFields = [
        'participant_count',
        'start_place_id',
        'transfer_km',
        'program_km',
        'bus_id',
        'markup_id',
        'duration_days',
        'event_template_id',
    ];

    public function updated(Event $event): void
    {
        $changed = array_keys($event->getChanges());

        // Przelicz bazowy koszt i odśwież rozliczenie tylko gdy zmieniono pole wpływające na cenę.
        // Wyłączamy total_cost i updated_at z listy istotnych pól, by nie wejść w pętlę:
        // calculateTotalCost() → update(total_cost) → observer::updated() → calculateTotalCost()...
        $costRelevantChanged = ! empty(array_intersect($changed, self::$costFields));
        $totalCostManuallyChanged = in_array('total_cost', $changed, true);

        if ($costRelevantChanged && ! $totalCostManuallyChanged) {
            try {
                $event->calculateTotalCost();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('EventObserver: calculateTotalCost failed', [
                    'event_id' => $event->id,
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                $event->refreshActiveSettlementCosts();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('EventObserver: refreshActiveSettlementCosts failed', [
                    'event_id' => $event->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->clearAffectedUsersCache($event);
    }

    public function created(Event $event): void
    {
        $this->clearAffectedUsersCache($event);
    }

    public function deleted(Event $event): void
    {
        $this->clearAffectedUsersCache($event);
    }

    private function clearAffectedUsersCache(Event $event): void
    {
        $userIds = collect([
            Auth::id(),
            $event->assigned_to,
            $event->getOriginal('assigned_to'),
            $event->created_by,
        ])
            ->filter(fn ($id): bool => ! empty($id))
            ->map(fn ($id): int => (int) $id)
            ->merge(
                User::query()
                    ->whereHas('roles', function ($query) {
                        $query->whereIn('name', ['super_admin', 'admin']);
                    })
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
            )
            ->unique()
            ->values();

        foreach ($userIds as $userId) {
            NotificationService::clearCacheForUser($userId);
        }
    }
}
