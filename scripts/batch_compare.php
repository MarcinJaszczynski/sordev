<?php
// Usage: php scripts/batch_compare.php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\EventTemplate;
use App\Models\EventTemplateStartingPlaceAvailability;

$sample = EventTemplateStartingPlaceAvailability::where('available',1)
    ->groupBy('event_template_id','start_place_id')
    ->limit(8)
    ->get();

foreach ($sample as $row) {
    $templateId = $row->event_template_id;
    $startPlaceId = $row->start_place_id;
    echo "\n=== template={$templateId}, start_place={$startPlaceId} ===\n";
    $artisan = __DIR__ . '/../artisan';
    passthru("php \"$artisan\" eventtemplate:compare-calc $templateId $startPlaceId");
}

echo "\nBatch compare done.\n";
