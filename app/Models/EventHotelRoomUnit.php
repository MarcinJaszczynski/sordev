<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventHotelRoomUnit extends Model
{
    protected $fillable = [
        'event_hotel_room_line_id',
        'unit_index',
        'room_number',
    ];

    protected $casts = [
        'unit_index' => 'integer',
    ];

    public function line(): BelongsTo
    {
        return $this->belongsTo(EventHotelRoomLine::class, 'event_hotel_room_line_id');
    }
}
