<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientTripInquiry extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_ANSWERED = 'answered';

    public const STATUS_CLOSED = 'closed';

    public const SOURCE_CLIENT = 'client';

    public const SOURCE_PILOT = 'pilot';

    public static array $statuses = [
        self::STATUS_OPEN => 'Otwarte',
        self::STATUS_ANSWERED => 'Odpowiedziane',
        self::STATUS_CLOSED => 'Zamknięte',
    ];

    public static array $sources = [
        self::SOURCE_CLIENT => 'Klient',
        self::SOURCE_PILOT => 'Pilot',
    ];

    protected $fillable = [
        'event_id',
        'user_id',
        'contract_id',
        'event_portal_access_id',
        'source',
        'subject',
        'body',
        'status',
        'office_reply',
        'answered_by',
        'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'answered_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function portalAccess(): BelongsTo
    {
        return $this->belongsTo(EventPortalAccess::class, 'event_portal_access_id');
    }

    public function answeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::$statuses[$this->status] ?? (string) $this->status;
    }

    public function getSourceLabelAttribute(): string
    {
        return self::$sources[$this->source ?? self::SOURCE_CLIENT] ?? (string) $this->source;
    }
}
