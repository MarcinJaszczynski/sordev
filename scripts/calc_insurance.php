<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\EventTemplate;
use App\Models\EventTemplateQty;

$templateId = $argv[1] ?? 146;
$qtyNumber = $argv[2] ?? 10;

$template = EventTemplate::with(['dayInsurances.insurance'])->find($templateId);
if (!$template) { echo "Template not found\n"; exit(1); }

$qtyVariant = EventTemplateQty::where('qty', $qtyNumber)->first();
if (!$qtyVariant) { echo "Qty variant not found\n"; exit(1); }

$qty = $qtyVariant->qty;
$gratis = $qtyVariant->gratis ?? 0;
$count = $qty + $gratis;

echo "Template {$template->id} - qty={$qty}, gratis={$gratis}, count={$count}\n";

$perDay = [];
foreach ($template->dayInsurances as $di) {
    $day = $di->day;
    $ins = $di->insurance;
    if (!$ins || !$ins->insurance_enabled) continue;
    $perDay[$day][] = $ins->price_per_person;
}

$total = 0.0;
ksort($perDay);
foreach ($perDay as $day => $prices) {
    $sumPerPerson = array_sum($prices);
    $dayTotal = $sumPerPerson * $count;
    echo "Day {$day}: per_person_sum={$sumPerPerson}, day_total={$dayTotal}\n";
    $total += $dayTotal;
}

echo "TOTAL insurance sum = {$total}\n";
