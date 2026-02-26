<?php
// One-off script: php scripts/save_engine_prices.php <template_id> <start_place_id>
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\EventTemplate;
use App\Models\EventTemplateQty;
use App\Models\EventTemplatePricePerPerson;
use App\Models\Currency;
use App\Services\EventTemplateCalculationEngine;

$argvCount = $_SERVER['argc'] ?? 0;
if ($argvCount < 3) {
    echo "Usage: php scripts/save_engine_prices.php <event_template_id> <start_place_id>\n";
    exit(1);
}

$templateId = (int)$_SERVER['argv'][1];
$startPlaceId = (int)$_SERVER['argv'][2];

$template = EventTemplate::find($templateId);
if (!$template) {
    echo "EventTemplate id={$templateId} not found\n";
    exit(2);
}

echo "Calculating engine prices for template={$templateId}, start_place={$startPlaceId}\n";

$engine = new EventTemplateCalculationEngine();
$results = $engine->calculateDetailed($template, $startPlaceId);

$qtyVariants = EventTemplateQty::all();
// find PLN by symbol or name
$pln = Currency::where('symbol', 'PLN')
    ->orWhere('name', 'like', '%złoty%')
    ->orWhere('name', 'like', '%zloty%')
    ->first();
$plnId = $pln?->id ?? null;

foreach ($qtyVariants as $qtyVariant) {
    $qty = $qtyVariant->qty;
    if (!isset($results[$qty])) {
        echo " - qty={$qty}: no calc result, skipping\n";
        continue;
    }
    $calc = $results[$qty];
    $saveData = [
        'price_per_person' => $calc['price_per_person'] ?? 0,
        'price_per_tax' => $calc['tax_amount'] ?? 0,
        'transport_cost' => $calc['transport_cost'] ?? null,
        'price_base' => $calc['price_base'] ?? 0,
        'markup_amount' => $calc['markup_amount'] ?? 0,
        'tax_amount' => $calc['tax_amount'] ?? 0,
        'price_with_tax' => $calc['price_with_tax'] ?? 0,
        'updated_at' => now(),
    ];

    EventTemplatePricePerPerson::updateOrCreate([
        'event_template_id' => $template->id,
        'event_template_qty_id' => $qtyVariant->id,
        'currency_id' => $plnId,
        'start_place_id' => $startPlaceId,
    ], $saveData);

    echo " - qty={$qty}: saved price_per_person={$saveData['price_per_person']} (qty_id={$qtyVariant->id})\n";
}

echo "Done.\n";
