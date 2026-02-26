<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Services\UnifiedPriceCalculator;
use App\Models\EventTemplate;

// bootstrap laravel
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$templateId = $argv[1] ?? 103;
$startPlaceId = $argv[2] ?? 39;

$template = EventTemplate::with(['markup', 'taxes', 'programPoints', 'hotelDays', 'bus'])->find($templateId);
if (!$template) {
    echo "Template not found: $templateId\n";
    exit(1);
}

$calc = new UnifiedPriceCalculator();
$data = $calc->calculate($template, (int)$startPlaceId, true);

echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
