<?php

use App\Models\EventTemplate;
use App\Filament\Resources\EventTemplateResource\Widgets\EventTemplatePriceTable;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$eventTemplateId = 10; // Twój szablon
$startPlaceId = 3; // Poznań

$template = EventTemplate::find($eventTemplateId);
$widget = new EventTemplatePriceTable();
$widget->record = $template;
$widget->startPlaceId = $startPlaceId;

$detailed = $widget->getDetailedCalculations();

foreach ($detailed as $qty => $data) {
    if (isset($data['PLN'])) {
        echo "Qty: $qty\n";
        echo "  Suma kosztów (total): " . ($data['PLN']['total'] ?? '-') . "\n";
        echo "  Suma bez narzutu: " . ($data['PLN']['total_before_markup'] ?? '-') . "\n";
        echo "  Suma z narzutem, bez podatków: " . ($data['PLN']['total_before_tax'] ?? '-') . "\n";
        // Debug transportu
        $bus = $widget->record->bus;
        $programKm = $widget->record->program_km ?? 0;
        $startPlaceId = $widget->startPlaceId;
        $templateStartId = $widget->record->start_place_id;
        $templateEndId = $widget->record->end_place_id;
        $d1 = 0;
        $d2 = 0;
        if ($startPlaceId && $templateStartId) {
            $d1 = \App\Models\PlaceDistance::where('from_place_id', $startPlaceId)
                ->where('to_place_id', $templateStartId)
                ->first()?->distance_km ?? 0;
        }
        if ($templateEndId && $startPlaceId) {
            $d2 = \App\Models\PlaceDistance::where('from_place_id', $templateEndId)
                ->where('to_place_id', $startPlaceId)
                ->first()?->distance_km ?? 0;
        }
        $basicDistance = $d1 + $d2 + $programKm;
        $totalKm = 1.1 * $basicDistance + 50;
        $duration = $widget->record->duration_days ?? 1;
        $busCapacity = $bus ? $bus->capacity : 0;
        $qtyVariant = \App\Models\EventTemplateQty::where('qty', $qty)->first();
        $totalPeople = $qty;
        if ($qtyVariant) {
            // keep totalPeople for hotel/transport but compute peopleForPoints for tickets/points
            $totalPeople += ($qtyVariant->gratis ?? 0) + ($qtyVariant->staff ?? 0) + ($qtyVariant->driver ?? 0);
        }
        $busCount = ($bus && $busCapacity > 0) ? ceil($totalPeople / $busCapacity) : 1;
        $includedKm = $duration * ($bus ? $bus->package_km_per_day : 0);
        $baseCost = $duration * ($bus ? $bus->package_price_per_day : 0);
        $extraKm = $totalKm > $includedKm ? $totalKm - $includedKm : 0;
        $extraKmCost = $extraKm * ($bus ? $bus->extra_km_price : 0);
        echo "  [DEBUG TRANSPORTU]\n";
        echo "    d1 (start->poczatek): $d1\n";
        echo "    d2 (koniec->start): $d2\n";
        echo "    programKm: $programKm\n";
        echo "    basicDistance: $basicDistance\n";
        echo "    Suma km (totalKm): $totalKm\n";
        echo "    Limit km (includedKm): $includedKm\n";
        echo "    Nadmiarowe km (extraKm): $extraKm\n";
        echo "    Koszt za nadmiarowe km: $extraKmCost\n";
        echo "    Liczba autobusów: $busCount\n";
        echo "    Koszt bazowy: $baseCost\n";
        echo "    Całkowity koszt transportu: " . ($baseCost + $extraKmCost) * $busCount . "\n";
        echo "  Punkty kosztowe:\n";
        foreach (($data['PLN']['points'] ?? []) as $point) {
            echo "    - {$point['name']}: {$point['cost']} PLN\n";
        }
        echo "\n";
    }
}
