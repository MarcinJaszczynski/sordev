<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventAgreementPaymentSchedule extends Model
{
    protected $fillable = [
        'event_agreement_id',
        'sort_order',
        'label',
        'amount',
        'amount_foreign',
        'currency_code',
        'paid_by',
        'paid_amount',
        'paid_at',
        'due_date',
        'due_from',
        'due_to',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'amount_foreign' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'due_date' => 'date',
        'due_from' => 'date',
        'due_to' => 'date',
    ];

    public function eventAgreement(): BelongsTo
    {
        return $this->belongsTo(EventAgreement::class);
    }
}
