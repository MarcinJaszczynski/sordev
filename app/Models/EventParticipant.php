<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventParticipant extends Model
{
    public const SOURCE_IMPORT = 'import';

    public const SOURCE_AGREEMENT = 'agreement';

    public const SOURCE_MANUAL = 'manual';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_RESIGNED = 'resigned';

    public static array $sources = [
        self::SOURCE_IMPORT => 'Import',
        self::SOURCE_AGREEMENT => 'Umowa',
        self::SOURCE_MANUAL => 'Ręcznie',
    ];

    public static array $statuses = [
        self::STATUS_ACTIVE => 'Aktywny',
        self::STATUS_RESIGNED => 'Rezygnacja',
    ];

    protected $fillable = [
        'event_id',
        'first_name',
        'last_name',
        'birth_date',
        'pesel',
        'email',
        'phone',
        'booking_reference',
        'source',
        'status',
        'contract_id',
        'event_agreement_id',
        'participant_payment_id',
        'import_batch_key',
        'notes',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function eventAgreement(): BelongsTo
    {
        return $this->belongsTo(EventAgreement::class);
    }

    public function participantPayment(): BelongsTo
    {
        return $this->belongsTo(EventSettlementParticipantPayment::class, 'participant_payment_id');
    }

    public function fullName(): string
    {
        $parts = array_filter([
            trim((string) $this->first_name),
            trim((string) $this->last_name),
        ]);

        return $parts !== [] ? implode(' ', $parts) : '';
    }

    public function setFullName(string $name): void
    {
        $name = trim($name);
        if ($name === '') {
            $this->first_name = null;
            $this->last_name = null;

            return;
        }

        $segments = preg_split('/\s+/', $name, 2) ?: [];
        $this->first_name = $segments[0] ?? null;
        $this->last_name = $segments[1] ?? null;
    }
}
