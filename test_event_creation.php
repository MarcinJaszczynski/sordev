<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\EventTemplate;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

// Inicjalizacja Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Test tworzenia imprezy z pierwotnym snapshotem ===" . PHP_EOL;

// Symuluj zalogowanego użytkownika
$user = User::first();
if (!$user) {
    echo "Błąd: Nie znaleziono użytkownika w bazie" . PHP_EOL;
    exit(1);
}

Auth::login($user);
echo "Zalogowany jako: " . $user->name . PHP_EOL;

// Znajdź szablon z punktami programu
$template = EventTemplate::whereHas('programPoints')->first();

if (!$template) {
    echo "Błąd: Nie znaleziono szablonu z punktami programu" . PHP_EOL;
    exit(1);
}

echo "Szablon: " . $template->name . PHP_EOL;
echo "Punktów programu: " . $template->programPoints()->count() . PHP_EOL;

// Utwórz imprezę z szablonu
try {
    $event = Event::createFromTemplate($template, [
        'name' => 'Test impreza z pierwotnym snapshotem',
        'client_name' => 'Klient testowy',
        'start_date' => '2025-07-01',
        'participant_count' => 25,
    ]);

    echo PHP_EOL . "✅ Impreza utworzona pomyślnie!" . PHP_EOL;
    echo "ID: " . $event->id . PHP_EOL;
    echo "Nazwa: " . $event->name . PHP_EOL;
    echo "Punktów programu: " . $event->programPoints()->count() . PHP_EOL;
    echo "Koszt całkowity: " . $event->total_cost . " PLN" . PHP_EOL;
    echo "Snapshotów: " . $event->snapshots()->count() . PHP_EOL;

    // Sprawdź pierwotny snapshot
    $originalSnapshot = $event->originalSnapshot;
    if ($originalSnapshot) {
        echo PHP_EOL . "✅ Pierwotny snapshot utworzony!" . PHP_EOL;
        echo "Nazwa snapshotu: " . $originalSnapshot->name . PHP_EOL;
        echo "Typ: " . $originalSnapshot->type . PHP_EOL;
        echo "Data utworzenia: " . $originalSnapshot->snapshot_date->format('Y-m-d H:i:s') . PHP_EOL;
        echo "Koszt w snapszorcie: " . $originalSnapshot->total_cost_snapshot . " PLN" . PHP_EOL;
        echo "Punktów w snapszorcie: " . count($originalSnapshot->program_points) . PHP_EOL;

        // Sprawdź kursy walut
        if (!empty($originalSnapshot->currency_rates)) {
            echo "Kursy walut zapisane: " . count($originalSnapshot->currency_rates) . PHP_EOL;
        }

        // Sprawdź kalkulacje
        if (!empty($originalSnapshot->calculations)) {
            $calc = $originalSnapshot->calculations;
            echo "Kalkulacje - aktywne punkty: " . ($calc['active_points_count'] ?? 0) . PHP_EOL;
            echo "Kalkulacje - koszt programu: " . ($calc['total_program_cost'] ?? 0) . " PLN" . PHP_EOL;
        }

    } else {
        echo "❌ Błąd: Pierwotny snapshot nie został utworzony!" . PHP_EOL;
    }

    // Test porównania z pierwotnym stanem
    echo PHP_EOL . "=== Test porównania z pierwotnym stanem ===" . PHP_EOL;
    $comparison = $event->compareWithOriginal();
    if ($comparison) {
        echo "✅ Porównanie wykonane pomyślnie" . PHP_EOL;
        echo "Zmian w danych: " . count($comparison['event_changes']) . PHP_EOL;
        echo "Zmian w programie: " . count($comparison['program_changes']['modified'] ?? []) . PHP_EOL;
        echo "Różnica kosztów: " . ($comparison['cost_changes']['difference'] ?? 0) . " PLN" . PHP_EOL;
    } else {
        echo "❌ Błąd: Nie można porównać z pierwotnym stanem" . PHP_EOL;
    }

    // Test ręcznego snapshotu
    echo PHP_EOL . "=== Test ręcznego snapshotu ===" . PHP_EOL;
    $manualSnapshot = $event->createManualSnapshot('Test snapshot ręczny', 'Snapshot utworzony w teście');
    echo "✅ Ręczny snapshot utworzony!" . PHP_EOL;
    echo "ID: " . $manualSnapshot->id . PHP_EOL;
    echo "Nazwa: " . $manualSnapshot->name . PHP_EOL;

    echo PHP_EOL . "=== Test zakończony pomyślnie! ===" . PHP_EOL;
    echo "Łączna liczba snapshotów: " . $event->snapshots()->count() . PHP_EOL;

} catch (Exception $e) {
    echo "❌ Błąd podczas tworzenia imprezy: " . $e->getMessage() . PHP_EOL;
    echo "Stack trace:" . PHP_EOL . $e->getTraceAsString() . PHP_EOL;
}
