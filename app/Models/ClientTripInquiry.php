<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

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
        'escalated_at',
    ];

    protected function casts(): array
    {
        return [
            'answered_at' => 'datetime',
            'escalated_at' => 'datetime',
        ];
    }

    /**
     * Widoczność w inboxie biura:
     * - bez opiekuna / po eskalacji → całe biuro
     * - przed eskalacją → tylko aktualny office_caretaker_id
     */
    public function scopeVisibleToOfficeUser(Builder $query, User $user): Builder
    {
        if (! Schema::hasColumn('client_trip_inquiries', 'escalated_at')
            || ! Schema::hasColumn('events', 'office_caretaker_id')) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user): void {
            $q->whereNotNull('escalated_at')
                ->orWhereHas('event', fn (Builder $e) => $e->whereNull('office_caretaker_id'))
                ->orWhereHas('event', fn (Builder $e) => $e->where('office_caretaker_id', $user->id));
        });
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
