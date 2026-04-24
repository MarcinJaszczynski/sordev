<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventStartingPlaceAvailability extends Model
{
    protected $table = 'event_starting_place_availability';

    protected $fillable = [
        'event_id',
        'start_place_id',
        'end_place_id',
        'available',
        'note',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function startPlace()
    {
        return $this->belongsTo(Place::class, 'start_place_id');
    }

    public function endPlace()
    {
        return $this->belongsTo(Place::class, 'end_place_id');
    }
}
