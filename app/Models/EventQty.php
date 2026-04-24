<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventQty extends Model
{
    protected $table = 'event_qties';

    protected $fillable = [
        'event_id',
        'qty',
        'gratis',
        'staff',
        'driver',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
