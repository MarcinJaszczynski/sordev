<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelRoom extends Model
{
    protected $fillable = [
        'name',
        'description',
        'notes',
        'people_count',
        'price',
        'currency',
        'convert_to_pln',
    ];

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'hotel_room_tag');
    }

    /**
     * Relacja do przypisań hotelowych do dni eventu
     */
    public function eventTemplateHotelDays()
    {
        return $this->hasMany(EventTemplateHotelDay::class);
    }
}
