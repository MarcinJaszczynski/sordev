<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentReminderLog extends Model
{
    protected $fillable = [
        'event_settlement_participant_payment_id',
        'channel',
        'recipient',
        'cadence_day',
        'status',
        'message',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(EventSettlementParticipantPayment::class, 'event_settlement_participant_payment_id');
    }
}
