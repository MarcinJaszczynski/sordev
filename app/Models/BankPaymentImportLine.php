<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankPaymentImportLine extends Model
{
    protected $fillable = [
        'batch_id',
        'operation_date',
        'title',
        'counterparty',
        'account_number',
        'amount_pln',
        'fingerprint',
        'match_status',
        'match_reason',
        'contract_id',
        'participant_payment_id',
        'event_id',
        'selected',
        'applied',
        'applied_at',
        'apply_notes',
    ];

    protected $casts = [
        'operation_date' => 'date',
        'amount_pln' => 'decimal:2',
        'selected' => 'boolean',
        'applied' => 'boolean',
        'applied_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(BankPaymentImportBatch::class, 'batch_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function participantPayment(): BelongsTo
    {
        return $this->belongsTo(EventSettlementParticipantPayment::class, 'participant_payment_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
