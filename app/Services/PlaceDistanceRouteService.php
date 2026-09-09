<?php

namespace App\Services;

use App\Models\Place;
use App\Models\PlaceDistance;
use App\Support\OpenRouteServiceSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Odległości drogowe (ORS Directions driving-car) z throttlingiem.
 * Haversine zostaje tylko w PlaceDistanceGenerator jako szybki placeholder.
 */
class PlaceDistanceRouteService
{
    public const SOURCE_ORS = 'openrouteservice';

    public const SOURCE_HAVERSINE = 'haversine_estimate';

    public const SOURCE_SYMMETRIC = 'symmetric_copy';

    public const SOURCE_MANUAL = 'manual';

    public function apiKey(): ?string
    {
        return OpenRouteServiceSettings::apiKey();
    }

    /**
     * @return array{
     *     daily_used: int,
     *     daily_limit: int,
     *     daily_remaining: int,
     *     minute_used: int,
     *     requests_per_minute: int,
     *     min_interval_ms: int
     * }
     */
    public function usageSnapshot(): array
    {
        $limits = OpenRouteServiceSettings::limits();
        $dailyUsed = (int) Cache::get($this->dailyCacheKey(), 0);
        $minuteUsed = (int) Cache::get($this->minuteCacheKey(), 0);

        return [
            'daily_used' => $dailyUsed,
            'daily_limit' => $limits['daily_limit'],
            'daily_remaining' => max(0, $limits['daily_limit'] - $dailyUsed),
            'minute_used' => $minuteUsed,
            'requests_per_minute' => $limits['requests_per_minute'],
            'min_interval_ms' => $limits['min_interval_ms'],
        ];
    }

