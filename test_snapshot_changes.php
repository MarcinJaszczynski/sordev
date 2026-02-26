<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\EventTemplate;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

// Inicjalizacja Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Test zmian i porównywania snapshotów ===" . PHP_EOL;

// Symuluj zalogowanego użytkownika
Auth::login(User::first());

// Znajdź wcześniej utworzoną imprezę
$event = Event::find(2);
if (!$event) {
    echo "Błąd: Nie znaleziono imprezy z ID 2" . PHP_EOL;
    exit(1);
}

echo "Impreza: " . $event->name . PHP_EOL;
echo "Pierwotny koszt: " . $event->total_cost . " PLN" . PHP_EOL;

// Sprawdź pierwotny snapshot
$original = $event->originalSnapshot;
echo "Pierwotny snapshot: " . $original->name . " (koszt: " . $original->total_cost_snapshot . " PLN)" . PHP_EOL;

// Zmień dane imprezy
echo PHP_EOL . "=== Wprowadzanie zmian ===" . PHP_EOL;
$event->update([
    'client_name' => 'Klient testowy ZMIENIONY',
    'participant_count' => 30, // zmiana z 25 na 30
]);
echo "✅ Zmieniono dane imprezy" . PHP_EOL;

// Zmień koszt jednego z punktów programu
$firstPoint = $event->programPoints()->first();
if ($firstPoint) {
    $firstPoint->update([
        'unit_price' => 100.00,
        'quantity' => 2,
    ]);
    echo "✅ Zmieniono koszt punktu programu: " . $firstPoint->templatePoint->name . PHP_EOL;
    echo "Nowy koszt punktu: " . $firstPoint->total_price . " PLN" . PHP_EOL;
}

// Przeładuj imprezę
$event->refresh();
echo "Nowy koszt całkowity: " . $event->total_cost . " PLN" . PHP_EOL;

// Porównaj z pierwotnym stanem
echo PHP_EOL . "=== Porównanie z pierwotnym stanem ===" . PHP_EOL;
$comparison = $event->compareWithOriginal();

if ($comparison) {
    echo "Zmiany w danych imprezy: " . count($comparison['event_changes']) . PHP_EOL;
    foreach ($comparison['event_changes'] as $field => $change) {
        echo "- {$field}: '{$change['old']}' → '{$change['new']}'" . PHP_EOL;
    }

    $programChanges = $comparison['program_changes'];
    echo PHP_EOL . "Zmiany w programie:" . PHP_EOL;
    echo "- Dodane punkty: " . count($programChanges['added'] ?? []) . PHP_EOL;
    echo "- Usunięte punkty: " . count($programChanges['removed'] ?? []) . PHP_EOL;
    echo "- Zmodyfikowane punkty: " . count($programChanges['modified'] ?? []) . PHP_EOL;

    foreach ($programChanges['modified'] ?? [] as $modified) {
        echo "  * " . $modified['point_name'] . ":" . PHP_EOL;
        foreach ($modified['changes'] as $field => $change) {
            echo "    - {$field}: {$change['old']} → {$change['new']}" . PHP_EOL;
        }
    }

    $costChanges = $comparison['cost_changes'];
    echo PHP_EOL . "Zmiany kosztów:" . PHP_EOL;
    echo "- Poprzedni koszt: " . $costChanges['old_total'] . " PLN" . PHP_EOL;
    echo "- Obecny koszt: " . $costChanges['new_total'] . " PLN" . PHP_EOL;
    echo "- Różnica: " . ($costChanges['difference'] >= 0 ? '+' : '') . $costChanges['difference'] . " PLN" . PHP_EOL;

    // Utwórz snapshot po zmianach
    echo PHP_EOL . "=== Tworzenie snapshotu po zmianach ===" . PHP_EOL;
    $afterChangesSnapshot = $event->createManualSnapshot(
        'Po wprowadzeniu zmian',
        'Snapshot utworzony po zmianie danych klienta i kosztów punktu programu'
    );
    echo "✅ Snapshot utworzony: " . $afterChangesSnapshot->name . PHP_EOL;

    // Test przywracania pierwotnego stanu
    echo PHP_EOL . "=== Test przywracania pierwotnego stanu ===" . PHP_EOL;
    $restored = $event->restoreToOriginal();
    if ($restored) {
        $event->refresh();
        echo "✅ Przywrócono pierwotny stan!" . PHP_EOL;
        echo "Przywrócony koszt: " . $event->total_cost . " PLN" . PHP_EOL;
        echo "Przywrócona liczba uczestników: " . $event->participant_count . PHP_EOL;
        echo "Przywrócona nazwa klienta: " . $event->client_name . PHP_EOL;

        // Sprawdź czy można ponownie porównać
        $comparisonAfterRestore = $event->compareWithOriginal();
        echo "Zmian po przywróceniu: " . count($comparisonAfterRestore['event_changes']) . PHP_EOL;
    } else {
        echo "❌ Błąd przywracania pierwotnego stanu" . PHP_EOL;
    }

    echo PHP_EOL . "Łączna liczba snapshotów: " . $event->snapshots()->count() . PHP_EOL;

} else {
    echo "❌ Błąd: Nie można porównać z pierwotnym stanem" . PHP_EOL;
}

echo PHP_EOL . "=== Test zakończony ===" . PHP_EOL;
