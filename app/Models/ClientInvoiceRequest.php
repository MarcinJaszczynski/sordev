<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientInvoiceRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_REJECTED = 'rejected';

    public const BUYER_PERSON = 'person';

    public const BUYER_COMPANY = 'company';

    public const SOURCE_PORTAL = 'portal';

    public const SOURCE_WEB = 'web';

    public const SOURCE_ADMIN = 'admin';

    public static array $statuses = [
        self::STATUS_PENDING => 'Oczekuje',
        self::STATUS_PROCESSED => 'Zrealizowany',
        self::STATUS_REJECTED => 'Odrzucony',
    ];

    public static array $buyerTypes = [
        self::BUYER_PERSON => 'Osoba fizyczna',
        self::BUYER_COMPANY => 'Firma / instytucja',
    ];

    public static array $sources = [
        self::SOURCE_PORTAL => 'Portal klienta',
        self::SOURCE_WEB => 'Strona WWW',
        self::SOURCE_ADMIN => 'Biuro (ręcznie)',
    ];

    protected $fillable = [
        'event_id',
        'event_code_entered',
        'contract_id',
        'user_id',
        'buyer_type',
        'source',
        'company_name',
        'nip',
        'street',
        'house_number',
        'postal_code',
        'city',
        'invoice_email',
        'applicant_phone',
        'amount',
        'payment_reference',
        'notes',
        'admin_notes',
        'status',
        'processed_by',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function processedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function salesInvoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::$statuses[$this->status] ?? $this->status;
    }

    public function getBuyerTypeLabelAttribute(): string
    {
        return self::$buyerTypes[$this->buyer_type] ?? $this->buyer_type;
    }

    public function getSourceLabelAttribute(): string
    {
        return self::$sources[$this->source] ?? $this->source;
    }

    public function getFullAddressAttribute(): string
    {
        return trim(implode(' ', array_filter([
            $this->street,
            $this->house_number,
            $this->postal_code,
            $this->city,
        ])));
    }

    public function isLinkedToEvent(): bool
    {
        return $this->event_id !== null;
    }
}
