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

    public const GENDER_MALE = 'male';

    public const GENDER_FEMALE = 'female';

    public const GENDER_OTHER = 'other';

    /** @var array<string, string> */
    public static array $genders = [
        self::GENDER_MALE => 'Męska',
        self::GENDER_FEMALE => 'Żeńska',
        self::GENDER_OTHER => 'Inna',
    ];

    protected $fillable = [
        'event_id',
        'first_name',
        'last_name',
        'gender',
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
        'diet',
        'selected_extras',
        'parent_consent_at',
        'parent_consent_ip',
        'consents',
        'parent_access_token',
        'parent_access_token_expires_at',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'parent_consent_at' => 'datetime',
        'parent_access_token_expires_at' => 'datetime',
        'consents' => 'array',
        'selected_extras' => 'array',
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

    public function attendances(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EventAttendance::class);
    }

    public function hasParentConsent(): bool
    {
        if (\App\Support\EventParticipantConsents::hasRequired($this->consents)) {
            return true;
        }

        return $this->parent_consent_at !== null;
    }

    /**
     * @return array<string, bool>
     */
    public function consentChecklist(): array
    {
        return \App\Support\EventParticipantConsents::checklist($this->consents);
    }

    public function consentsCompletedLabel(): string
    {
        $done = \App\Support\EventParticipantConsents::completedCount($this->consents);
        $total = count(\App\Support\EventParticipantConsents::allKeys());

        return "{$done}/{$total}";
    }

    public function genderLabel(): ?string
    {
        if ($this->gender === null || $this->gender === '') {
            return null;
        }

        return self::$genders[$this->gender] ?? $this->gender;
    }

    /**
     * Normalizuje wartość z UI / CSV do male|female|other albo null.
     */
    public static function normalizeGender(?string $raw): ?string
    {
        $value = mb_strtolower(trim((string) $raw));
        if ($value === '') {
            return null;
        }

        $ascii = strtr($value, [
            'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n',
            'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
        ]);

        return match ($ascii) {
            'male', 'm', 'meska', 'mezczyzna', 'chlopiec' => self::GENDER_MALE,
            'female', 'f', 'z', 'zenska', 'kobieta', 'dziewczynka' => self::GENDER_FEMALE,
            'other', 'o', 'i', 'inna', 'inne', 'inny' => self::GENDER_OTHER,
            default => null,
        };
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
