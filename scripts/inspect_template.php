<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\EventTemplate;

$templateId = $argv[1] ?? null;
if (!$templateId) { echo "Usage: php scripts/inspect_template.php <template_id>\n"; exit(1); }

$template = EventTemplate::with(['dayInsurances.insurance'])->find($templateId);
if (!$template) { echo "Template not found\n"; exit(2); }

echo "Template id={$template->id}, name={$template->name}\n";

echo "Qty variants (global list):\n";
foreach (\App\Models\EventTemplateQty::all() as $v) {
    echo " - id={$v->id}, qty={$v->qty}, gratis={$v->gratis}, staff={$v->staff}, driver={$v->driver}\n";
}

echo "Day insurances:\n";
foreach ($template->dayInsurances as $di) {
    $ins = $di->insurance;
    if ($ins) {
        echo " - day={$di->day}, insurance_id={$ins->id}, name={$ins->name}, price_per_person={$ins->price_per_person}, per_day={$ins->insurance_per_day}, per_person={$ins->insurance_per_person}\n";
    } else {
        echo " - day={$di->day}, NO insurance attached\n";
    }
}

echo "Done\n";

// Spróbuj uruchomić silnik kalkulacji w try/catch i wypisać wynik dla debug
try {
    $engine = new \App\Services\EventTemplateCalculationEngine();
    $results = $engine->calculateDetailed($template, null, null, true);
    echo "\nEngine calculateDetailed returned:\n";
    echo var_export($results, true) . "\n";
} catch (\Exception $e) {
    echo "Engine execution failed: " . $e->getMessage() . "\n";
}
