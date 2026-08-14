<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EventTemplate;
use App\Models\Place;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Publiczny katalog ofert (storefront) — odczyt bez auth.
 * Nie duplikuje pełnego frontu (Turnstile, cookies); daje stabilny kontrakt mobilny.
 */
class PublicPackageCatalogService
{
    /**
     * @param  array{search?: string, duration_days?: int, start_place_id?: int, per_page?: int}  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = EventTemplate::query()
            ->active()
            ->with([
                'eventTypes:id,name',
                'transportTypes:id,name',
                'tags:id,name',
            ]);

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('subtitle', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['duration_days'])) {
            $query->where('duration_days', (int) $filters['duration_days']);
        }

        if (! empty($filters['start_place_id'])) {
            $startPlaceId = (int) $filters['start_place_id'];
            $query->where(function (Builder $builder) use ($startPlaceId): void {
                $builder->whereHas('startingPlaceAvailabilities', function (Builder $availability) use ($startPlaceId): void {
                    $availability->where('start_place_id', $startPlaceId)->where('available', true);
                })->orWhere('start_place_id', $startPlaceId);
            });
        }

        $perPage = (int) ($filters['per_page'] ?? 20);

        return $query
            ->orderBy('name')
            ->paginate(max(1, min(100, $perPage)));
    }

    public function findByIdOrSlug(string $idOrSlug): ?EventTemplate
    {
        $query = EventTemplate::query()
            ->active()
            ->with([
                'eventTypes:id,name',
                'transportTypes:id,name',
                'tags:id,name',
                'programPoints' => fn ($q) => $q->orderBy('day')->orderBy('order'),
                'startingPlaceAvailabilities.startPlace:id,name',
            ]);

        if (ctype_digit($idOrSlug)) {
            return $query->whereKey((int) $idOrSlug)->first();
        }

        return $query->where('slug', $idOrSlug)->first();
    }

    /**
     * @return Collection<int, Place>
     */
    public function startingPlaces(): Collection
    {
        return Place::query()
            ->startingPlaces()
            ->orderBy('name')
            ->get(['id', 'name', 'latitude', 'longitude']);
    }
}
