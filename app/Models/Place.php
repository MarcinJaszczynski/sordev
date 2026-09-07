<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Place extends Model
{
    use HasFactory;

    protected $table = 'places';

    protected $fillable = [
        'name',
        'description',
        'tags',
        'starting_place',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'starting_place' => 'boolean',
        'tags' => 'array',
    ];

    /**
     * Relacja jeden-do-wielu z dostępnością miejsc startowych
     */
    public function startingPlaceAvailabilities()
    {
        return $this->hasMany(\App\Models\EventTemplateStartingPlaceAvailability::class, 'start_place_id');
    }

    public function scopeStartingPlaces(Builder $query): Builder
    {
        if (! Schema::hasColumn($this->getTable(), 'starting_place')) {
            return $query;
        }

        return $query->where('starting_place', true);
    }

    /**
     * Wyszukiwanie miejsc do selectów Filament — wyłącznie tabela places, tylko po nazwie.
     * Dopasowanie bez względu na polskie znaki (krakow → Kraków), bez fuzzy „podobnych” miast.
     *
     * @return array<int|string, string>
     */
    public static function searchSelectOptions(string $search, int $limit = 40): array
    {
        $term = trim($search);
        $limit = max(1, min(500, $limit));

        $places = static::query()
            ->whereNotNull('name')
            ->whereRaw("TRIM(name) != ''")
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name']);

        if ($term !== '') {
            $foldedTerm = static::foldSearchValue($term);

            // Za krótkie frazy dają szum (szczególnie w Choices na szerokim viewportcie).
            if (mb_strlen($foldedTerm) < 2) {
                return [];
            }

            $places = $places
                ->map(function (self $place) use ($term, $foldedTerm): ?array {
                    $name = trim((string) $place->name);
                    $foldedName = static::foldSearchValue($name);

                    $rank = null;
                    if (strcasecmp($name, $term) === 0 || $foldedName === $foldedTerm) {
                        $rank = 0;
                    } elseif (str_starts_with($foldedName, $foldedTerm)) {
                        $rank = 1;
                    } elseif (str_contains($foldedName, $foldedTerm)) {
                        $rank = 2;
                    }

                    if ($rank === null) {
                        return null;
                    }

                    return [
                        'place' => $place,
                        'rank' => $rank,
                        'name' => $name,
                    ];
                })
                ->filter()
                ->sortBy([
                    ['rank', 'asc'],
                    ['name', 'asc'],
                ])
                ->take($limit)
                ->pluck('place')
                ->values();
        } else {
            $places = $places->take($limit);
        }

        return static::formatSelectOptions($places);
    }

    /**
     * Normalizacja do porównań: małe litery + bez polskich diakrytyków.
     */
    public static function foldSearchValue(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');

        return strtr($value, [
            'ą' => 'a',
            'ć' => 'c',
            'ę' => 'e',
            'ł' => 'l',
            'ń' => 'n',
            'ó' => 'o',
            'ś' => 's',
            'ź' => 'z',
            'ż' => 'z',
        ]);
    }

    public static function optionLabel(?int $placeId): ?string
    {
        if (! $placeId) {
            return null;
        }

        $place = static::query()->whereKey($placeId)->first(['id', 'name']);
        if (! $place || blank($place->name)) {
            return null;
        }

        $isDuplicate = static::query()
            ->where('name', $place->name)
            ->whereKeyNot($place->id)
            ->exists();

        return $isDuplicate
            ? sprintf('%s (#%d)', $place->name, $place->id)
            : $place->name;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, self>|\Illuminate\Database\Eloquent\Collection<int, self>  $places
     * @return array<int|string, string>
     */
    protected static function formatSelectOptions($places): array
    {
        if ($places->isEmpty()) {
            return [];
        }

        $names = $places->pluck('name')->unique()->filter()->values()->all();

        $duplicateNames = static::query()
            ->select('name')
            ->whereIn('name', $names)
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('name')
            ->flip()
            ->all();

        $options = [];

        foreach ($places as $place) {
            $name = trim((string) $place->name);
            if ($name === '') {
                continue;
            }

            $options[$place->id] = isset($duplicateNames[$place->name]) || isset($duplicateNames[$name])
                ? sprintf('%s (#%d)', $name, $place->id)
                : $name;
        }

        return $options;
    }

    /**
     * Opcje selecta dla miejsc podstawienia autokaru (punkty startowe imprez).
     *
     * @param  int|null  $includePlaceId  Zachowaj bieżącą wartość przy edycji (np. legacy).
     */
    public static function startingPlaceSelectOptions(?int $includePlaceId = null): array
    {
        $query = static::query();

        if (Schema::hasColumn((new static)->getTable(), 'starting_place')) {
            $query->where(function (Builder $inner) use ($includePlaceId): void {
                $inner->where('starting_place', true);

                if ($includePlaceId) {
                    $inner->orWhere('id', $includePlaceId);
                }
            });
        }

        return $query->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * Opcje selecta: punkty startowe dostępne dla szablonu (lub wszystkie startowe bez szablonu).
     * Przy ustawionym szablonie — wyłącznie availability available=true (pusta lista gdy brak).
     *
     * @param  int|null  $includePlaceId  Zachowaj bieżącą wartość przy edycji (np. legacy).
     */
    public static function startingPlaceSelectOptionsForTemplate(?int $templateId = null, ?int $includePlaceId = null): array
    {
        $allowedIds = null;

        if ($templateId && $templateId > 0) {
            $template = EventTemplate::query()->find($templateId);
            if ($template) {
                $allowedIds = $template->resolveAvailableStartPlaceIds();
            }
        }

        $query = static::query();

        if (Schema::hasColumn((new static)->getTable(), 'starting_place')) {
            $query->where(function (Builder $inner) use ($allowedIds, $includePlaceId): void {
                if ($allowedIds !== null) {
                    if ($allowedIds->isNotEmpty()) {
                        $inner->whereIn('id', $allowedIds);
                    } else {
                        // Szablon bez dostępnych miejsc — pusta lista (poza legacy include).
                        $inner->whereRaw('0 = 1');
                    }
                } else {
                    $inner->where('starting_place', true);
                }

                if ($includePlaceId) {
                    $inner->orWhere('id', $includePlaceId);
                }
            });
        } elseif ($allowedIds !== null) {
            if ($allowedIds->isNotEmpty()) {
                $query->whereIn('id', $allowedIds);
            } else {
                $query->whereRaw('0 = 1');
            }

            if ($includePlaceId) {
                $query->orWhere('id', $includePlaceId);
            }
        }

        return $query->orderBy('name')->pluck('name', 'id')->all();
    }
}
