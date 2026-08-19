<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\Reservation;
use App\Support\Reservations\ProgramPointReservationGroup;
use Illuminate\Support\Facades\Schema;

/**
 * Jedna Reservation na grupę punktów (hotel / transport / ten sam punkt szablonu).
 * HotelStayReservationSync nadal spina noce w event_hotel_stays.
 */
class ProgramPointReservationSync
{
    public function findForPoint(EventProgramPoint $point): ?Reservation
    {
        if (ProgramPointReservationGroup::hasColumn() && filled($point->reservation_id)) {
            $linked = $point->relationLoaded('sharedReservation')
                ? $point->sharedReservation
                : Reservation::query()->find((int) $point->reservation_id);

            if ($linked instanceof Reservation && $linked->isActiveBooking()) {
                return $linked;
            }
        }

        $siblingIds = ProgramPointReservationGroup::ids($point);

        if ($siblingIds === []) {
            $siblingIds = [(int) $point->id];
        }

        if (ProgramPointReservationGroup::hasColumn()) {
            $linkedId = EventProgramPoint::query()
                ->whereIn('id', $siblingIds)
                ->whereNotNull('reservation_id')
                ->value('reservation_id');

            if ($linkedId) {
                $linked = Reservation::query()->find((int) $linkedId);
                if ($linked instanceof Reservation && $linked->isActiveBooking()) {
                    return $linked;
                }
            }
        }

        return Reservation::query()
            ->where('event_id', $point->event_id)
            ->whereIn('program_point_id', $siblingIds)
            ->whereNotIn('status', ['cancelled', 'not_required'])
            ->latest('id')
            ->first();
    }

    public function linkPoints(Reservation $reservation, ?EventProgramPoint $from = null): void
    {
        if (! ProgramPointReservationGroup::hasColumn()) {
            return;
        }

        if (! $reservation->isActiveBooking()) {
            return;
        }

        $point = $from ?? $reservation->programPoint;
        if (! $point) {
            return;
        }

        $ids = ProgramPointReservationGroup::ids($point);
        if ($ids === []) {
            $ids = [(int) $point->id];
        }

        EventProgramPoint::query()
            ->whereIn('id', $ids)
            ->update(['reservation_id' => $reservation->id]);

        EventProgramPoint::query()
            ->where('reservation_id', $reservation->id)
            ->whereNotIn('id', $ids)
            ->update(['reservation_id' => null]);

        if (filled($reservation->contractor_id)) {
            EventProgramPoint::query()
                ->whereIn('id', $ids)
                ->whereNull('contractor_id')
                ->update(['contractor_id' => $reservation->contractor_id]);
        }
    }

    public function backfillForEvent(Event $event): int
    {
        if (! ProgramPointReservationGroup::hasColumn()) {
            return 0;
        }

        $linked = 0;

        Reservation::query()
            ->where('event_id', $event->id)
            ->whereNotNull('program_point_id')
            ->whereNotIn('status', ['cancelled', 'not_required'])
            ->with('programPoint')
            ->orderBy('id')
            ->each(function (Reservation $reservation) use (&$linked): void {
                if (! $reservation->programPoint) {
                    return;
                }

                $this->linkPoints($reservation, $reservation->programPoint);
                $linked++;
            });

        if (Schema::hasColumn('event_hotel_stays', 'reservation_id')) {
            $event->loadMissing('hotelStays.programPoint');

            foreach ($event->hotelStays as $stay) {
                if (blank($stay->reservation_id) || ! $stay->programPoint) {
                    continue;
                }

                $reservation = $stay->reservation ?? Reservation::query()->find((int) $stay->reservation_id);
                if ($reservation instanceof Reservation && $reservation->isActiveBooking()) {
                    $this->linkPoints($reservation, $stay->programPoint);
                }
            }
        }

        return $linked;
    }
}
