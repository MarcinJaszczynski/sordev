<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventProgramPoint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ProgramPointContractorBulkAssignService
{
    /**
     * @param  'same_point'|'all_days'|'same_type'|'selected_days'  $scope
     * @param  list<int>|null  $days
     */
    public function assign(
        EventProgramPoint $source,
        Event $event,
        string $scope = 'same_point',
        ?array $days = null,
    ): int {
        $contractorId = $source->contractor_id;

        if (! $contractorId) {
            return 0;
        }

        $query = EventProgramPoint::query()
            ->where('event_id', $event->id)
            ->where('id', '!=', $source->id);

        // Domyślnie / selected_days: tylko „ten sam punkt” w innych dniach.
        if ($scope === 'same_point' || $scope === 'selected_days') {
            $this->constrainToSamePoint($query, $source);
        }

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

    /**
     * Identyczny punkt: ten sam template point, albo nazwa + flagi hotel/transport.
     *
     * @param  Builder<EventProgramPoint>  $query
     */
    private function constrainToSamePoint(Builder $query, EventProgramPoint $source): void
    {
        $templatePointId = $source->event_template_program_point_id;

        if (filled($templatePointId)) {
            $query->where('event_template_program_point_id', $templatePointId);

            return;
        }

        $query->where('name', $source->name)
            ->where('is_transport', $source->is_transport)
            ->where('is_hotel', $source->is_hotel)
            ->where('is_hotel_service', $source->is_hotel_service);
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
