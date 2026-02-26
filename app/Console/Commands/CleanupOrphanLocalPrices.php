<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EventTemplatePricePerPerson;
use App\Models\EventTemplateStartingPlaceAvailability;

class CleanupOrphanLocalPrices extends Command
{
    protected $signature = 'prices:cleanup-orphans {--dry-run : Pokaż tylko ile rekordów zostanie usuniętych}';
    protected $description = 'Usuwa lokalne ceny (start_place_id != null) bez odpowiadającej dostępności available=1.';

    public function handle(): int
    {
        $availablePairs = EventTemplateStartingPlaceAvailability::where('available', true)
            ->get(['event_template_id', 'start_place_id'])
            ->map(fn($r) => $r->event_template_id . ':' . $r->start_place_id)
            ->flip();

        $orphans = EventTemplatePricePerPerson::whereNotNull('start_place_id')
            ->where('price_per_person', '>', 0)
            ->get()
            ->filter(function ($p) use ($availablePairs) {
                $key = $p->event_template_id . ':' . $p->start_place_id;
                return !$availablePairs->has($key);
            });

        $count = $orphans->count();
        if ($count === 0) {
            $this->info('Brak sierot do usunięcia.');
            return Command::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("[DRY-RUN] Znalazłem $count sierot (nic nie usuwam).");
            return Command::SUCCESS;
        }

        $ids = $orphans->pluck('id')->toArray();
        EventTemplatePricePerPerson::whereIn('id', $ids)->delete();
        $this->info("Usunięto $count sierot.");
        return Command::SUCCESS;
    }
}
