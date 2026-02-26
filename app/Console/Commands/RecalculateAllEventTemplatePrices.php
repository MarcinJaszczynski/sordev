<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EventTemplate;
use App\Models\Place;
use App\Filament\Resources\EventTemplateResource\Widgets\EventTemplatePriceTable;

class RecalculateAllEventTemplatePrices extends Command
{
    protected $signature = 'event-templates:recalculate-prices';
    protected $description = 'Przelicz ceny dla wszystkich szablonów i miejsc startowych nowym systemem oraz usuń duplikaty';

    public function handle()
    {
        $templates = EventTemplate::all();
        $places = Place::where('starting_place', true)->get();
        $total = 0;
        foreach ($templates as $template) {
            foreach ($places as $place) {
                $widget = new EventTemplatePriceTable();
                $widget->record = $template;
                $widget->startPlaceId = $place->id;
                $widget->recalculatePrices();
                $total++;
                $this->info("Przeliczono ceny dla szablonu #{$template->id} ({$template->name}), miejsce startowe: {$place->name}");
            }
        }
        // Usuwanie duplikatów
        EventTemplatePriceTable::removeDuplicatePrices();
        $this->info("Usunięto duplikaty cen. Przeliczono łącznie: {$total} kombinacji.");
        return 0;
    }
}
