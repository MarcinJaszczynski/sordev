<?php

namespace App\Observers;

use App\Models\Place;
use App\Services\PlaceDistanceGenerator;

class PlaceObserver
{
    public function created(Place $place): void
    {
        app(PlaceDistanceGenerator::class)->generateForPlace($place);
    }

    public function updated(Place $place): void
    {
        // If coordinates or starting_place flag changed, fill missing pairs.
        if ($place->wasChanged(['latitude', 'longitude', 'starting_place'])) {
            app(PlaceDistanceGenerator::class)->generateForPlace($place);
        }
    }
}
