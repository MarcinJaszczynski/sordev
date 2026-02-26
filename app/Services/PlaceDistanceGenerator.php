<?php

namespace App\Services;

use App\Models\Place;
use App\Models\PlaceDistance;

class PlaceDistanceGenerator
{
    /**
     * Generate missing distance pairs for a newly created/updated place.
     * Rule: create distances only for pairs where at least one side is a starting place
     * (start<->non-start and start<->start). Skip non-start<->non-start.
     *
     * Uses symmetric copy when available, else Haversine * factor.
     */
    public function generateForPlace(Place $place, float $factor = 1.3): void
    {
        $othersQuery = Place::query()->where('id', '!=', $place->id);

        // If new place is NOT a starting place, only pair it with starting places.
        if (! $place->starting_place) {
            $othersQuery->where('starting_place', true);
        }

        $others = $othersQuery->get();

        foreach ($others as $other) {
            // Enforce rule: at least one side must be a starting place.
            if (! $place->starting_place && ! $other->starting_place) {
                continue;
            }

            // Create both directions to keep lookups fast and consistent.
            $this->ensureDistance($place, $other, $factor);
            $this->ensureDistance($other, $place, $factor);
        }
    }

    protected function ensureDistance(Place $from, Place $to, float $factor): void
    {
        if ($from->id === $to->id) {
            return;
        }

        $existing = PlaceDistance::where('from_place_id', $from->id)
            ->where('to_place_id', $to->id)
            ->value('distance_km');

        if ($existing && (float) $existing > 0) {
            return;
        }

        // Try symmetric copy first
        $sym = PlaceDistance::where('from_place_id', $to->id)
            ->where('to_place_id', $from->id)
            ->value('distance_km');

        if ($sym && (float) $sym > 0) {
            PlaceDistance::updateOrCreate(
                ['from_place_id' => $from->id, 'to_place_id' => $to->id],
                ['distance_km' => (float) $sym, 'api_source' => 'symmetric_copy']
            );
            return;
        }

        // Compute Haversine estimate
        if (! $from->latitude || ! $from->longitude || ! $to->latitude || ! $to->longitude) {
            return;
        }

        $km = $this->haversineKm((float) $from->latitude, (float) $from->longitude, (float) $to->latitude, (float) $to->longitude);
        if ($km <= 0) {
            return;
        }

        $km = round($km * $factor, 2);

        PlaceDistance::updateOrCreate(
            ['from_place_id' => $from->id, 'to_place_id' => $to->id],
            ['distance_km' => $km, 'api_source' => 'haversine_estimate']
        );
    }

    protected function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }
}
