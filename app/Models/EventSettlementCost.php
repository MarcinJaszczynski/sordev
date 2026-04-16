<?php

namespace App\Models;

use App\Models\Concerns\HasTasks;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventSettlementCost extends Model
{
    use HasFactory, HasTasks;

    protected $fillable = [
        'settlement_id',
        'source_type',
        'source_id',
        'name',
        'planned_amount',
        'planned_currency_id',
        'planned_rate',
        'planned_amount_pln',
        'actual_amount',
        'actual_currency_id',
        'rate_snapshot_id',
        'actual_rate',
        'actual_amount_pln',
        'paid_by',
        'advance_type',
        'payment_method',
        'document_number',
        'paid_at',
        'paid_by_user_id',
        'payment_status',
        'advance_due_date',
        'advance_amount',
        'notes',
        'order',
        'approval_status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'contractor_id',
    ];

    protected $casts = [
        'planned_amount'     => 'decimal:2',
        'planned_rate'       => 'decimal:4',
        'planned_amount_pln' => 'decimal:2',
        'actual_amount'      => 'decimal:2',
        'actual_rate'        => 'decimal:4',
        'actual_amount_pln'  => 'decimal:2',
        'advance_amount'     => 'decimal:2',
        'paid_at'            => 'datetime',
        'advance_due_date'   => 'datetime',
        'reviewed_by'        => 'integer',
        'reviewed_at'        => 'datetime',
    ];

    public static array $approvalStatuses = [
        'pending' => 'Oczekuje',
        'approved' => 'Zaakceptowana',
        'rejected' => 'Odrzucona',
    ];

    public static array $paidByOptions = [
        'office' => 'Biuro',
        'pilot'  => 'Pilot',
    ];

    public static array $advanceTypes = [
        'advance' => 'Zaliczka',
        'deposit' => 'Kaucja',
        'final'   => 'Dopłata końcowa',
        'full'    => 'Pełna płatność',
    ];

    public static array $paymentMethods = [
        'cash'     => 'Gotówka',
        'transfer' => 'Przelew',
        'card'     => 'Karta',
        'other'    => 'Inny',
    ];

    public static array $paymentStatuses = [
        'planned'              => 'Planowana',
        'reservation_required' => 'Wymaga rezerwacji',
        'reserved'             => 'Zarezerwowana',
        'advance_required'     => 'Wymaga zaliczki',
        'advance_paid'         => 'Zaliczka wpłacona',
        'partially_paid'       => 'Częściowo opłacona',
        'paid'                 => 'Opłacona',
        'cancelled'            => 'Anulowana',
    ];

    // --- Relacje ---

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(EventSettlement::class, 'settlement_id');
    }

    public function plannedCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'planned_currency_id');
    }

    public function actualCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'actual_currency_id');
    }

    public function rateSnapshot(): BelongsTo
    {
        return $this->belongsTo(CurrencyRateSnapshot::class, 'rate_snapshot_id');
    }

    public function paidByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'settlement_cost_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EventDocument::class, 'settlement_cost_id');
    }

    public function linkedDocuments()
    {
        $documents = $this->settlement?->documents()->get() ?? collect();

        return $documents->filter(function ($doc) {
            $ids = collect($doc->linked_cost_ids ?? [])->map(fn ($id) => (string) $id)->all();
            return in_array((string) $this->id, $ids, true);
        })->values();
    }

    public function programPoint(): ?EventProgramPoint
    {
        if ($this->source_type === 'program_point' && $this->source_id) {
            return EventProgramPoint::find($this->source_id);
        }
        return null;
    }

    // --- Computed ---

    /**
     * Różnica planowany → rzeczywisty (w PLN)
     */
    public function getDiffPlnAttribute(): ?float
    {
        if ($this->actual_amount_pln === null) return null;
        return (float) $this->actual_amount_pln - (float) $this->planned_amount_pln;
    }

    public function getLinkedDocumentsLabelAttribute(): string
    {
        $docs = $this->linkedDocuments();
        if ($docs->isEmpty()) {
            return '—';
        }

        return $docs->map(function ($doc) {
            $number = $doc->document_number ?: ('Dokument #' . $doc->id);
            return $number;
        })->join(', ');
    }

    public function getHasLinkedDocumentAttribute(): bool
    {
        return $this->linkedDocuments()->isNotEmpty();
    }

    public function getHasDocumentScanAttribute(): bool
    {
        return $this->linkedDocuments()->contains(function ($doc) {
            $files = $doc->files ?? [];
            return is_array($files) && count(array_filter($files)) > 0;
        });
    }

    public function getDocumentScanStatusAttribute(): string
    {
        $requiresDocument = in_array($this->payment_status, ['reserved', 'advance_paid', 'partially_paid', 'paid'], true);

        if ($this->has_document_scan) {
            return 'Skan OK';
        }

        if ($this->has_linked_document) {
            return 'Dokument bez skanu';
        }

        return $requiresDocument ? 'Brak dokumentu' : 'Brak dokumentu (niewymagany)';
    }

    /**
     * Przelicza actual_amount_pln na podstawie actual_amount i actual_rate
     */
    public function recalculateActualPln(): void
    {
        if ($this->actual_amount !== null) {
            $rate = $this->actual_rate ?? $this->plannedCurrency?->exchange_rate ?? 1;
            $this->actual_amount_pln = $this->actual_amount * $rate;
        }
    }

    protected static function booted(): void
    {
        static::saving(function (self $model) {
            // auto-przelicz PLN przy zmianie kwoty lub kursu
            if ($model->isDirty(['actual_amount', 'actual_rate']) && $model->actual_amount !== null) {
                $rate = $model->actual_rate ?? $model->planned_rate ?? 1;
                $model->actual_amount_pln = $model->actual_amount * $rate;
            }
            if ($model->isDirty(['planned_amount', 'planned_rate'])) {
                $rate = $model->planned_rate ?? 1;
                $model->planned_amount_pln = $model->planned_amount * $rate;
            }

            if ($model->advance_type === 'deposit' && blank($model->payment_status ?: null)) {
                $model->payment_status = 'reservation_required';
            }
            if ($model->advance_type === 'advance' && blank($model->payment_status ?: null)) {
                $model->payment_status = 'advance_required';
            }

            if ($model->advance_type === 'deposit' && $model->payment_status === 'planned') {
                $model->payment_status = 'reservation_required';
            }
            if ($model->advance_type === 'advance' && $model->payment_status === 'planned') {
                $model->payment_status = 'advance_required';
            }

            if ($model->actual_amount !== null && $model->payment_status !== 'cancelled') {
                $planned = (float) ($model->planned_amount ?? 0);
                $actual = (float) $model->actual_amount;

                if ($planned > 0) {
                    if ($actual >= $planned) {
                        $model->payment_status = 'paid';
                    } elseif ($actual > 0 && in_array($model->payment_status, ['planned', 'reservation_required', 'advance_required', 'reserved'], true)) {
                        $model->payment_status = 'partially_paid';
                    }
                }
            }

            if ($model->payment_status === 'paid' && !$model->paid_at) {
                $model->paid_at = now();
            }
        });

        static::saved(function (self $model) {
            // aktualizuj sumy w rozliczeniu
            $model->settlement?->recalculateTotals();
            $model->settlement?->recalculatePilotCash();
        });

        static::deleted(function (self $model) {
            $model->settlement?->recalculateTotals();
            $model->settlement?->recalculatePilotCash();
        });
    }
}
