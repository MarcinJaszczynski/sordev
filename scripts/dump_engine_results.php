<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\EventTemplate;
use App\Services\EventTemplateCalculationEngine;

if (($argc ?? 0) < 3) {
    echo "Usage: php scripts/dump_engine_results.php <event_template_id> <start_place_id>\n";
    exit(1);
}

$templateId = (int)$argv[1];
$startPlaceId = (int)$argv[2];

$template = EventTemplate::with(['dayInsurances.insurance'])->find($templateId);
if (!$template) {
    echo "Template not found\n";
    exit(2);
}

$engine = new EventTemplateCalculationEngine();
$results = $engine->calculateDetailed($template, $startPlaceId, null, true);

echo var_export($results, true);

echo "\nDone.\n";
