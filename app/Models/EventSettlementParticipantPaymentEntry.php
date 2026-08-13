<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventSettlementParticipantPaymentEntry extends Model
{
    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_BANK_IMPORT = 'bank_import';

    public const SOURCE_ONLINE = 'online';

    public const KIND_REGULAR = 'regular';

    public const KIND_OFFICE_ADVANCE = 'office_advance';

    public const KIND_PILOT_ON_SITE = 'pilot_on_site';

    public static array $paymentKinds = [
        self::KIND_REGULAR => 'Dopłata',
        self::KIND_OFFICE_ADVANCE => 'Zaliczka',
        self::KIND_PILOT_ON_SITE => 'Dopłata u pilota',
    ];

    public static array $sources = [
        self::SOURCE_MANUAL => 'Ręcznie',
        self::SOURCE_BANK_IMPORT => 'Import bankowy',
        self::SOURCE_ONLINE => 'Płatność online',
    ];

    protected $fillable = [
        'participant_payment_id',
        'paid_at',
        'amount',
        'currency_id',
        'rate',
        'amount_pln',
        'payer_name',
        'bank_transfer_description',
        'payment_kind',
        'source',
        'payment_method',
        'bank_payment_import_line_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'amount' => 'decimal:2',
        'rate' => 'decimal:6',
        'amount_pln' => 'decimal:2',
    ];

    public function participantPayment(): BelongsTo
    {
        return $this->belongsTo(EventSettlementParticipantPayment::class, 'participant_payment_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function bankImportLine(): BelongsTo
    {
        return $this->belongsTo(BankPaymentImportLine::class, 'bank_payment_import_line_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
