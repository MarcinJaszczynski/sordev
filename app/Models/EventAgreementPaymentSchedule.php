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
        'due_date',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
    ];

    public function eventAgreement(): BelongsTo
    {
        return $this->belongsTo(EventAgreement::class);
    }
}
