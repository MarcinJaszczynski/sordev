<?php

namespace App\Models;

use App\Models\Concerns\HasStickyNotes;
use App\Models\Concerns\HasTasks;
use App\Support\CurrencyAmountDisplay;
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
        'reservation_id',
        'finance_group_id',
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
        'accommodation_hotel' => 'Nocleg (hotel)',
        'accommodation_hotel_stay' => 'Nocleg (noc)',
        'insurance_day' => 'Ubezpieczenie',
    ];

    public static array $advanceTypes = [
        'advance' => 'Zaliczka',
        'deposit' => 'Kaucja',
        'supplement' => 'Dopłata',
        'final' => 'Dopłata całkowita',
        'full' => 'Wpłata całkowita',
    ];

    /**
     * Typy widoczne w UI wpłat kosztów (bez historycznych kaucji).
     *
     * @return array<string, string>
     */
    public static function userSelectableAdvanceTypes(): array
    {
        return [
            'advance' => 'Zaliczka',
            'supplement' => 'Dopłata',
            'final' => 'Dopłata całkowita',
            'full' => 'Wpłata całkowita',
        ];
    }

    /**
     * @return list<string>
     */
    public static function userSelectableAdvanceTypeKeys(): array
    {
        return array_keys(self::userSelectableAdvanceTypes());
    }

    public static function userSelectableAdvanceTypesValidationRule(): string
    {
        return 'in:'.implode(',', self::userSelectableAdvanceTypeKeys());
    }

    public static function isAdvancePaymentType(?string $type): bool
    {
        return in_array((string) $type, ['advance', 'deposit'], true);
    }

    public static function advanceTypeLabel(?string $type): string
    {
        return self::$advanceTypes[$type] ?? ($type ?: 'Wpłata');
    }

    /**
     * Mapuje historyczne / legacy wartości na aktualny wybór w formularzu.
     */
    public static function normalizeUserAdvanceType(?string $type): string
    {
        $type = (string) $type;

        if (array_key_exists($type, self::userSelectableAdvanceTypes())) {
            return $type;
        }

        return match ($type) {
            'deposit' => 'advance',
            default => 'final',
        };
    }

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

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function financeGroup(): BelongsTo
    {
        return $this->belongsTo(EventSettlementCostGroup::class, 'finance_group_id');
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
            $raw = $doc->linked_cost_ids ?? [];
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $raw = $decoded;
                } else {
                    $raw = preg_split('/\s*,\s*/', trim($raw, "[] \t\n\r\0\x0B\"")) ?: [];
                }
            }

            $ids = collect($raw)
                ->flatten()
                ->map(fn ($id) => (string) (int) $id)
                ->filter(fn (string $id): bool => $id !== '0')
                ->all();

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

        return in_array($sourceType, ['program_point', 'transport', 'accommodation', 'accommodation_hotel', 'accommodation_hotel_stay', 'insurance_day', 'manual'], true);
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
     * Dla wiersza inbox/płatności zwraca pozycję planu, na którą należy zaksięgować wpłatę.
     * Dla samego planu / manualnego zobowiązania zwraca $this.
     */
    public function resolvePlanCostForPayment(): self
    {
        if (! self::isPaymentSourceType($this->source_type)) {
            return $this;
        }

        $settlementId = (int) $this->settlement_id;

        if ($this->source_type === 'program_point_payment' && $this->source_id) {
            $plan = static::query()
                ->where('settlement_id', $settlementId)
                ->where('source_type', 'program_point')
                ->where('source_id', $this->source_id)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->first();

            if ($plan) {
                return $plan;
            }
        }

        if ($this->source_type === 'manual_payment' && $this->source_id) {
            $plan = static::query()
                ->whereKey((int) $this->source_id)
                ->where('settlement_id', $settlementId)
                ->whereNull('deleted_at')
                ->first();

            if ($plan) {
                return $plan;
            }
        }

        if (in_array($this->source_type, ['transport_payment', 'accommodation_payment'], true)) {
            $planType = str_replace('_payment', '', $this->source_type);
            $plan = static::query()
                ->where('settlement_id', $settlementId)
                ->where('source_type', $planType)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->first();

            if ($plan) {
                return $plan;
            }
        }

        if (in_array($this->source_type, ['accommodation_hotel_payment', 'accommodation_hotel_stay_payment'], true) && $this->source_id) {
            $planType = str_replace('_payment', '', $this->source_type);
            $plan = static::query()
                ->where('settlement_id', $settlementId)
                ->where('source_type', $planType)
                ->where('source_id', (int) $this->source_id)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->first();

            if ($plan) {
                return $plan;
            }
        }

        return $this;
    }

    /**
     * Zaległe płatności do sterty / kalendarza.
     * Tylko zobowiązania (nie zaksięgowane w pełni): bez paid / cancelled / planned bez terminu zobowiązania.
     * Wyklucza advance_paid (zaliczka już wpłacona — nie należy do „do zapłaty”).
     */
    public function scopePendingPaymentInbox(Builder $query): Builder
    {
        return $query
            ->paymentsOnly()
            ->whereNotIn('payment_status', ['paid', 'cancelled', 'planned', 'reserved', 'advance_paid'])
            ->whereNotNull('advance_due_date')
            ->where(function (Builder $q): void {
                // Wiersze-zobowiązania (stary model): planned_amount > 0 i brak / mały actual
                $q->where(function (Builder $inner): void {
                    $inner->where('planned_amount_pln', '>', 0.01)
                        ->where(function (Builder $actual): void {
                            $actual->whereNull('actual_amount_pln')
                                ->orWhereColumn('actual_amount_pln', '<', 'planned_amount_pln');
                        });
                })->orWhere(function (Builder $inner): void {
                    // Częściowe / wymaga zaliczki bez full actual
                    $inner->whereIn('payment_status', ['advance_required', 'partially_paid', 'reservation_required']);
                });
            });
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
            if ($model->isDirty(['actual_amount', 'actual_rate', 'actual_currency_id', 'planned_convert_to_pln']) && $model->actual_amount !== null) {
                $isForeign = CurrencyAmountDisplay::isForeignCurrency($model->actual_currency_id);
                if ($isForeign && ! (bool) ($model->planned_convert_to_pln ?? true)) {
                    $model->actual_amount_pln = null;
                } else {
                    $rate = $model->actual_rate ?? $model->planned_rate ?? 1;
                    $model->actual_amount_pln = $model->actual_amount * $rate;
                }
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
