<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventProgramPoint;
use Illuminate\Support\Collection;

class ProgramPointContractorBulkAssignService
{
    /**
     * @param  'all_days'|'same_type'|'selected_days'  $scope
     * @param  list<int>|null  $days
     */
    public function assign(
        EventProgramPoint $source,
        Event $event,
        string $scope = 'all_days',
        ?array $days = null,
    ): int {
        $contractorId = $source->contractor_id;

        if (! $contractorId) {
            return 0;
        }

        $query = EventProgramPoint::query()
            ->where('event_id', $event->id)
            ->where('id', '!=', $source->id);

        if ($scope === 'same_type') {
            $query->where('is_transport', $source->is_transport)
                ->where('is_hotel', $source->is_hotel)
                ->where('is_hotel_service', $source->is_hotel_service);
        }

        if ($scope === 'selected_days' && $days !== null) {
            $query->whereIn('day', $days);
        } elseif ($scope === 'all_days') {
            // wszystkie dni — bez dodatkowego filtra
        }

        $updated = 0;

        $query->each(function (EventProgramPoint $point) use ($contractorId, &$updated): void {
            if ((int) $point->contractor_id === (int) $contractorId) {
                return;
            }

            $point->update(['contractor_id' => $contractorId]);
            $updated++;
        });

        return $updated;
    }

    /** @return Collection<int, int> */
    public function availableDays(Event $event): Collection
    {
        return EventProgramPoint::query()
            ->where('event_id', $event->id)
            ->distinct()
            ->orderBy('day')
            ->pluck('day')
            ->map(fn ($day) => (int) $day);
    }
}
