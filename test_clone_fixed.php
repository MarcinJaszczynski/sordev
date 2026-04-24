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

echo "=== Test naprawionego klonowania szablonu ===\n";

// Znajdź szablon o ID 10
$original = EventTemplate::find(10);

if (! $original) {
    echo "Błąd: Nie znaleziono szablonu o ID 10\n";
    exit(1);
}

echo "Oryginalny szablon: {$original->name} (ID: {$original->id})\n";

try {
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

    // Utwórz klon używając create() zamiast replicate()
    $clone = EventTemplate::create([
        'name' => $original->name.' (Test Kopia 2)',
        'subtitle' => $original->subtitle,
        'slug' => $original->slug.'-test-kopia2-'.uniqid(),
        'duration_days' => $original->duration_days,
        'is_active' => $original->is_active,
        'featured_image' => $original->featured_image,
        'event_description' => $original->event_description,
        'gallery' => $original->gallery,
        'office_description' => $original->office_description,
        'notes' => $original->notes,
        'transfer_km' => $original->transfer_km,
        'program_km' => $original->program_km,
        'bus_id' => $original->bus_id,
        'markup_id' => $original->markup_id,
        'start_place_id' => $original->start_place_id,
        'end_place_id' => $original->end_place_id,
        'transport_notes' => $original->transport_notes,
    ]);

    echo "✅ Podstawowy klon utworzony: ID {$clone->id}\n";

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

    // Klonuj ceny za osobę
    foreach ($original->pricesPerPerson as $price) {
        $clone->pricesPerPerson()->create([
            'event_template_qty_id' => $price->event_template_qty_id,
            'currency_id' => $price->currency_id,
            'start_place_id' => $price->start_place_id,
            'price_per_person' => $price->price_per_person,
        ]);
    }
    echo "✅ Ceny za osobę skopiowane\n";

    echo "\n🎉 Klonowanie zakończone pomyślnie!\n";
    echo "ID nowego szablonu: {$clone->id}\n";
    echo "Nazwa: {$clone->name}\n";
    echo "Slug: {$clone->slug}\n";

    // Test URL do edycji
    $editUrl = "http://sorbaza_old.test/admin/event-templates/{$clone->id}/edit";
    echo "URL do edycji: {$editUrl}\n";
} catch (Exception $e) {
    echo "\n❌ Błąd podczas klonowania: ".$e->getMessage()."\n";
    echo "Stack trace:\n".$e->getTraceAsString()."\n";
}
