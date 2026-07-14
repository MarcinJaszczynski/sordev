<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\EventProgramPointOrderService;
use App\Services\TemplateProgramPointCopier;
use Illuminate\Console\Command;

class RepairEventProgramOrderCommand extends Command
{
    protected $signature = 'event:repair-program-order
                            {event : ID imprezy}
                            {--sync-template : Przywróć day/order i sety ze szablonu (zalecane)}
                            {--fill-missing : Uzupełnij brakujące sloty programu ze szablonu}
                            {--renumber-only : Tylko ponumeruj bieżącą kolejność (bez szablonu)}';

    protected $description = 'Naprawia kolejność punktów programu (dzień → rodzice → dzieci w secie)';

    public function handle(EventProgramPointOrderService $service, TemplateProgramPointCopier $copier): int
    {
        $event = Event::query()->find($this->argument('event'));

        if (! $event) {
            $this->error('Nie znaleziono imprezy.');

            return self::FAILURE;
        }

        if ($this->option('fill-missing')) {
            $filled = $copier->fillMissingFromTemplate($event);
            $this->info("Uzupełniono brakujące sloty ze szablonu ({$filled} nowych punktów).");
            $event = $event->fresh();
        }

        if ($this->option('renumber-only')) {
            $updated = $service->repairEvent($event);
            $this->info("Ponumerowano kolejność ({$updated} zmian).");
        } else {
            $updated = $service->syncOrderFromTemplate($event);
            $this->info("Przywrócono ze szablonu ({$updated} zmian).");
        }

        $this->info("Impreza #{$event->id}: {$event->name}");

        $display = $service->sortedForDisplay($event);
        foreach ($display as $point) {
            $prefix = $point->parent_id ? '  ↳ ' : '';
            $name = $point->name ?? $point->templatePoint?->name ?? ('#'.$point->id);
            $this->line(sprintf(
                '%sDzień %d | %s%s | order %d',
                $prefix,
                (int) $point->day,
                $prefix ? '' : '',
                $name,
                (int) $point->order
            ));
        }

        return self::SUCCESS;
    }
}
