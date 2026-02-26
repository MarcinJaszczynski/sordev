<?php

use App\Models\EventTemplate;
use App\Filament\Resources\EventTemplateResource\Widgets\EventTemplatePriceTable;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$templateId = 513;
$startPlaceId = 9;

$template = EventTemplate::find($templateId);
if (!$template) {
    echo "Template not found\n";
    exit(1);
}

$widget = new EventTemplatePriceTable();
$widget->record = $template;
$widget->startPlaceId = $startPlaceId;

$detailed = $widget->getDetailedCalculations();

foreach ($detailed as $qty => $data) {
    echo "=== QTY {$qty} ===\n";
    foreach ($data as $code => $info) {
        if (!is_array($info) || !isset($info['total'])) continue;
        echo "$code: total=" . ($info['total'] ?? '-') . "\n";
    }
    if (isset($data['hotel_structure'])) {
        foreach ($data['hotel_structure'] as $day) {
            echo "  Hotel day {$day['day']} totals:";
            foreach ($day['day_total'] as $code => $val) {
                echo " $code=$val";
            }
            echo "\n";
        }
    }
}
