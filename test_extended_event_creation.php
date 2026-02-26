<?php

require_once __DIR__ . '/bootstrap/app.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Test tworzenia imprezy z rozszerzonymi danymi

use App\Models\EventTemplate;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

try {
    // Symuluj zalogowanego użytkownika
    $user = User::first();
    if ($user) {
        Auth::login($user);
        echo "Zalogowano użytkownika: {$user->name}\n";
    } else {
        echo "Brak użytkowników w bazie\n";
        exit;
    }

    // Znajdź szablon z punktami programu
    $template = EventTemplate::with(['programPoints', 'bus', 'markup'])->find(2);

    if (!$template) {
        echo "Nie znaleziono szablonu o ID 2\n";
        exit;
    }

    echo "=== Test tworzenia imprezy z rozszerzonymi danymi ===\n";
    echo "Szablon: {$template->name}\n";
    echo "Liczba dni: {$template->duration_days}\n";
    echo "Transfer km: {$template->transfer_km}\n";
    echo "Program km: {$template->program_km}\n";
    echo "Autokar: " . ($template->bus ? $template->bus->name : 'Brak') . "\n";
    echo "Markup: " . ($template->markup ? $template->markup->name : 'Brak') . "\n";
    echo "Punkty programu: {$template->programPoints->count()}\n";

    // Utwórz imprezę
    $eventData = [
        'name' => 'Test Impreza Rozszerzona',
        'client_name' => 'Test Klient',
        'client_email' => 'test@example.com',
        'start_date' => '2025-07-01',
        'end_date' => '2025-07-03',
        'participant_count' => 25,
    ];

    $event = Event::createFromTemplate($template, $eventData);

    echo "\n=== Utworzona impreza ===\n";
    echo "ID: {$event->id}\n";
    echo "Nazwa: {$event->name}\n";
    echo "Liczba dni: {$event->duration_days}\n";
    echo "Transfer km: {$event->transfer_km}\n";
    echo "Program km: {$event->program_km}\n";
    echo "Autokar: " . ($event->bus ? $event->bus->name : 'Brak') . "\n";
    echo "Markup: " . ($event->markup ? $event->markup->name : 'Brak') . "\n";
    echo "Punkty programu: {$event->programPoints->count()}\n";
    echo "Koszt całkowity: {$event->total_cost} PLN\n";

    // Sprawdź snapshoty
    $snapshots = $event->snapshots;
    echo "Snapshoty: {$snapshots->count()}\n";

    if ($snapshots->count() > 0) {
        $originalSnapshot = $event->originalSnapshot;
        echo "Pierwotny snapshot: " . ($originalSnapshot ? $originalSnapshot->name : 'Brak') . "\n";
    }

    echo "\n=== Test zakończony pomyślnie ===\n";

} catch (Exception $e) {
    echo "Błąd: " . $e->getMessage() . "\n";
    echo "Plik: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
