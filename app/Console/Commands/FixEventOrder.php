<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Event;

class FixEventOrder extends Command
{
    protected $signature = 'fix:event-order {eventId=7}';
    protected $description = 'Napraw kolejność punktów programu w imprezie';

    public function handle()
    {
        $eventId = $this->argument('eventId');
        $event = Event::with('programPoints.templatePoint')->find($eventId);

        if (!$event) {
            $this->error("Nie znaleziono imprezy o ID {$eventId}");
            return 1;
        }

        $this->info("=== Naprawa kolejności dla Event ID: {$event->id} ===");
        
        // Pobierz punkty pogrupowane według dni
        $pointsByDay = $event->programPoints()
            ->with('templatePoint')
            ->get()
            ->groupBy('day');
        
        $fixed = 0;
        
        foreach ($pointsByDay as $day => $points) {
            $this->line("Dzień {$day}:");
            $newOrder = 1;
            
            foreach ($points->sortBy('order') as $point) {
                $oldOrder = $point->order;
                $point->update(['order' => $newOrder]);
                
                $this->line("  {$point->templatePoint->name}: {$oldOrder} -> {$newOrder}");
                $newOrder++;
                $fixed++;
            }
        }
        
        $this->info("Naprawiono kolejność dla {$fixed} punktów programu");
        
        // Pokaż nową kolejność
        $this->line("");
        $newPoints = $event->fresh()->programPoints()->orderBy('day')->orderBy('order')->get();
        $this->info("Nowa kolejność:");
        foreach ($newPoints as $point) {
            $this->line("ID: {$point->id} | Dzień: {$point->day} | Kolejność: {$point->order} | {$point->templatePoint->name}");
        }

        return 0;
    }
}
