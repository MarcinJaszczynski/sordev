<?php

namespace App\Models;

use App\Models\Concerns\HasTasks;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PilotCashPreparation extends Model
{
    use HasFactory, HasTasks;

    protected $fillable = [
        'settlement_id',
        'currency_id',
        'calculated_amount',
        'approved_amount',
        'provided_amount',
        'spent_amount',
        'returned_amount',
        'balance',
        'rate_snapshot_id',
        'rate_used',
        'pln_equivalent',
        'status',
        'provided_at',
        'settled_at',
        'notes',
        'approval_status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected $casts = [
        'calculated_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'provided_amount' => 'decimal:2',
        'spent_amount' => 'decimal:2',
        'returned_amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'rate_used' => 'decimal:4',
        'pln_equivalent' => 'decimal:2',
        'provided_at' => 'datetime',
        'settled_at' => 'datetime',
        'reviewed_by' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public static array $approvalStatuses = [
        'pending' => 'Oczekuje',
        'approved' => 'Zaakceptowana',
        'rejected' => 'Odrzucona',
    ];

    public static array $statuses = [
        'calculated' => 'Obliczona',
        'approved' => 'Zatwierdzona',
        'provided' => 'Wypłacona pilotowi',
        'settled' => 'Rozliczona',
    ];

    // --- Relacje ---

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(EventSettlement::class, 'settlement_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function rateSnapshot(): BelongsTo
    {
        return $this->belongsTo(CurrencyRateSnapshot::class, 'rate_snapshot_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // --- Computed ---

    public function getReceivedAmountAttribute(): float
    {
        return (float) ($this->provided_amount ?? $this->approved_amount ?? $this->calculated_amount ?? 0);
    }

    /**
     * Saldo końcowe w walucie: otrzymano - wydano - zwrócono
     */
    public function getComputedBalanceAttribute(): float
    {
        $received = $this->received_amount;
        $spent = (float) ($this->spent_amount ?? 0);
        $returned = (float) ($this->returned_amount ?? 0);

        return $received - $spent - $returned;
    }

    /**
     * Ile pilot ma jeszcze zwrócić do biura
     */
    public function getToReturnAmountAttribute(): float
    {
        return max($this->computed_balance, 0);
    }

    /**
     * Ile biuro powinno dopłacić pilotowi (gdy saldo ujemne)
     */
    public function getToPayPilotAmountAttribute(): float
    {
        return max(-1 * $this->computed_balance, 0);
    }

    protected static function booted(): void
    {
        static::saving(function (self $model) {
            if ($model->isDirty(['approved_amount', 'provided_amount', 'spent_amount', 'returned_amount'])) {
                $received = (float) ($model->provided_amount ?? $model->approved_amount ?? $model->calculated_amount ?? 0);
                $spent = (float) ($model->spent_amount ?? 0);
                $returned = (float) ($model->returned_amount ?? 0);
                $model->balance = $received - $spent - $returned;
            }
        });
    }
}
