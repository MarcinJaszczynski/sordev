<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlaceDistance extends Model
{
    protected $table = 'place_distances';

    protected $fillable = [
        'from_place_id',
        'to_place_id',
        'distance_km',
        'api_source',
    ];

    public function fromPlace()
    {
        return $this->belongsTo(Place::class, 'from_place_id');
    }

    public function toPlace()
    {
        return $this->belongsTo(Place::class, 'to_place_id');
    }
}
