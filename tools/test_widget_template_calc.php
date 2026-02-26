<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Filament\Resources\EventTemplateResource\Widgets\EventTemplatePriceTable;
use App\Models\EventTemplate;

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$template = EventTemplate::find(146);
if (!$template) {
    echo "Template 146 not found\n"; exit(1);
}

$widget = new EventTemplatePriceTable();
$widget->record = $template;
$widget->startPlaceId = 1;
$widget->startPlace = \App\Models\Place::find(1);
$widget->mount();

$calculations = $widget->getDetailedCalculations();

echo "Markup percent (from widget helper): " . $widget->debugGetMarkupPercent() . "\n";
foreach ($calculations as $qty => $calc) {
    echo "Qty={$qty} percent_applied=" . ($calc['markup']['percent_applied'] ?? 'N/A') . " amount=" . ($calc['markup']['amount'] ?? 'N/A') . "\n";
}
