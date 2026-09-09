<?php

namespace App\Console\Commands;

use App\Services\PlaceDistanceRouteService;
use App\Support\OpenRouteServiceSettings;
use Illuminate\Console\Command;

class RecalculatePlaceDistances extends Command
{
    protected $signature = 'places:recalculate-distances
        {--force : Nadpisz także istniejące odległości drogowe}
        {--estimates-only : Tylko haversine_estimate / symmetric_copy / puste źródło}
        {--sleep= : Opóźnienie między requestami ORS (ms); domyślnie z ustawień panelu}';

    protected $description = 'Przelicza odległości między miejscami na trasy drogowe (OpenRouteService)';

    public function handle(PlaceDistanceRouteService $routes): int
    {
        if ($routes->apiKey() === null) {
            $this->error('Brak klucza ORS — ustaw w panelu (System → OpenRouteService) albo OPENROUTESERVICE_API_KEY w .env');

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');
        $estimatesOnly = (bool) $this->option('estimates-only');
        $sleepOpt = $this->option('sleep');
        $sleepMs = $sleepOpt !== null && $sleepOpt !== ''
            ? max(0, (int) $sleepOpt)
            : OpenRouteServiceSettings::limits()['min_interval_ms'];

        $usage = $routes->usageSnapshot();
        $this->info('Start przeliczania odległości drogowych…');
        $this->line("Limit dziś: {$usage['daily_used']}/{$usage['daily_limit']}, odstęp: {$sleepMs} ms");

        $result = $routes->recalculateAll(
            force: $force || $estimatesOnly,
            estimatesOnly: $estimatesOnly,
            sleepMs: $sleepMs,
            stopOnLimit: true,
        );

        $this->info("Łącznie: {$result['total']}");
        $this->info("Zaktualizowano: {$result['updated']}");
        $this->info("Pominięto: {$result['skipped']}");
        $this->info("Nieudane: {$result['failed']}");
        if ($result['limit_hit'] ?? false) {
            $this->warn("Limit ORS wyczerpany — pozostało kandydatów: {$result['remaining_candidates']}. Uruchom ponownie później.");
        }

        return $result['failed'] > 0 && $result['updated'] === 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
