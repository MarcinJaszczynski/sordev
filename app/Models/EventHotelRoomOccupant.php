<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventHotelRoomOccupant extends Model
{
    public const SOURCES = [
        'agreement' => 'Umowa',
        'reservation' => 'Rezerwacja',
        'manual' => 'Ręcznie',
    ];

    protected $fillable = [
        'event_hotel_room_line_id',
        'unit_index',
        'bed_index',
        'name',
        'source',
        'event_agreement_id',
        'contract_id',
        'reservation_id',
        'order',
    ];

    protected $casts = [
        'order' => 'integer',
        'unit_index' => 'integer',
        'bed_index' => 'integer',
    ];

    public function roomLine(): BelongsTo
    {
        return $this->belongsTo(EventHotelRoomLine::class, 'event_hotel_room_line_id');
    }

    public function eventAgreement(): BelongsTo
    {
        return $this->belongsTo(EventAgreement::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function participantKey(): ?string
    {
        if ($this->event_agreement_id) {
            return 'agreement:'.$this->event_agreement_id;
        }

        if ($this->contract_id) {
            return 'contract:'.$this->contract_id;
        }

        if ($this->reservation_id) {
            return 'reservation:'.$this->reservation_id;
        }

        return null;
    }
}
