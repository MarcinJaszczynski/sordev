<?php
// Usage: php fill_missing_distances_for_template.php <event_template_id>

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Place;
use App\Models\PlaceDistance;
use App\Models\EventTemplate;

$apiKey = '5b3ce3597851110001cf62489885073b636a44e3ac9774af529a3c40';
$timeout = 8;

if ($argc < 2) {
    echo "Usage: php fill_missing_distances_for_template.php <event_template_id>\n";
    exit(1);
}

$templateId = (int)$argv[1];
$template = EventTemplate::find($templateId);
if (!$template) {
    echo "EventTemplate {$templateId} not found\n";
    exit(1);
}

$startPlaceId = $template->start_place_id;
$endPlaceId = $template->end_place_id;
if (!$startPlaceId || !$endPlaceId) {
    echo "Template {$templateId} missing start_place_id or end_place_id\n";
    exit(1);
}

$startingPlaces = Place::where('starting_place', true)->get();

$missingPairs = [];

foreach ($startingPlaces as $from) {
    if ($from->id === $startPlaceId) continue;
    $existing = PlaceDistance::where('from_place_id', $from->id)->where('to_place_id', $startPlaceId)->first();
    if (!$existing || !$existing->distance_km) {
        $to = Place::find($startPlaceId);
        if ($to) $missingPairs[] = ['from' => $from, 'to' => $to, 'type' => 'tam'];
    }
}

$endPlace = Place::find($endPlaceId);
if ($endPlace) {
    foreach ($startingPlaces as $to) {
        if ($endPlace->id === $to->id) continue;
        $existing = PlaceDistance::where('from_place_id', $endPlace->id)->where('to_place_id', $to->id)->first();
        if (!$existing || !$existing->distance_km) {
            $missingPairs[] = ['from' => $endPlace, 'to' => $to, 'type' => 'powrot'];
        }
    }
}

if (count($missingPairs) === 0) {
    echo "No missing pairs for template {$templateId}\n";
    exit(0);
}

echo "Found " . count($missingPairs) . " missing pairs for template {$templateId}\n";

$processed = 0;
$updated = 0;
$errors = [];

foreach ($missingPairs as $pair) {
    $from = $pair['from'];
    $to = $pair['to'];

    // re-check
    $existing = PlaceDistance::where('from_place_id', $from->id)->where('to_place_id', $to->id)->first();
    if ($existing && $existing->distance_km) {
        $processed++;
        continue;
    }

    if (!$from->latitude || !$from->longitude || !$to->latitude || !$to->longitude) {
        $errors[] = [ 'from' => $from->id, 'to' => $to->id, 'error' => 'missing coords' ];
        $processed++;
        continue;
    }

    $url = 'https://api.openrouteservice.org/v2/directions/driving-car?api_key=' . $apiKey . '&start=' . $from->longitude . ',' . $from->latitude . '&end=' . $to->longitude . ',' . $to->latitude;
    try {
        $ctx = stream_context_create(['http' => ['timeout' => $timeout]]);
        $response = @file_get_contents($url, false, $ctx);
        if (!$response) {
            $errors[] = [ 'from' => $from->id, 'to' => $to->id, 'error' => 'no response' ];
            $processed++;
            continue;
        }
        $data = json_decode($response, true);
        if (isset($data['features'][0]['properties']['segments'][0]['distance'])) {
            $distance = round($data['features'][0]['properties']['segments'][0]['distance'] / 1000, 2);
            PlaceDistance::updateOrCreate([
                'from_place_id' => $from->id,
                'to_place_id' => $to->id,
            ], [
                'distance_km' => $distance,
                'api_source' => 'openrouteservice',
            ]);
            echo "Saved distance {$distance} km from {$from->id} to {$to->id}\n";
            $updated++;
        } else {
            $errors[] = [ 'from' => $from->id, 'to' => $to->id, 'error' => 'no distance in response' ];
        }
    } catch (\Throwable $e) {
        $errors[] = [ 'from' => $from->id, 'to' => $to->id, 'error' => $e->getMessage() ];
    }
    $processed++;
    // small sleep to avoid rate limits
    usleep(200000);
}

echo "Processed: {$processed}, Updated: {$updated}, Errors: " . count($errors) . "\n";
if (count($errors) > 0) {
    echo json_encode($errors, JSON_PRETTY_PRINT) . "\n";
}

exit(0);
