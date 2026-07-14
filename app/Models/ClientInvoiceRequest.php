<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientInvoiceRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_REJECTED = 'rejected';

    public static array $statuses = [
        self::STATUS_PENDING => 'Oczekuje',
        self::STATUS_PROCESSED => 'Zrealizowany',
        self::STATUS_REJECTED => 'Odrzucony',
    ];

    protected $fillable = [
        'event_id',
        'contract_id',
        'user_id',
        'company_name',
        'nip',
        'street',
        'house_number',
        'postal_code',
        'city',
        'invoice_email',
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

    public function getStatusLabelAttribute(): string
    {
        return self::$statuses[$this->status] ?? $this->status;
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
}
