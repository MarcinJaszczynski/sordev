<?php

namespace App\Models;

use App\Services\PlaceDistanceRouteService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class PlaceDistance extends Model
{
    protected $table = 'place_distances';

    protected $fillable = [
        'from_place_id',
        'to_place_id',
        'distance_km',
        'api_source',
    ];

    /**
     * @return array<string, string>
     */
    public static function sourceLabels(): array
    {
        return [
            PlaceDistanceRouteService::SOURCE_ORS => 'OpenRouteService (trasa)',
            PlaceDistanceRouteService::SOURCE_HAVERSINE => 'Formuła (szacunek)',
            PlaceDistanceRouteService::SOURCE_SYMMETRIC => 'Formuła (kopia lustrzana)',
            PlaceDistanceRouteService::SOURCE_MANUAL => 'Ręcznie',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function sourceColors(): array
    {
        return [
            PlaceDistanceRouteService::SOURCE_ORS => 'success',
            PlaceDistanceRouteService::SOURCE_HAVERSINE => 'warning',
            PlaceDistanceRouteService::SOURCE_SYMMETRIC => 'warning',
            PlaceDistanceRouteService::SOURCE_MANUAL => 'gray',
        ];
    }

    public static function sourceLabel(?string $source): string
    {
        if ($source === null || $source === '') {
            return 'Brak źródła';
        }

        return self::sourceLabels()[$source] ?? $source;
    }

    public static function sourceColor(?string $source): string
    {
        if ($source === null || $source === '') {
            return 'danger';
        }

        return self::sourceColors()[$source] ?? 'gray';
    }

    public function isFormulaEstimate(): bool
    {
        return in_array($this->api_source, [
            PlaceDistanceRouteService::SOURCE_HAVERSINE,
            PlaceDistanceRouteService::SOURCE_SYMMETRIC,
        ], true);
    }

    public function isOpenRoute(): bool
    {
        return $this->api_source === PlaceDistanceRouteService::SOURCE_ORS;
    }

    /**
     * @return list<array{source: ?string, label: string, count: int}>
     */
    public static function sourceStats(): array
    {
        if (! Schema::hasTable('place_distances')) {
            return [];
        }

        $stats = [];
        foreach (self::sourceLabels() as $source => $label) {
            $stats[] = [
                'source' => $source,
                'label' => $label,
                'count' => (int) static::query()->where('api_source', $source)->count(),
            ];
        }

        $nullCount = (int) static::query()->whereNull('api_source')->count();
        if ($nullCount > 0) {
            $stats[] = [
                'source' => null,
                'label' => 'Brak źródła',
                'count' => $nullCount,
            ];
        }

        $other = (int) static::query()
            ->whereNotNull('api_source')
            ->whereNotIn('api_source', array_keys(self::sourceLabels()))
            ->count();
        if ($other > 0) {
            $stats[] = [
                'source' => 'other',
                'label' => 'Inne',
                'count' => $other,
            ];
        }

        return $stats;
    }

    public function fromPlace()
    {
        return $this->belongsTo(Place::class, 'from_place_id');
    }

    public function toPlace()
    {
        return $this->belongsTo(Place::class, 'to_place_id');
    }
}
