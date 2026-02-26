<?php

namespace App\Support;

use App\Models\Place;
use Illuminate\Support\Str;

class Region
{
    /**
     * Determine region slug for links.
     * Priority:
     * 1. Explicit $startPlaceId parameter
     * 2. Cookie 'start_place_id'
     * 3. Place with slug 'warszawa' or name 'Warszawa'
     * 4. Fallback literal 'warszawa'
     *
     * @param int|null $startPlaceId
     * @return string
     */
    public static function slugForLinks(?int $startPlaceId = null): string
    {
        if ($startPlaceId) {
            $place = Place::find($startPlaceId);
            if ($place && $place->name) {
                return Str::slug($place->name);
            }
        }

        if (function_exists('request') && request()->cookie('start_place_id')) {
            $cookieStart = (int) request()->cookie('start_place_id');
            if ($cookieStart) {
                $place = Place::find($cookieStart);
                if ($place && $place->name) {
                    return Str::slug($place->name);
                }
            }
        }

        // Prefer looking up a place record for 'warszawa' if present
        $slug = Place::where('slug', 'warszawa')->value('slug')
            ?? Place::where('name', 'Warszawa')->value('slug');

        return $slug ?: 'warszawa';
    }
}
