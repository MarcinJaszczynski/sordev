<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelCorrespondenceLog extends Model
{
    public const DIRECTION_OUTBOUND = 'outbound';

    public const DIRECTION_INBOUND = 'inbound';

    /** @var array<string, string> */
    public static array $directions = [
        self::DIRECTION_OUTBOUND => 'Wysłane przez biuro',
        self::DIRECTION_INBOUND => 'Odebrane od obiektu',
    ];

    protected $fillable = [
        'event_id',
        'contractor_id',
        'event_hotel_stay_id',
        'direction',
        'subject',
        'body',
        'contact_person',
        'contacted_at',
        'attachment_path',
        'created_by',
    ];

    protected $casts = [
        'contacted_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function hotelStay(): BelongsTo
    {
        return $this->belongsTo(EventHotelStay::class, 'event_hotel_stay_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
