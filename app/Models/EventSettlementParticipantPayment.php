<?php

namespace App\Models;

use App\Models\Concerns\HasTasks;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventSettlementParticipantPayment extends Model
{
    use HasFactory, HasTasks;

    protected $fillable = [
        'settlement_id',
        'participant_name',
        'booking_reference',
        'due_amount_pln',
        'paid_amount_pln',
        'discount_amount_pln',
        'payment_status',
        'payment_date',
        'payment_method',
        'document_number',
        'attended',
        'notes',
        'approval_status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected $casts = [
        'due_amount_pln' => 'decimal:2',
        'paid_amount_pln' => 'decimal:2',
        'discount_amount_pln' => 'decimal:2',
        'payment_date' => 'datetime',
        'attended' => 'boolean',
        'reviewed_by' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public static array $approvalStatuses = [
        'pending' => 'Oczekuje',
        'approved' => 'Zaakceptowana',
        'rejected' => 'Odrzucona',
    ];

    public static array $paymentStatuses = [
        'pending' => 'Oczekuje',
        'partial' => 'Częściowa',
        'paid' => 'Opłacona',
        'overpaid' => 'Nadpłata',
        'cancelled' => 'Anulowana',
    ];

    public static array $paymentMethods = [
        'cash' => 'Gotówka',
        'transfer' => 'Przelew',
        'card' => 'Karta',
        'other' => 'Inny',
    ];

    // --- Relacje ---

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(EventSettlement::class, 'settlement_id');
    }

    public function agreements(): HasMany
    {
        return $this->hasMany(EventAgreement::class, 'participant_payment_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // --- Computed ---

    public function getBalanceAttribute(): float
    {
        return (float) $this->paid_amount_pln - (float) $this->due_amount_pln;
    }

    // --- Hooks ---

    protected static function booted(): void
    {
        static::saving(function (self $model) {
            // auto-status
            if ($model->isDirty(['paid_amount_pln', 'due_amount_pln'])) {
                $paid = (float) $model->paid_amount_pln;
                $due = (float) $model->due_amount_pln;
                if ($paid <= 0) {
                    $model->payment_status = 'pending';
                } elseif ($paid < $due) {
                    $model->payment_status = 'partial';
                } elseif ($paid > $due) {
                    $model->payment_status = 'overpaid';
                } else {
                    $model->payment_status = 'paid';
                }
            }
        });

        static::saved(fn ($m) => $m->settlement?->recalculateTotals());
        static::deleted(fn ($m) => $m->settlement?->recalculateTotals());
    }
}
