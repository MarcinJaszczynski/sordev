<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\EventProgramPoint;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestEventDragDropFixed extends Command
{
    protected $signature = 'test:event-dragdrop-fixed';
    protected $description = 'Test drag&drop Event po naprawach';

    public function handle()
    {
        $this->info('=== Test Event Drag&Drop (po naprawach) ===');
        
        // Znajdź pierwszego eventa z program points
        $event = Event::whereHas('programPoints')->first();
        
        if (!$event) {
            $this->error('Brak eventów z punktami programu');
            return;
        }
        
        $this->info("Event: {$event->name} (ID: {$event->id})");
        
        // Pokaż punkty przed
        $this->info("\n=== Program przed testem ===");
        $points = $event->programPoints()->orderBy('day')->orderBy('order')->get();
        
        foreach ($points as $point) {
            $this->line("Dzień {$point->day}, Order: {$point->order}, Punkt: {$point->templatePoint->name}");
        }
        
        // Symulacja drag&drop - weźmy pierwsze 3 punkty i przestawmy ich kolejność
        $testPoints = $points->take(3);
        if ($testPoints->count() < 3) {
            $this->error('Potrzeba minimum 3 punktów do testu');
            return;
        }
        
        $this->info("\n=== Symulacja drag&drop ===");
        $this->info("Przestawiamy kolejność pierwszych 3 punktów...");
        
        // Kolejność przed: A, B, C
        // Kolejność po: C, A, B
        $newOrder = [
            $testPoints->get(2)->id, // C na pierwszym miejscu
            $testPoints->get(0)->id, // A na drugim miejscu
            $testPoints->get(1)->id, // B na trzecim miejscu
        ];
        
        $this->info("Nowa kolejność ID: " . implode(', ', $newOrder));
        
        // Grupujemy według dni (tak jak w reorderTable)
        $recordsByDay = [];
        foreach ($newOrder as $recordId) {
            $record = EventProgramPoint::find($recordId);
            if ($record) {
                $recordsByDay[$record->day][] = $recordId;
            }
        }
        
        // Aktualizujemy kolejność w obrębie każdego dnia
        foreach ($recordsByDay as $day => $recordIds) {
            $this->info("Dzień {$day}: aktualizujemy kolejność...");
            foreach ($recordIds as $index => $recordId) {
                $newOrderValue = $index + 1;
                $result = EventProgramPoint::where('id', $recordId)
                    ->update(['order' => $newOrderValue]);
                $this->info("  Punkt ID {$recordId} -> order {$newOrderValue} (wynik: {$result})");
            }
        }
        
        // Pokaż punkty po
        $this->info("\n=== Program po teście ===");
        $pointsAfter = $event->programPoints()->orderBy('day')->orderBy('order')->get();
        
        foreach ($pointsAfter as $point) {
            $this->line("Dzień {$point->day}, Order: {$point->order}, Punkt: {$point->templatePoint->name}");
        }
        
        $this->info("\n=== Test zakończony ===");
        $this->info("Sprawdź w panelu admin czy kolejność została zapisana poprawnie.");
    }
}
