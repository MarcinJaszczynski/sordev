<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Models\EventTemplate;
use App\Models\Markup;
use App\Services\EventTemplateCalculationEngine;
use Illuminate\Support\Facades\Artisan;

// bootstrap laravel
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Try to find a template that uses a non-default markup (different than system default)
$defaultMarkupId = Markup::where('is_default', true)->first()?->id;
$template = null;
if ($defaultMarkupId) {
    $template = EventTemplate::whereNotNull('markup_id')->where('markup_id', '!=', $defaultMarkupId)->first();
}

// fallback: any template with a markup_id
if (!$template) {
    $template = EventTemplate::whereNotNull('markup_id')->where('markup_id', '!=', 0)->first();
}
if (!$template) {
    echo "No template with non-null markup_id found.\n";
    exit(0);
}

$engine = new EventTemplateCalculationEngine();
$results = $engine->calculateDetailed($template, $template->start_place_id, null, true);

echo "Template ID: {$template->id}\n";
echo "Template markup_percent raw: " . ($template->markup_percent ?? 'null') . "\n";
echo "Template markup_id: " . ($template->markup_id ?? 'null') . "\n";
echo "Template relation markup percent: " . ($template->markup?->percent ?? 'null') . "\n";

$resolved = App\Models\Markup::find($template->markup_id);
echo "Resolved Markup model percent: " . ($resolved?->percent ?? 'null') . "\n";

foreach ($results as $qty => $row) {
    echo "Qty={$qty} => markup_amount={$row['markup_amount']} price_base={$row['price_base']} price_with_tax={$row['price_with_tax']}\n";
}

// Also show default markup from Markup model if exists
$default = App\Models\Markup::where('is_default', true)->first();
if ($default) {
    echo "Default markup percent: " . $default->percent . "\n";
}

