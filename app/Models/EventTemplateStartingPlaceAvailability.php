<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventTemplateStartingPlaceAvailability extends Model
{
    use HasFactory;
    protected $table = 'event_template_starting_place_availability';
    protected $fillable = [
        'event_template_id',
        'start_place_id',
        'end_place_id',
        'available',
        'note',
    ];

    public function startPlace(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'start_place_id');
    }

    public function endPlace(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'end_place_id');
    }

    public function eventTemplate(): BelongsTo
    {
        return $this->belongsTo(EventTemplate::class);
    }
}
