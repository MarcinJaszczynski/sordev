<?php

namespace App\Services;

use App\Models\EventProgramPoint;
use App\Models\EventSettlementCost;
use App\Models\Reservation;

class ProgramPointContractorSync
{
    public function sync(EventProgramPoint $point): void
    {
        if (! filled($point->contractor_id)) {
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
            ->where('program_point_id', $point->id)
            ->update(['contractor_id' => $point->contractor_id]);
    }
}
