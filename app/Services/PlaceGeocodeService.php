<?php

namespace App\Services;

use App\Support\OpenRouteServiceSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PlaceGeocodeService
{
    public static function getCoordinates(string $name): ?array
    {
        $apiKey = OpenRouteServiceSettings::apiKey();
        if ($apiKey === null) {
            return null;
        }

        $url = 'https://api.openrouteservice.org/geocode/search';

        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->get($url, [
                    'api_key' => $apiKey,
                    'text' => $name,
                ]);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();
            if (isset($data['features'][0]['geometry']['coordinates'])) {
                $lon = $data['features'][0]['geometry']['coordinates'][0];
                $lat = $data['features'][0]['geometry']['coordinates'][1];

                return ['lat' => $lat, 'lon' => $lon];
            }
        } catch (\Throwable $e) {
            Log::warning('ORS geocode failed: '.$e->getMessage());

            return null;
        }

        return null;
    }
}
