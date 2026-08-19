<?php

declare(strict_types=1);

namespace App\Support\Reservations;

use App\Models\EventProgramPoint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Grupa punktów z jedną rezerwacją u dostawcy: wyłącznie ten sam kontrahent w imprezie.
 * Bez kontrahenta punkt nie jest scalany (przewodnik w każdym mieście może być inny).
 */
final class ProgramPointReservationGroup
{
    /**
     * @return Collection<int, EventProgramPoint>
     */
    public static function points(EventProgramPoint $point): Collection
    {
        return self::query($point)
            ->orderBy('day')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return list<int>
     */
    public static function ids(EventProgramPoint $point): array
    {
        return self::query($point)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public static function coverageLabel(EventProgramPoint $point): ?string
    {
        if (blank($point->contractor_id)) {
            return null;
        }

        $points = self::points($point);
        if ($points->count() <= 1) {
            return null;
        }

        $point->loadMissing('contractor');
        $days = $points->pluck('day')->map(fn ($day) => (int) $day)->unique()->sort()->values();
        $name = $point->contractor?->displayLabel() ?: ('#'.(int) $point->contractor_id);

        return 'Wspólna rezerwacja u '.$name.' — dni '.$days->implode(', ');
    }

    /**
     * @return Builder<EventProgramPoint>
     */
    public static function query(EventProgramPoint $point): Builder
    {
        $query = EventProgramPoint::query()->where('event_id', $point->event_id);

        if (blank($point->contractor_id)) {
            return $query->whereKey($point->id);
        }

        return $query->where('contractor_id', $point->contractor_id);
    }

    public static function hasColumn(): bool
    {
        return Schema::hasColumn('event_program_points', 'reservation_id');
    }
}
