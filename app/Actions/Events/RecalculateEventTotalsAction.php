<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Data\RecalculateEventTotalsData;
use App\Services\EventCostCalculator;
use Illuminate\Support\Facades\DB;

/**
 * Deterministyczne przeliczenie kosztu bazowego (bez narzutu).
 * Źródło prawdy: EventCostCalculator (program + transport + ubezpieczenie, każdy koszt raz).
 */
final class RecalculateEventTotalsAction
{
    public function __invoke(RecalculateEventTotalsData $data): float
    {
        if ($data->startPlaceId !== null) {
            $data->event->start_place_id = $data->startPlaceId;
        }

        if ($data->participantCount !== null) {
            $data->event->participant_count = $data->participantCount;
        }

        try {
            $result = EventCostCalculator::for($data->event)->calculate(
                $data->participantCount,
                $data->gratisCount,
                $data->staffCount,
                $data->driverCount,
            );
            $total = round((float) ($result['base_pln'] ?? 0), 2);
        } catch (\Throwable) {
            $total = $data->event->resolvedBaseTotalCost(
                $data->participantCount,
                $data->gratisCount,
                $data->startPlaceId,
            );
        }

        if (! $data->persist) {
            return $total;
        }

        return DB::transaction(function () use ($data, $total): float {
            $data->event->update(['total_cost' => $total]);

            return $total;
        });
    }
}
