<?php

namespace App\Console\Commands;

use App\Services\RepairProgramPointPlannedPriceService;
use Illuminate\Console\Command;

class RepairProgramPointPlannedPricesCommand extends Command
{
    protected $signature = 'event:repair-program-point-planned-prices
                            {--event= : Tylko ta impreza (ID)}
                            {--apply : Zapisz zmiany (domyślnie dry-run)}';

    protected $description = 'Naprawia planned_price zaseedowane jako cena za 1 osobę zamiast sumy dla imprezy';

    public function handle(RepairProgramPointPlannedPriceService $service): int
    {
        $eventId = $this->option('event') !== null && $this->option('event') !== ''
            ? (int) $this->option('event')
            : null;
        $apply = (bool) $this->option('apply');
        $dryRun = ! $apply;

        $candidates = $service->candidates($eventId);

        if ($candidates->isEmpty()) {
            $this->info('Brak punktów do naprawy.');

            return self::SUCCESS;
        }

        $this->table(
            ['Event', 'Point', 'Nazwa', 'Było (P)', 'Będzie (P)'],
            $candidates->map(fn (array $row): array => [
                $row['event_id'],
                $row['point']->id,
                mb_substr($row['name'], 0, 48),
                number_format($row['from'], 2, ',', ' '),
                number_format($row['to'], 2, ',', ' '),
            ])->all()
        );

        $result = $service->repair($eventId, $dryRun);

        if ($dryRun) {
            $this->warn("Dry-run: {$result['repaired']} pozycji. Uruchom z --apply, aby zapisać.");
        } else {
            $this->info("Naprawiono {$result['repaired']} pozycji (planned_price + sync rozliczenia).");
        }

        return self::SUCCESS;
    }
}
