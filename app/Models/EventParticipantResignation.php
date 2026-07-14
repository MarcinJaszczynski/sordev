<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventParticipantResignation extends Model
{
    protected $fillable = [
        'event_id',
        'settlement_id',
        'participant_payment_id',
        'contract_id',
        'event_agreement_id',
        'participant_name',
        'resignation_type',
        'status',
        'resigned_at',
        'price_per_person_pln',
        'amount_paid_pln',
        'amount_due_pln',
        'refund_amount_pln',
        'retention_amount_pln',
        'insurance_policy_number',
        'insurance_refund_pln',
        'reason',
        'notes',
        'synced_at',
        'created_by',
    ];

    protected $casts = [
        'resigned_at' => 'date',
        'price_per_person_pln' => 'decimal:2',
        'amount_paid_pln' => 'decimal:2',
        'amount_due_pln' => 'decimal:2',
        'refund_amount_pln' => 'decimal:2',
        'retention_amount_pln' => 'decimal:2',
        'insurance_refund_pln' => 'decimal:2',
        'synced_at' => 'datetime',
    ];

    public static array $types = [
        'insurance' => 'Ubezpieczenie kosztów rezygnacji',
        'contractual' => 'Rezygnacja wg warunków (częściowy zwrot)',
        'other' => 'Inna',
    ];

    public static array $statuses = [
        'draft' => 'Szkic',
        'confirmed' => 'Potwierdzona',
        'settled' => 'Zsynchronizowana z rozliczeniem',
        'cancelled' => 'Anulowana',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(EventSettlement::class, 'settlement_id');
    }

    public function participantPayment(): BelongsTo
    {
        return $this->belongsTo(EventSettlementParticipantPayment::class, 'participant_payment_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function eventAgreement(): BelongsTo
    {
        return $this->belongsTo(EventAgreement::class, 'event_agreement_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(EventParticipantResignationLine::class, 'resignation_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recalculateTotalsFromLines(): void
    {
        $this->loadMissing('lines');

        if ($this->resignation_type === 'insurance') {
            $paid = (float) $this->amount_paid_pln;
            $this->refund_amount_pln = $paid;
            $this->retention_amount_pln = 0;

            return;
        }

        $refunded = (float) $this->lines->sum('refunded_amount_pln');
        $retained = (float) $this->lines->sum('retained_amount_pln');

        if ($this->lines->isNotEmpty()) {
            $this->refund_amount_pln = round($refunded, 2);
            $this->retention_amount_pln = round($retained, 2);

            return;
        }

        $due = (float) $this->amount_due_pln;
        $refund = (float) $this->refund_amount_pln;
        $this->retention_amount_pln = round(max(0, $due - $refund), 2);
    }
}
