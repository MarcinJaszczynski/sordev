<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Event;

class TestEventDragDrop extends Command
{
    protected $signature = 'test:event-dragdrop {eventId=7}';
    protected $description = 'Test przestawiania kolejności punktów programu';

    public function handle()
    {
        $eventId = $this->argument('eventId');
        $event = Event::with('programPoints.templatePoint')->find($eventId);

        if (!$event) {
            $this->error("Nie znaleziono imprezy o ID {$eventId}");
            return 1;
        }

        $this->info("=== Test drag&drop dla Event ID: {$event->id} ===");
        $this->line("Nazwa: {$event->name}");
        $this->line("");

        $points = $event->programPoints()->orderBy('day')->orderBy('order')->get();
        
        $this->info("Aktualna kolejność punktów:");
        foreach ($points as $point) {
            $this->line("ID: {$point->id} | Dzień: {$point->day} | Kolejność: {$point->order} | {$point->templatePoint->name}");
        }
        
        if ($points->count() >= 2) {
            $this->line("");
            $this->info("Test zamiany kolejności dwóch pierwszych punktów...");
            
            $firstPoint = $points->first();
            $secondPoint = $points->skip(1)->first();
            
            if ($firstPoint && $secondPoint && $firstPoint->day == $secondPoint->day) {
                // Zamień kolejność
                $tempOrder = $firstPoint->order;
                $firstPoint->update(['order' => $secondPoint->order]);
                $secondPoint->update(['order' => $tempOrder]);
                
                $this->info("Zamieniono kolejność punktów:");
                $this->line("- {$firstPoint->templatePoint->name}: {$tempOrder} -> {$firstPoint->order}");
                $this->line("- {$secondPoint->templatePoint->name}: {$secondPoint->order} -> {$tempOrder}");
                
                // Pokaż nową kolejność
                $newPoints = $event->fresh()->programPoints()->orderBy('day')->orderBy('order')->get();
                $this->line("");
                $this->info("Nowa kolejność:");
                foreach ($newPoints as $point) {
                    $this->line("ID: {$point->id} | Dzień: {$point->day} | Kolejność: {$point->order} | {$point->templatePoint->name}");
                }
            } else {
                $this->warn("Nie można zamienić - punkty są w różnych dniach lub brak drugiego punktu");
            }
        } else {
            $this->warn("Za mało punktów do testowania (minimum 2)");
        }

        return 0;
    }
}
