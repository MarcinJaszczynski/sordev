<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Event;

class CheckEventBus extends Command
{
    protected $signature = 'check:event-bus {id=7}';
    protected $description = 'Sprawdź dane autokaru dla imprezy';

    public function handle()
    {
        $eventId = $this->argument('id');
        $event = Event::with(['bus', 'markup'])->find($eventId);

        if (!$event) {
            $this->error("Nie znaleziono imprezy o ID {$eventId}");
            return 1;
        }

        $this->info("=== Impreza ID: {$event->id} ===");
        $this->line("Nazwa: {$event->name}");
        $this->line("Bus ID: {$event->bus_id}");
        
        if ($event->bus) {
            $this->info("=== Autokar ===");
            $this->line("Nazwa: {$event->bus->name}");
            $this->line("Cena za dzień: {$event->bus->package_price_per_day}");
            $this->line("Km za dzień: {$event->bus->package_km_per_day}");
            $this->line("Cena za dodatkowy km: {$event->bus->extra_km_price}");
            $this->line("Waluta: {$event->bus->currency}");
        } else {
            $this->error("Brak autokaru!");
        }
        
        if ($event->markup) {
            $this->info("=== Markup ===");
            $this->line("Nazwa: {$event->markup->name}");
        } else {
            $this->error("Brak markup!");
        }

        $this->line("\n=== Transport ===");
        $this->line("Transfer km: {$event->transfer_km}");
        $this->line("Program km: {$event->program_km}");
        $this->line("Liczba dni: {$event->duration_days}");

        return 0;
    }
}
