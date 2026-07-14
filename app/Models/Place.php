<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
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
                if ($allowedIds !== null && $allowedIds->isNotEmpty()) {
                    $inner->whereIn('id', $allowedIds);
                } else {
                    $inner->where('starting_place', true);
                }

                if ($includePlaceId) {
                    $inner->orWhere('id', $includePlaceId);
                }
            });
        } elseif ($allowedIds !== null && $allowedIds->isNotEmpty()) {
            $query->whereIn('id', $allowedIds);

            if ($includePlaceId) {
                $query->orWhere('id', $includePlaceId);
            }
        }

        return $query->orderBy('name')->pluck('name', 'id')->all();
    }
}
