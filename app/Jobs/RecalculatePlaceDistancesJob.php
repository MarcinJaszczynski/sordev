<?php

namespace App\Jobs;

use App\Services\PlaceDistanceRouteService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RecalculatePlaceDistancesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 3;

    public function __construct(
        public bool $force = true,
        public bool $estimatesOnly = true,
    ) {}

    public function handle(PlaceDistanceRouteService $routes): void
    {
        if ($routes->apiKey() === null) {
            Log::warning('RecalculatePlaceDistancesJob: brak klucza ORS');

            return;
        }

        $result = $routes->recalculateAll(
            force: $this->force,
            estimatesOnly: $this->estimatesOnly,
            sleepMs: null,
            stopOnLimit: true,
        );

        Log::info('RecalculatePlaceDistancesJob finished', $result + [
            'force' => $this->force,
            'estimates_only' => $this->estimatesOnly,
        ]);

        if (($result['limit_hit'] ?? false) && (($result['remaining_candidates'] ?? 0) > 0)) {
            self::dispatch($this->force, $this->estimatesOnly)
                ->delay(now()->addMinutes(5));
        }
    }
}
