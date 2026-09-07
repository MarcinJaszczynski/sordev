<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\EventProgramPoint;
use App\Models\Reservation;
use Illuminate\Support\Collection;

/**
 * Kompaktowy podgląd rezerwacji w programie portalu pilota (status + godzina punktu).
 */
final class PilotProgramReservationDisplay
{
    /**
     * @return list<array{
     *     status: string,
     *     status_label: string,
     *     badge_classes: string,
     *     time: string|null,
     *     reference: string|null
     * }>
     */
    public static function linesForPoint(EventProgramPoint $point): array
    {
        $point->loadMissing(['reservations', 'children.reservations']);

        $reservations = self::reservationsForPoint($point);

        $start = $point->displayStartTime();

        return $reservations
            ->reject(fn (Reservation $reservation): bool => $reservation->status === 'not_required')
            ->sortBy(fn (Reservation $reservation): string => (string) ($reservation->reserved_at?->timestamp ?? $reservation->id))
            ->values()
            ->map(function (Reservation $reservation) use ($start): array {
                $status = (string) $reservation->status;

                return [
                    'status' => $status,
                    'status_label' => Reservation::$statuses[$status] ?? $status,
                    'badge_classes' => self::badgeClasses($status),
                    'time' => $start,
                    'reference' => filled($reservation->booking_reference)
                        ? (string) $reservation->booking_reference
                        : null,
                ];
            })
            ->all();
    }

    /**
     * Rezerwacje punktu; dla setu (rodzica) — także z podpunktów, gdy rodzic nie ma własnych.
     *
     * @return Collection<int, Reservation>
     */
    private static function reservationsForPoint(EventProgramPoint $point): Collection
    {
        $own = $point->reservations ?? collect();
        if ($own->isNotEmpty()) {
            return $own;
        }

        $children = $point->children ?? collect();
        if ($children->isEmpty()) {
            return collect();
        }

        return $children
            ->flatMap(fn (EventProgramPoint $child) => $child->reservations ?? collect())
            ->unique('id')
            ->values();
    }

    private static function badgeClasses(string $status): string
    {
        return match ($status) {
            'confirmed', 'completed' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
            'partially_confirmed' => 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200',
            'pending' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
            'cancelled' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-200',
            default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
        };
    }
}
