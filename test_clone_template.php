<?php

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\EventTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

// Symuluj zalogowanego użytkownika
$user = User::first();
if ($user) {
    Auth::login($user);
    echo "Zalogowano użytkownika: {$user->name}\n";
}

echo "=== Test klonowania szablonu EventTemplate ===\n";

// Znajdź szablon o ID 10
$original = EventTemplate::find(10);

if (! $original) {
    echo "Błąd: Nie znaleziono szablonu o ID 10\n";
    exit(1);
}

echo "Oryginalny szablon: {$original->name}\n";
echo "Slug: {$original->slug}\n";

// Załaduj wszystkie relacje
$original->load([
    'tags',
    'programPoints',
    'dayInsurances.insurance',
    'hotelDays',
    'startingPlaceAvailabilities',
    'taxes',
    'pricesPerPerson',
]);

echo 'Liczba tagów: '.$original->tags->count()."\n";
echo 'Liczba punktów programu: '.$original->programPoints->count()."\n";
echo 'Liczba cen za osobę: '.$original->pricesPerPerson->count()."\n";
echo 'Liczba ubezpieczeń dni: '.$original->dayInsurances->count()."\n";
echo 'Liczba dni hotelowych: '.$original->hotelDays->count()."\n";
echo 'Liczba dostępności miejsc: '.$original->startingPlaceAvailabilities->count()."\n";
echo 'Liczba podatków: '.$original->taxes->count()."\n";

try {
    // Rozpocznij klonowanie
    $clone = $original->replicate();
    $clone->name = $original->name.' (Test Kopia)';
    $clone->slug = $original->slug.'-test-kopia-'.uniqid();
    $clone->save();

    echo "\n✅ Podstawowy klon utworzony: ID {$clone->id}\n";

    // Klonuj tagi
    $clone->tags()->sync($original->tags->pluck('id')->toArray());
    echo "✅ Tagi skopiowane\n";

    // Klonuj punkty programu (pivot)
    foreach ($original->programPoints as $point) {
        $clone->programPoints()->attach($point->id, [
            'day' => $point->pivot->day,
            'order' => $point->pivot->order,
            'notes' => $point->pivot->notes,
            'include_in_program' => $point->pivot->include_in_program,
            'include_in_calculation' => $point->pivot->include_in_calculation,
            'active' => $point->pivot->active,
        ]);
    }
    echo "✅ Punkty programu skopiowane\n";

    // Klonuj ceny za osobę (zamiast wariantów QTY)
    foreach ($original->pricesPerPerson as $price) {
        $clone->pricesPerPerson()->create([
            'event_template_qty_id' => $price->event_template_qty_id,
            'currency_id' => $price->currency_id,
            'start_place_id' => $price->start_place_id,
            'price_per_person' => $price->price_per_person,
        ]);
    }
    echo "✅ Ceny za osobę skopiowane\n";

    // Klonuj ubezpieczenia dni
    foreach ($original->dayInsurances as $dayInsurance) {
        $clone->dayInsurances()->create([
            'day' => $dayInsurance->day,
            'insurance_id' => $dayInsurance->insurance_id,
        ]);
    }
    echo "✅ Ubezpieczenia dni skopiowane\n";

    // Klonuj dni hotelowe
    foreach ($original->hotelDays as $hotelDay) {
        $clone->hotelDays()->create([
            'day' => $hotelDay->day,
            'hotel_room_ids_qty' => $hotelDay->hotel_room_ids_qty,
            'hotel_room_ids_gratis' => $hotelDay->hotel_room_ids_gratis,
            'hotel_room_ids_staff' => $hotelDay->hotel_room_ids_staff,
            'hotel_room_ids_driver' => $hotelDay->hotel_room_ids_driver,
        ]);
    }
    echo "✅ Dni hotelowe skopiowane\n";

    // Klonuj dostępność miejsc startowych
    foreach ($original->startingPlaceAvailabilities as $availability) {
        $clone->startingPlaceAvailabilities()->create([
            'start_place_id' => $availability->start_place_id,
            'end_place_id' => $availability->end_place_id,
            'available' => $availability->available,
            'note' => $availability->note,
        ]);
    }
    echo "✅ Dostępność miejsc startowych skopiowana\n";

    // Klonuj podatki
    $clone->taxes()->sync($original->taxes->pluck('id')->toArray());
    echo "✅ Podatki skopiowane\n";

    echo "\n🎉 Klonowanie zakończone pomyślnie!\n";
    echo "ID nowego szablonu: {$clone->id}\n";
    echo "Nazwa: {$clone->name}\n";
    echo "Slug: {$clone->slug}\n";

    // Sprawdź czy można przejść do edycji
    $editUrl = "http://sorbaza_old.test/admin/event-templates/{$clone->id}/edit";
    echo "URL do edycji: {$editUrl}\n";
} catch (Exception $e) {
    echo "\n❌ Błąd podczas klonowania: ".$e->getMessage()."\n";
    echo "Stack trace:\n".$e->getTraceAsString()."\n";
}
