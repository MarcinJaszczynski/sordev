<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EventTemplate;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class TestEventCreation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:event-creation';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test tworzenia imprezy z rozszerzonymi danymi';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            // Symuluj zalogowanego użytkownika
            $user = User::first();
            if ($user) {
                Auth::login($user);
                $this->info("Zalogowano użytkownika: {$user->name}");
            } else {
                $this->error("Brak użytkowników w bazie");
                return 1;
            }

            // Znajdź szablon z punktami programu
            $template = EventTemplate::with(['programPoints.children', 'bus', 'markup'])->find(2);

            if (!$template) {
                $this->error("Nie znaleziono szablonu o ID 2");
                return 1;
            }

            $this->line("=== Test tworzenia imprezy z rozszerzonymi danymi ===");
            $this->info("Szablon: {$template->name}");
            $this->line("Liczba dni: {$template->duration_days}");
            $this->line("Transfer km: {$template->transfer_km}");
            $this->line("Program km: {$template->program_km}");
            $this->line("Autokar: " . ($template->bus ? $template->bus->name : 'Brak'));
            $this->line("Markup: " . ($template->markup ? $template->markup->name : 'Brak'));
            $this->line("Punkty programu: {$template->programPoints->count()}");

            // Sprawdź podpunkty
            $childrenCount = 0;
            foreach ($template->programPoints as $point) {
                $childrenCount += $point->children->count();
            }
            $this->line("Podpunkty łącznie: {$childrenCount}");

            // Utwórz imprezę
            $eventData = [
                'name' => 'Test Impreza Rozszerzona ' . now()->format('H:i:s'),
                'client_name' => 'Test Klient',
                'client_email' => 'test@example.com',
                'start_date' => '2025-07-01',
                'end_date' => '2025-07-03',
                'participant_count' => 25,
            ];

            $event = Event::createFromTemplate($template, $eventData);

            $this->line("\n=== Utworzona impreza ===");
            $this->info("ID: {$event->id}");
            $this->line("Nazwa: {$event->name}");
            $this->line("Liczba dni: {$event->duration_days}");
            $this->line("Transfer km: {$event->transfer_km}");
            $this->line("Program km: {$event->program_km}");
            $this->line("Autokar: " . ($event->bus ? $event->bus->name : 'Brak'));
            $this->line("Markup: " . ($event->markup ? $event->markup->name : 'Brak'));
            $this->line("Punkty programu: {$event->programPoints->count()}");
            $this->line("Koszt całkowity: {$event->total_cost} PLN");

            // Sprawdź snapshoty
            $snapshots = $event->snapshots;
            $this->line("Snapshoty: {$snapshots->count()}");

            if ($snapshots->count() > 0) {
                $originalSnapshot = $event->originalSnapshot;
                $this->line("Pierwotny snapshot: " . ($originalSnapshot ? $originalSnapshot->name : 'Brak'));
            }

            // Pokaż punkty programu
            $this->line("\n=== Punkty programu ===");
            foreach ($event->programPoints as $point) {
                $label = "Dzień {$point->day}, Kolejność {$point->order}: {$point->templatePoint->name} - {$point->total_price} PLN";
                if (strpos($point->notes ?? '', 'Podpunkt:') !== false) {
                    $label .= " (PODPUNKT)";
                }
                $this->line($label);
            }

            $this->info("\n=== Test zakończony pomyślnie ===");
            return 0;

        } catch (\Exception $e) {
            $this->error("Błąd: " . $e->getMessage());
            $this->line("Plik: " . $e->getFile() . ":" . $e->getLine());
            return 1;
        }
    }
}
