<?php

namespace App\Services;

class PlaceGeocodeService
{
    public static function getCoordinates(string $name): ?array
    {
        $apiKey = '5b3ce3597851110001cf62489885073b636a44e3ac9774af529a3c40';
        $url = 'https://api.openrouteservice.org/geocode/search?api_key=' . $apiKey . '&text=' . urlencode($name);
        try {
            $response = file_get_contents($url);
            $data = json_decode($response, true);
            if (isset($data['features'][0]['geometry']['coordinates'])) {
                $lon = $data['features'][0]['geometry']['coordinates'][0];
                $lat = $data['features'][0]['geometry']['coordinates'][1];
                return ['lat' => $lat, 'lon' => $lon];
            }
        } catch (\Exception $e) {
            return null;
        }
        return null;
    }
}
