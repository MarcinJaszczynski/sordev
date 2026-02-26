<?php
// Usage: php fill_all_missing_distances.php
// This script backsup DB and fills all missing place_distances:
// 1) copy symmetric distance if present
// 2) else compute Haversine * factor (default 1.3)

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Place;
use App\Models\PlaceDistance;

$memoryLimit = '512M';
@ini_set('memory_limit', $memoryLimit);
set_time_limit(0);
date_default_timezone_set('UTC');
$now = date('Ymd_His');
$dbPath = __DIR__ . '/../database/database.sqlite';
$backupPath = __DIR__ . "/../database/database.sqlite.bak." . $now;

if (!file_exists($dbPath)) {
    echo "ERROR: database file not found at {$dbPath}\n";
    exit(1);
}

if (!copy($dbPath, $backupPath)) {
    echo "ERROR: failed to create DB backup at {$backupPath}\n";
    exit(1);
}

echo "Created DB backup: {$backupPath}\n";

// configuration
$factor = 1.3; // multiplier to approximate road distance from great-circle
$report = [
    'started_at' => date('c'),
    'factor' => $factor,
    'total_places' => 0,
    'total_missing_pairs' => 0,
    'filled_symmetric' => 0,
    'filled_haversine' => 0,
    'skipped_no_coords' => 0,
    'errors' => [],
    'sample_errors' => [],
    'processed_pairs' => 0,
];

$places = Place::all();
$report['total_places'] = $places->count();
if ($places->count() === 0) {
    echo "No places found.\n";
    exit(0);
}

// Build map of existing distances for quick lookup
// Prepare helpers
$placeMap = $places->keyBy('id');
$placeIds = $places->pluck('id')->toArray();

// NEW RULE: compute distances only for pairs where at least one side is a starting place
// Allowed: start<->non-start and start<->start. Disallowed: non-start<->non-start.
function shouldProcessPair($from, $to): bool {
    return (bool)($from?->starting_place) || (bool)($to?->starting_place);
}

$totalPairs = 0;
foreach ($placeIds as $fromId) {
    $totalPairs += count($placeIds) - 1;
}

$report['total_missing_pairs'] = 0; // we'll increment as we find missing
echo "Total pairs: {$totalPairs} (will scan and fill missing)\n";

$processed = 0;
// Iterate streaming: for each fromId -> for each toId check and fill if missing
foreach ($placeIds as $fromId) {
    foreach ($placeIds as $toId) {
        if ($fromId == $toId) continue;

        $fromPlace = $placeMap->get($fromId);
        $toPlace = $placeMap->get($toId);
        if (!shouldProcessPair($fromPlace, $toPlace)) {
            continue;
        }

        $processed++;
        $report['processed_pairs'] = $processed;
        // Check existing distance directly from DB
        $existsDistance = PlaceDistance::where('from_place_id', $fromId)->where('to_place_id', $toId)->value('distance_km');
        if ($existsDistance && $existsDistance > 0) {
            continue; // already present
        }
        $report['total_missing_pairs']++;

        try {
            // 1) try symmetric copy (check DB value)
            $symmetry = PlaceDistance::where('from_place_id', $toId)->where('to_place_id', $fromId)->value('distance_km');
            if ($symmetry && $symmetry > 0) {
                PlaceDistance::updateOrCreate([
                    'from_place_id' => $fromId,
                    'to_place_id' => $toId,
                ], [
                    'distance_km' => $symmetry,
                    'api_source' => 'symmetric_copy',
                ]);
                $report['filled_symmetric']++;
                echo "symmetric: {$fromId} -> {$toId} = {$symmetry}\n";
                continue;
            }

            // 2) compute haversine estimate
            $from = $placeMap->get($fromId);
            $to = $placeMap->get($toId);
            if (!$from || !$to) {
                $report['errors'][] = [ 'from' => $fromId, 'to' => $toId, 'error' => 'place_not_found' ];
                if (count($report['sample_errors']) < 50) $report['sample_errors'][] = end($report['errors']);
                echo "error: place not found {$fromId} or {$toId}\n";
                continue;
            }

            if (!$from->latitude || !$from->longitude || !$to->latitude || !$to->longitude) {
                $report['skipped_no_coords']++;
                $report['errors'][] = [ 'from' => $fromId, 'to' => $toId, 'error' => 'no_coords' ];
                if (count($report['sample_errors']) < 50) $report['sample_errors'][] = end($report['errors']);
                echo "skipped (no coords): {$fromId} -> {$toId}\n";
                continue;
            }

            // haversine
            $lat1 = deg2rad((float)$from->latitude);
            $lon1 = deg2rad((float)$from->longitude);
            $lat2 = deg2rad((float)$to->latitude);
            $lon2 = deg2rad((float)$to->longitude);
            $dLat = $lat2 - $lat1;
            $dLon = $lon2 - $lon1;
            $a = sin($dLat/2) * sin($dLat/2) + cos($lat1) * cos($lat2) * sin($dLon/2) * sin($dLon/2);
            $c = 2 * atan2(sqrt($a), sqrt(1-$a));
            $R = 6371.0; // km
            $d_gc = $R * $c;
            $estimated = round($d_gc * $factor, 2);

            PlaceDistance::updateOrCreate([
                'from_place_id' => $fromId,
                'to_place_id' => $toId,
            ], [
                'distance_km' => $estimated,
                'api_source' => 'haversine_estimate',
            ]);

            $report['filled_haversine']++;
            echo "haversine: {$fromId} -> {$toId} = {$estimated}\n";

        } catch (\Throwable $e) {
            $report['errors'][] = [ 'from' => $fromId, 'to' => $toId, 'error' => $e->getMessage() ];
            if (count($report['sample_errors']) < 50) $report['sample_errors'][] = end($report['errors']);
            echo "exception for {$fromId} -> {$toId}: " . $e->getMessage() . "\n";
        }
    }
}

$report['finished_at'] = date('c');

$reportPath = __DIR__ . "/fill_all_missing_distances_report_{$now}.json";
file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "\nDONE. Report saved to: {$reportPath}\n";
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

exit(0);
