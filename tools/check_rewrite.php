<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Services\UnifiedPriceCalculator;
use App\Models\EventTemplate;
use App\Models\EventTemplatePricePerPerson;
use App\Models\Currency;

// bootstrap
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$templateId = isset($argv[1]) ? (int)$argv[1] : 103;
$startPlaceId = isset($argv[2]) ? (int)$argv[2] : 39;

echo "Checking template={$templateId} start_place={$startPlaceId}\n";

$template = EventTemplate::find($templateId);
if (!$template) {
    echo "Template not found\n";
    exit(1);
}

$calc = new UnifiedPriceCalculator();
$calcData = $calc->calculate($template, $startPlaceId, true);

echo "\n--- UnifiedPriceCalculator output ---\n";
echo json_encode($calcData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

echo "\n--- DB rows (event_template_price_per_person) ---\n";
$rows = EventTemplatePricePerPerson::where('event_template_id', $templateId)
    ->where('start_place_id', $startPlaceId)
    ->orderBy('event_template_qty_id')
    ->get();

$out = [];
foreach ($rows as $r) {
    $out[] = [
        'qty_id' => $r->event_template_qty_id,
        'currency' => $r->currency?->symbol ?? null,
        'price_per_person' => (float)$r->price_per_person,
        'markup_amount' => (float)$r->markup_amount,
        'price_base' => (float)$r->price_base,
        'price_with_tax' => (float)$r->price_with_tax,
    ];
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

// Compare: for each qty in calcData, check DB rows matching currencies
foreach ($calcData as $qty => $row) {
    echo "\n== qty={$qty} ==\n";
    $currencies = $row['currencies'] ?? [];
    foreach ($currencies as $code => $cdata) {
        $rawMarkup = $cdata['raw']['markup_amount'] ?? null;
        $finalPP = $cdata['final']['price_per_person'] ?? null;
        echo "calc currency={$code} raw_markup=" . var_export($rawMarkup, true) . " finalPP=" . var_export($finalPP, true) . "\n";
        // find DB row
        $currencyModel = Currency::where('symbol', $code)->orWhere('code', $code)->first();
        if ($currencyModel) {
            $dbRow = EventTemplatePricePerPerson::where('event_template_id', $templateId)
                ->where('start_place_id', $startPlaceId)
                ->where('event_template_qty_id', $row['event_template_qty_id'])
                ->where('currency_id', $currencyModel->id)
                ->first();
            if ($dbRow) {
                echo "DB row: price_per_person={$dbRow->price_per_person}, markup_amount={$dbRow->markup_amount}, price_base={$dbRow->price_base}, price_with_tax={$dbRow->price_with_tax}\n";
            } else {
                echo "DB row: NOT FOUND for currency {$code}\n";
            }
        } else {
            echo "Currency model not found for code {$code}\n";
        }
    }
}

echo "\nDone.\n";
