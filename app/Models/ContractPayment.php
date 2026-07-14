<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractPayment extends Model
{
    protected $fillable = [
        'contract_id',
        'amount',
        'currency',
        'paid_at',
        'payment_method_code',
        'description',
        'participant_payment_id',
        'sort_order',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'date',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function participantPayment(): BelongsTo
    {
        return $this->belongsTo(EventSettlementParticipantPayment::class, 'participant_payment_id');
    }
}
