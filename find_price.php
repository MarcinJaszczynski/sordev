<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = app('db');

echo "=== Szukamy 48822 w event_template_price_per_person ===\n";
$r = $db->table('event_template_price_per_person')
    ->where('price_with_tax', '>=', 48820)
    ->where('price_with_tax', '<=', 48825)
    ->get(['event_template_id','event_template_qty_id','start_place_id','price_with_tax','price_per_person']);
foreach ($r as $row) {
    echo "template={$row->event_template_id} qty_id={$row->event_template_qty_id} start={$row->start_place_id} price={$row->price_with_tax} ppp={$row->price_per_person}\n";
}
if ($r->isEmpty()) echo "(brak wynikow)\n";

echo "=== Szukamy 48822 w event_price_per_person ===\n";
$r2 = $db->table('event_price_per_person')
    ->where('price_with_tax', '>=', 48820)
    ->where('price_with_tax', '<=', 48825)
    ->get(['event_id','event_template_qty_id','start_place_id','price_with_tax','currency_id']);
foreach ($r2 as $row) {
    echo "event={$row->event_id} start={$row->start_place_id} price={$row->price_with_tax} cur={$row->currency_id}\n";
}
if ($r2->isEmpty()) echo "(brak wynikow)\n";

echo "=== Event 7 ===\n";
$ev = $db->table('events')->where('id',7)->first(['start_place_id','event_template_id','participant_count']);
echo "start_place_id={$ev->start_place_id} template_id={$ev->event_template_id} pax={$ev->participant_count}\n";

echo "=== Szukamy ppp=1627.41 w event_template ===\n";
$r3 = $db->table('event_template_price_per_person')
    ->where('price_per_person', '>=', 1627)
    ->where('price_per_person', '<=', 1628)
    ->get(['event_template_id','event_template_qty_id','start_place_id','price_with_tax','price_per_person']);
foreach ($r3 as $row) {
    $qty = $db->table('event_template_qty')->where('id',$row->event_template_qty_id)->value('qty');
    echo "template={$row->event_template_id} qty={$qty} start={$row->start_place_id} price={$row->price_with_tax} ppp={$row->price_per_person}\n";
}
if ($r3->isEmpty()) echo "(brak wynikow)\n";
