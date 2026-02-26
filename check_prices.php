<?php

use App\Models\EventTemplatePricePerPerson;
use App\Models\EventTemplateQty;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$eventTemplateId = 10; // Twój szablon
$startPlaceId = 3; // Poznań (zmień jeśli inny)

$prices = EventTemplatePricePerPerson::with('eventTemplateQty')
    ->where('event_template_id', $eventTemplateId)
    ->where('start_place_id', $startPlaceId)
    ->orderBy('event_template_qty_id')
    ->get();

foreach ($prices as $p) {
    echo 'Qty: ' . ($p->eventTemplateQty->qty ?? '-') . ', price_per_person: ' . $p->price_per_person . " PLN\n";
}
