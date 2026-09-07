<?php

namespace App\Services;

use App\Models\EventProgramPoint;
use App\Models\EventSettlementCost;
use App\Models\Reservation;
use App\Support\Reservations\ProgramPointReservationGroup;

class ProgramPointContractorSync
{
    public function sync(EventProgramPoint $point): void
    {
        if (! filled($point->contractor_id)) {
            return;
        }

        // Set nadrzędny: kontrahent = miejsce — nie ruszamy kosztów/rezerwacji (w tym podpunktów).
        if ($point->isSetParent()) {
            if (ProgramPointReservationGroup::hasColumn() && filled($point->reservation_id)) {
                $point->forceFill(['reservation_id' => null])->saveQuietly();
            }

            return;
        }

        $point->loadMissing('event');

        if (! $point->event_id) {
            return;
        }

        EventSettlementCost::query()
            ->whereIn('source_type', ['program_point', 'program_point_payment'])
            ->where('source_id', $point->id)
            ->whereHas('settlement', fn ($query) => $query->where('event_id', $point->event_id))
            ->update(['contractor_id' => $point->contractor_id]);

        Reservation::query()
            ->where(function ($query) use ($point): void {
                $query->where('program_point_id', $point->id);
                if (filled($point->reservation_id)) {
                    $query->orWhere('id', $point->reservation_id);
                }
            })
            ->update(['contractor_id' => $point->contractor_id]);
    }
}