    public function fetchDistanceKm(?Place $from, ?Place $to): ?float
    {
        if (! $from || ! $to) {
            return null;
        }

        if (! $from->latitude || ! $from->longitude || ! $to->latitude || ! $to->longitude) {
            return null;
        }

        $apiKey = $this->apiKey();
        if ($apiKey === null) {
            return null;
        }

        if (! $this->acquireRequestSlot(blocking: true)) {
            Log::warning('ORS daily/minute limit reached — skip request', [
                'from' => $from->id,
                'to' => $to->id,
                'usage' => $this->usageSnapshot(),
            ]);

            return null;
        }

        $url = 'https://api.openrouteservice.org/v2/directions/driving-car';

        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->get($url, [
                    'api_key' => $apiKey,
                    'start' => $from->longitude.','.$from->latitude,
                    'end' => $to->longitude.','.$to->latitude,
                ]);

            $this->recordSuccessfulSlot();

            if ($response->status() === 429) {
                Log::warning('ORS 429 rate limited', ['from' => $from->id, 'to' => $to->id]);
                sleep(60);

                if (! $this->acquireRequestSlot(blocking: false)) {
                    return null;
                }

                $response = Http::timeout(20)
                    ->acceptJson()
                    ->get($url, [
                        'api_key' => $apiKey,
                        'start' => $from->longitude.','.$from->latitude,
                        'end' => $to->longitude.','.$to->latitude,
                    ]);
                $this->recordSuccessfulSlot();
            }

            if (! $response->successful()) {
                Log::warning('ORS directions failed', [
                    'status' => $response->status(),
                    'from' => $from->id,
                    'to' => $to->id,
                ]);

                return null;
            }

            $meters = data_get($response->json(), 'features.0.properties.segments.0.distance');
            if ($meters === null) {
                return null;
            }

            return round(((float) $meters) / 1000, 2);
        } catch (\Throwable $e) {
            Log::warning('ORS directions exception: '.$e->getMessage(), [
                'from' => $from->id,
                'to' => $to->id,
            ]);

            return null;
        }
    }

    /**
     * @param  Collection<int, PlaceDistance|int|string>  $records
     */
    public function recalculateCollection(Collection $records, bool $force = false, ?int $sleepMs = null): int
    {
        $sleepMs ??= OpenRouteServiceSettings::limits()['min_interval_ms'];
        $updated = 0;

        foreach ($records as $record) {
            if (! $record instanceof PlaceDistance) {
                $record = PlaceDistance::query()->find($record);
            }

            if (! $record) {
                continue;
            }

            if (! $force && $record->distance_km) {
                continue;
            }

            if ($this->usageSnapshot()['daily_remaining'] <= 0) {
                break;
            }

            if ($this->updateRecordFromRoute($record)) {
                $updated++;
            }

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        return $updated;
    }

    public function updateRecordFromRoute(PlaceDistance $record): bool
    {
        $record->loadMissing(['fromPlace', 'toPlace']);
        $distance = $this->fetchDistanceKm($record->fromPlace, $record->toPlace);

        if ($distance === null) {
            return false;
        }

        $record->update([
            'distance_km' => $distance,
            'api_source' => self::SOURCE_ORS,
        ]);

        return true;
    }

    /**
     * @return array{updated: int, skipped: int, failed: int, total: int, limit_hit: bool, remaining_candidates: int}
     */
    public function recalculateAll(
        bool $force = true,
        bool $estimatesOnly = false,
        ?int $sleepMs = null,
        bool $stopOnLimit = true,
    ): array {
        $sleepMs ??= OpenRouteServiceSettings::limits()['min_interval_ms'];
        $query = PlaceDistance::query()->with(['fromPlace', 'toPlace']);

        if ($estimatesOnly) {
            $query->where(function ($q) {
                $q->whereNull('api_source')
                    ->orWhereIn('api_source', [self::SOURCE_HAVERSINE, self::SOURCE_SYMMETRIC]);
            });
        } elseif (! $force) {
            $query->where(function ($q) {
                $q->whereNull('distance_km')->orWhere('distance_km', '<=', 0);
            });
        }

        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $total = 0;
        $limitHit = false;
        $remainingCandidates = 0;

        $query->orderBy('id')->chunkById(50, function (Collection $chunk) use (
            $force,
            $sleepMs,
            $stopOnLimit,
            &$updated,
            &$skipped,
            &$failed,
            &$total,
            &$limitHit,
            &$remainingCandidates,
        ) {
            if ($limitHit) {
                $remainingCandidates += $chunk->count();

                return;
            }

            foreach ($chunk as $record) {
                $total++;

                if (! $force && $record->distance_km) {
                    $skipped++;

                    continue;
                }

                if ($this->usageSnapshot()['daily_remaining'] <= 0) {
                    $limitHit = true;
                    $remainingCandidates++;

                    if ($stopOnLimit) {
                        continue;
                    }
                }

                if ($limitHit && $stopOnLimit) {
                    $remainingCandidates++;

                    continue;
                }

                if ($this->updateRecordFromRoute($record)) {
                    $updated++;
                } else {
                    $failed++;
                    if ($this->usageSnapshot()['daily_remaining'] <= 0) {
                        $limitHit = true;
                    }
                }

                if ($sleepMs > 0) {
                    usleep($sleepMs * 1000);
                }
            }
        });

        return [
            'updated' => $updated,
            'skipped' => $skipped,
            'failed' => $failed,
            'total' => $total,
            'limit_hit' => $limitHit,
            'remaining_candidates' => $remainingCandidates,
        ];
    }

    /**
     * @param  Collection<int, PlaceDistance|int|string>  $records
     */
    public function setManualDistance(Collection $records, float $value): int
    {
        $count = 0;

        foreach ($records as $record) {
            if (! $record instanceof PlaceDistance) {
                $record = PlaceDistance::query()->find($record);
            }

            if (! $record) {
                continue;
            }

            $record->update([
                'distance_km' => $value,
                'api_source' => self::SOURCE_MANUAL,
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * Czeka na min. odstęp / minutowy limit. False = dzienny limit wyczerpany.
     */
    public function acquireRequestSlot(bool $blocking = true): bool
    {
        $limits = OpenRouteServiceSettings::limits();
        $dailyUsed = (int) Cache::get($this->dailyCacheKey(), 0);
        if ($dailyUsed >= $limits['daily_limit']) {
            return false;
        }

        $attempts = 0;
        while ($attempts < 90) {
            $minuteUsed = (int) Cache::get($this->minuteCacheKey(), 0);
            if ($minuteUsed < $limits['requests_per_minute']) {
                break;
            }

            if (! $blocking) {
                return false;
            }

            sleep(1);
            $attempts++;
        }

        if ($attempts >= 90) {
            return false;
        }

        $last = Cache::get($this->lastRequestCacheKey());
        if (is_numeric($last) && $limits['min_interval_ms'] > 0) {
            $elapsedMs = (int) ((microtime(true) - (float) $last) * 1000);
            $waitMs = $limits['min_interval_ms'] - $elapsedMs;
            if ($waitMs > 0) {
                usleep($waitMs * 1000);
            }
        }

        return true;
    }

    public function recordSuccessfulSlot(): void
    {
        $limits = OpenRouteServiceSettings::limits();

        Cache::put($this->lastRequestCacheKey(), microtime(true), now()->addHour());

        $dailyKey = $this->dailyCacheKey();
        if (! Cache::has($dailyKey)) {
            Cache::put($dailyKey, 0, now()->endOfDay());
        }
        Cache::increment($dailyKey);

        $minuteKey = $this->minuteCacheKey();
        if (! Cache::has($minuteKey)) {
            Cache::put($minuteKey, 0, now()->addMinutes(2));
        }
        Cache::increment($minuteKey);

        // Soft guard — nie pozwól przekroczyć dziennego w race.
        if ((int) Cache::get($dailyKey, 0) > $limits['daily_limit'] + 5) {
            Log::warning('ORS daily counter exceeded soft guard', $this->usageSnapshot());
        }
    }

    private function dailyCacheKey(): string
    {
        return 'ors:requests:daily:'.now()->format('Y-m-d');
    }

    private function minuteCacheKey(): string
    {
        return 'ors:requests:minute:'.now()->format('Y-m-d-H-i');
    }

    private function lastRequestCacheKey(): string
    {
        return 'ors:last_request_at';
    }
}
