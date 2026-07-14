<?php

namespace App\Models;

use App\Models\Concerns\HasStickyNotes;
use App\Models\Concerns\HasTasks;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventSettlementCost extends Model
{
    use HasFactory, HasStickyNotes, HasTasks, SoftDeletes;

    protected $fillable = [
        'settlement_id',
        'source_type',
        'source_id',
        'name',
        'planned_amount',
        'planned_unit_amount',
        'planned_amount_basis',
        'planned_participant_scope',
        'planned_currency_id',
        'planned_convert_to_pln',
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
        'invoice_number',
        'receipt_number',
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
        'planned_amount' => 'decimal:2',
        'planned_unit_amount' => 'decimal:2',
        'planned_convert_to_pln' => 'boolean',
        'planned_rate' => 'decimal:5',
        'planned_amount_pln' => 'decimal:2',
        'actual_amount' => 'decimal:2',
        'actual_rate' => 'decimal:5',
        'actual_amount_pln' => 'decimal:2',
        'advance_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'advance_due_date' => 'datetime',
        'reviewed_by' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public static array $approvalStatuses = [
        'pending' => 'Oczekuje',
        'approved' => 'Zaakceptowana',
        'rejected' => 'Odrzucona',
    ];

    public static array $paidByOptions = [
        'office' => 'Biuro',
        'pilot' => 'Pilot',
    ];

    public static array $sourceTypeLabels = [
        'program_point' => 'Program',
        'manual' => 'Nieprzewidziany',
        'transport' => 'Transport',
        'accommodation' => 'Nocleg',
        'insurance_day' => 'Ubezpieczenie',
    ];

    public static array $advanceTypes = [
        'advance' => 'Zaliczka',
        'deposit' => 'Kaucja',
        'final' => 'Dopłata końcowa',
        'full' => 'Pełna płatność',
    ];

    public static array $paymentMethods = [
        'cash' => 'Gotówka',
        'transfer' => 'Przelew',
        'card' => 'Karta',
        'other' => 'Inny',
    ];

    public static array $paymentStatuses = [
        'planned' => 'Planowana',
        'reservation_required' => 'Wymaga rezerwacji',
        'reserved' => 'Zarezerwowana',
        'advance_required' => 'Wymaga zaliczki',
        'advance_paid' => 'Zaliczka wpłacona',
        'partially_paid' => 'Częściowo opłacona',
        'paid' => 'Opłacona',
        'cancelled' => 'Anulowana',
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

    /**
     * Pozycje będące realnymi płatnościami (stos wpłat, ręczne z harmonogramem) — bez linii planu programu.
     */
    public function scopePaymentsOnly(Builder $query): Builder
    {
        return $query->where(function (Builder $inner): void {
            $inner->where('source_type', 'like', '%_payment')
                ->orWhere(function (Builder $manual): void {
                    $manual->where('source_type', 'manual')
                        ->whereIn('payment_status', [
                            'reservation_required',
                            'advance_required',
                            'advance_paid',
                            'partially_paid',
                            'paid',
                        ]);
                });
        });
    }

    public static function isPaymentSourceType(?string $sourceType): bool
    {
        return is_string($sourceType) && str_ends_with($sourceType, '_payment');
    }

    public static function isPlanSourceType(?string $sourceType): bool
    {
        if (! is_string($sourceType) || self::isPaymentSourceType($sourceType)) {
            return false;
        }

        return in_array($sourceType, ['program_point', 'transport', 'accommodation', 'insurance_day', 'manual'], true);
    }

    public static function isManualPaymentRow(self $cost): bool
    {
        if ($cost->source_type !== 'manual') {
            return false;
        }

        return in_array((string) $cost->payment_status, [
            'reservation_required',
            'advance_required',
            'advance_paid',
            'partially_paid',
            'paid',
        ], true) || filled($cost->actual_amount_pln);
    }

    /**
     * Zaległe płatności do sterty / kalendarza (bez planów informacyjnych).
     */
    public function scopePendingPaymentInbox(Builder $query): Builder
    {
        return $query
            ->paymentsOnly()
            ->whereNotIn('payment_status', ['paid', 'cancelled', 'planned', 'reserved'])
            ->whereNotNull('advance_due_date');
    }

    // --- Computed ---

    /**
     * Różnica planowany → rzeczywisty (w PLN)
     */
    public function getDiffPlnAttribute(): ?float
    {
        if ($this->actual_amount_pln === null) {
            return null;
        }

        if ($this->planned_amount_pln === null) {
            return null;
        }

        return (float) $this->actual_amount_pln - (float) $this->planned_amount_pln;
    }

    public function resolvePlannedAmountPln(): ?float
    {
        $amount = (float) ($this->planned_amount ?? 0);
        $currency = $this->relationLoaded('plannedCurrency')
            ? $this->plannedCurrency
            : ($this->planned_currency_id ? Currency::find($this->planned_currency_id) : null);
        $symbol = strtoupper((string) ($currency?->code ?? $currency?->symbol ?? 'PLN'));

        if ($symbol === 'PLN') {
            return round($amount, 2);
        }

        if (! (bool) ($this->planned_convert_to_pln ?? true)) {
            return null;
        }

        $rate = (float) ($this->planned_rate ?? 1);

        return round($amount * $rate, 2);
    }

    public function getLinkedDocumentsLabelAttribute(): string
    {
        $docs = $this->linkedDocuments();
        if ($docs->isEmpty()) {
            return '—';
        }

        return $docs->map(function ($doc) {
            $number = $doc->document_number ?: ('Dokument #'.$doc->id);

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
            if ($model->isDirty(['planned_amount', 'planned_rate', 'planned_convert_to_pln', 'planned_currency_id'])) {
                $model->planned_amount_pln = $model->resolvePlannedAmountPln();
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

            if ($model->payment_status === 'paid' && ! $model->paid_at) {
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
