<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Event;
use App\Models\EventTemplate;

class QuickDbQuery extends Command
{
    protected $signature = 'db:quick {query}';
    protected $description = 'Szybkie zapytania do bazy bez Tinkera';

    public function handle()
    {
        $query = $this->argument('query');
        
        try {
            switch ($query) {
                case 'events':
                    $events = Event::with(['eventTemplate', 'bus', 'markup'])
                        ->orderBy('created_at', 'desc')
                        ->take(5)
                        ->get();
                    
                    $this->info("=== Ostatnie 5 imprez ===");
                    foreach ($events as $event) {
                        $this->line("ID: {$event->id} | {$event->name} | Szablon: {$event->eventTemplate->name} | Koszt: {$event->total_cost} PLN");
                    }
                    break;
                    
                case 'templates':
                    $templates = EventTemplate::with(['bus', 'markup'])
                        ->orderBy('created_at', 'desc')
                        ->take(5)
                        ->get();
                    
                    $this->info("=== Szablony imprez ===");
                    foreach ($templates as $template) {
                        $busName = $template->bus ? $template->bus->name : 'Brak';
                        $this->line("ID: {$template->id} | {$template->name} | Dni: {$template->duration_days} | Bus: {$busName}");
                    }
                    break;
                    
                case 'last-event':
                    $event = Event::with(['programPoints.templatePoint'])
                        ->orderBy('created_at', 'desc')
                        ->first();
                    
                    if ($event) {
                        $this->info("=== Ostatnia impreza ===");
                        $this->line("ID: {$event->id}");
                        $this->line("Nazwa: {$event->name}");
                        $this->line("Punkty programu: {$event->programPoints->count()}");
                        $this->line("Koszt: {$event->total_cost} PLN");
                        
                        $this->line("\nPunkty programu:");
                        foreach ($event->programPoints as $point) {
                            $this->line("- Dzień {$point->day}: {$point->templatePoint->name} - {$point->total_price} PLN");
                        }
                    }
                    break;
                    
                default:
                    $this->error("Nieznane zapytanie. Dostępne: events, templates, last-event");
                    return 1;
            }
            
            return 0;
        } catch (\Exception $e) {
            $this->error("Błąd: " . $e->getMessage());
            return 1;
        }
    }
}
