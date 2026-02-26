<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\EventTemplatePricePerPerson;

$start = 1;
$prices = EventTemplatePricePerPerson::with(['eventTemplateQty','currency'])
    ->where('event_template_id',500)
    ->where('price_per_person','>',0)
    ->where('start_place_id',$start)
    ->orderBy('event_template_qty_id')
    ->orderBy('currency_id')
    ->orderByDesc('id')
    ->get()
    ->groupBy(['event_template_qty_id','currency_id'])
    ->map(fn($g)=>$g->first())
    ->values();

echo "count=".count($prices)."\n";
foreach ($prices as $p) {
    // Debug: print type and structure
    if (is_object($p)) {
        echo "CLASS=" . get_class($p) . "\n";
        if ($p instanceof Illuminate\Support\Collection) {
            echo "Collection with count=" . count($p) . "\n";
            print_r($p->take(3)->all());
        } else {
            echo "id={$p->id} qty_id={$p->event_template_qty_id} currency=".($p->currency?->symbol ?? 'null')." price={$p->price_per_person} start={$p->start_place_id}\n";
        }
    } else {
        var_export($p);
        echo "\n";
    }
}
