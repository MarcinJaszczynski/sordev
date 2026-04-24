<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
