<?php
// One-off debug script: php scripts/debug_engine.php <template_id> <start_place_id>
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\EventTemplate;
use App\Services\EventTemplateCalculationEngine;

if (($argc ?? 0) < 3) {
    echo "Usage: php scripts/debug_engine.php <event_template_id> <start_place_id>\n";
    exit(1);
}

$templateId = (int)$argv[1];
$startPlaceId = (int)$argv[2];

$template = EventTemplate::find($templateId);
if (!$template) {
    echo "Template not found\n";
    exit(2);
}

$engine = new EventTemplateCalculationEngine();
$results = $engine->calculateDetailed($template, $startPlaceId, null, true);

foreach ($results as $qty => $data) {
    echo "\n--- QTY={$qty} ---\n";
    echo "price_per_person: " . ($data['price_per_person'] ?? 'n/a') . "\n";
    if (isset($data['debug'])) {
        echo "d1={$data['debug']['d1']}, d2={$data['debug']['d2']}, basicDistance={$data['debug']['basicDistance']}\n";
        echo "defaultTransportKm=" . ($data['debug']['defaultTransportKm'] ?? 'null') . "\n";
        echo "busTransportCostTotal=" . var_export($data['debug']['busTransportCostTotal'], true) . "\n";
        echo "plnPoints (count)=" . count($data['debug']['plnPoints']) . "\n";
        echo "currenciesTotals=" . json_encode($data['debug']['currenciesTotals']) . "\n";
        echo "hotelStructure=" . json_encode($data['debug']['hotelStructure']) . "\n";
    }
}

echo "\nDone.\n";
