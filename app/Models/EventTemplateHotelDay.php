<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventTemplateHotelDay extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_template_id',
        'day',
        'hotel_room_ids_qty',
        'hotel_room_ids_gratis',
        'hotel_room_ids_staff',
        'hotel_room_ids_driver',
        'notes',
    ];

    protected $casts = [
        'hotel_room_ids_qty' => 'array',
        'hotel_room_ids_gratis' => 'array',
        'hotel_room_ids_staff' => 'array',
        'hotel_room_ids_driver' => 'array',
    ];

    public function eventTemplate()
    {
        return $this->belongsTo(EventTemplate::class);
    }

    public function hotelRoomsQty()
    {
        return $this->belongsToMany(HotelRoom::class, null, 'id', 'id')
            ->whereIn('hotel_rooms.id', $this->hotel_room_ids_qty ?? []);
    }

    public function hotelRoomsGratis()
    {
        return $this->belongsToMany(HotelRoom::class, null, 'id', 'id')
            ->whereIn('hotel_rooms.id', $this->hotel_room_ids_gratis ?? []);
    }

    public function hotelRoomsStaff()
    {
        return $this->belongsToMany(HotelRoom::class, null, 'id', 'id')
            ->whereIn('hotel_rooms.id', $this->hotel_room_ids_staff ?? []);
    }

    public function hotelRoomsDriver()
    {
        return $this->belongsToMany(HotelRoom::class, null, 'id', 'id')
            ->whereIn('hotel_rooms.id', $this->hotel_room_ids_driver ?? []);
    }

    public function getAllAssignedRoomIds(): array
    {
        return array_merge(
            $this->hotel_room_ids_qty ?? [],
            $this->hotel_room_ids_gratis ?? [],
            $this->hotel_room_ids_staff ?? [],
            $this->hotel_room_ids_driver ?? []
        );
    }
}
