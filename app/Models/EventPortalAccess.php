<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventPortalAccess extends Model
{
    public const ROLE_PARTICIPANT = 'participant';

    public const ROLE_GUARDIAN = 'guardian';

    public const SOURCE_ADMIN = 'admin';

    public const SOURCE_AGREEMENT_FLOW = 'agreement_flow';

    protected $fillable = [
        'event_id',
        'user_id',
        'role',
        'contract_id',
        'event_participant_id',
        'source',
        'shared_at',
        'shared_by',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'shared_at' => 'datetime',
            'revoked_at' => 'datetime',
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

    public function eventParticipant(): BelongsTo
    {
        return $this->belongsTo(EventParticipant::class);
    }

    public function sharedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    public function isParticipant(): bool
    {
        return $this->role === self::ROLE_PARTICIPANT;
    }

    public function isGuardian(): bool
    {
        return $this->role === self::ROLE_GUARDIAN;
    }

    public function scopeActive($query)
    {
        return $query->whereNull('revoked_at');
    }
}
