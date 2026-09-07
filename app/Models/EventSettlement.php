<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class EventSettlement extends Model
{
    use HasFactory;

    protected $attributes = [
        'status' => 'draft',
        'planned_cost_pln' => 0,
        'actual_cost_pln' => 0,
        'participant_due_pln' => 0,
        'participant_paid_pln' => 0,
    ];

    protected $fillable = [
        'event_id',
        'status',
        'pilot_id',
        'planned_cost_pln',
        'actual_cost_pln',
        'participant_due_pln',
        'participant_paid_pln',
        'settled_at',
        'created_by',
        'notes',
        'pilot_report_notes',
        'reported_participant_count',
        'odometer_start',
        'odometer_end',
        'pilot_report_updated_at',
    ];

    protected $casts = [
        'planned_cost_pln' => 'decimal:2',
        'actual_cost_pln' => 'decimal:2',
        'participant_due_pln' => 'decimal:2',
        'participant_paid_pln' => 'decimal:2',
        'settled_at' => 'datetime',
        'pilot_report_updated_at' => 'datetime',
        'reported_participant_count' => 'integer',
        'odometer_start' => 'integer',
        'odometer_end' => 'integer',
    ];

    public static array $statuses = [
        'draft' => 'Szkic',
        'active' => 'Aktywne',
        'pilot_settled' => 'Pilot rozliczył',
        'closed' => 'Zamknięte',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            $model->created_by ??= Auth::id();
            $model->status ??= 'draft';
            $model->planned_cost_pln ??= 0;
            $model->actual_cost_pln ??= 0;
            $model->participant_due_pln ??= 0;
            $model->participant_paid_pln ??= 0;
        });
    }

    // --- Relacje ---

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function pilot(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pilot_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function costs(): HasMany
    {
        return $this->hasMany(EventSettlementCost::class, 'settlement_id')->orderBy('order');
    }

    public function costGroups(): HasMany
    {
        return $this->hasMany(EventSettlementCostGroup::class, 'settlement_id')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Punkty programu imprezy powiązanej z tym rozliczeniem (ten sam event_id).
     */
    public function programPoints(): HasMany
    {
        return $this->hasMany(EventProgramPoint::class, 'event_id', 'event_id')
            ->orderBy('day')
            ->orderBy('order');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'event_id', 'event_id');
    }

    public function participantPayments(): HasMany
    {
        return $this->hasMany(EventSettlementParticipantPayment::class, 'settlement_id');
    }

    public function pilotCashPreparations(): HasMany
    {
        return $this->hasMany(PilotCashPreparation::class, 'settlement_id');
    }

    public function currencyExchanges(): HasMany
    {
        return $this->hasMany(PilotCurrencyExchange::class, 'settlement_id');
    }

    public function busCollections(): HasMany
    {
        return $this->hasMany(EventBusCollection::class, 'settlement_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EventSettlementDocument::class, 'settlement_id')->latest('id');
    }

    public function isEditableByPilot(): bool
    {
        return $this->status !== 'closed';
    }

    public function getOdometerDistanceAttribute(): ?int
    {
        if ($this->odometer_start === null || $this->odometer_end === null) {
            return null;
        }

        return max(0, $this->odometer_end - $this->odometer_start);
    }

    // --- Computed ---

    /**
     * Różnica: koszt rzeczywisty - planowany
     */
    public function getCostDiffAttribute(): float
    {
        return (float) $this->actual_cost_pln - (float) $this->planned_cost_pln;
    }

    /**
     * Różnica: wpłacone - należne od uczestników
     */
    public function getParticipantBalanceAttribute(): float
    {
        return (float) $this->participant_paid_pln - (float) $this->participant_due_pln;
    }

    /**
     * Przychód wpłat uczestników minus koszty rzeczywiste.
     */
    public function getNetResultPlnAttribute(): float
    {
        return (float) $this->participant_paid_pln - (float) $this->actual_cost_pln;
    }

    /**
     * Przelicza i zapisuje sumy z pozycji kosztów.
     * Logika: {@see \App\Services\EventSettlementTotalsService}.
     */
    public function recalculateTotals(): void
    {
        \App\Services\EventSettlementTotalsService::for($this)->recalculate();
    }

    public function refreshDerivedData(): void
    {
        app(\App\Services\ParticipantPaymentLedgerService::class)->reconcileSettlement($this);
        $this->syncParticipantPaymentsFromAgreements();
        $this->recalculateTotals();
        $this->recalculatePilotCash();
        $this->refresh();
    }

    public function syncParticipantPaymentsFromAgreements(): void
    {
        $event = $this->event()->with('agreements')->first();

        if (! $event) {
            return;
        }

        $resolver = app(\App\Services\PublicAgreementResolver::class);

        foreach ($event->agreements as $agreement) {
            $resolver->syncPayment($agreement, $this);
        }
    }

    /**
     * Importuje wszystkie punkty programu imprezy oraz koszty transportu i noclegu jako pozycje kosztów planowanych.
     * Logika: {@see \App\Services\EventSettlementImportService}.
     */
    public function importFromEvent(): void
    {
        \App\Services\EventSettlementImportService::for($this)->import();
    }

    /**
     * Oblicza gotówkę pilota per waluta i zapisuje/aktualizuje wpisy.
     * Do przygotowania = plan pozycji paid_by=pilot minus już zapłacone (zaliczki biura + gotówka pilota).
     * Np. hotel 800 − zaliczka biura 300 = 500 dopłaty do przygotowania.
     */
    public function recalculatePilotCash(): void
    {
        $allCosts = $this->costs()->with('plannedCurrency')->get();
        $health = app(\App\Services\SettlementPaymentHealthService::class);

        $pilotPlanCosts = $allCosts
            ->filter(function (EventSettlementCost $cost) use ($health): bool {
                if (($cost->paid_by ?? '') !== 'pilot') {
                    return false;
                }

                if (($cost->payment_status ?? '') === 'cancelled') {
                    return false;
                }

                if (EventSettlementCost::isPaymentSourceType($cost->source_type)) {
                    return false;
                }

                return $health->isEvaluablePlanCost($cost);
            })
            ->values();

        $fallbackPlnCurrencyId = $this->resolveFallbackPlnCurrencyId();

        $byCurrency = $pilotPlanCosts->groupBy(
            fn (EventSettlementCost $cost) => $cost->planned_currency_id ?: $fallbackPlnCurrencyId
        );

        $usedCurrencyIds = [];

        foreach ($byCurrency as $currencyId => $items) {
            if (! $currencyId) {
                continue;
            }

            $usedCurrencyIds[] = (int) $currencyId;

            $total = 0.0;
            $pln = 0.0;
            $rate = (float) ($items->first()?->planned_rate ?? 1);

            foreach ($items as $cost) {
                [$remainingAmount, $remainingPln] = $this->resolvePilotCashRemaining($cost, $allCosts, $health);
                $total += $remainingAmount;
                $pln += $remainingPln;
            }

            $existing = $this->pilotCashPreparations()->where('currency_id', $currencyId)->first();

            $attributes = [
                'calculated_amount' => round($total, 2),
                'rate_used' => $rate > 0 ? $rate : 1,
                'pln_equivalent' => round($pln, 2),
            ];

            if (! $existing || blank($existing->provided_amount)) {
                $attributes['status'] = 'calculated';
            }

            $this->pilotCashPreparations()->updateOrCreate(
                ['currency_id' => $currencyId],
                $attributes
            );
        }

        // Zbiórki w autokarze: utrzymaj wiersze walut (także bez kosztów „płaci pilot”).
        $busCurrencyIds = $this->busCollectionCurrencyIds();
        foreach ($busCurrencyIds as $busCurrencyId) {
            $usedCurrencyIds[] = $busCurrencyId;
            $this->pilotCashPreparations()->firstOrCreate(
                ['currency_id' => $busCurrencyId],
                [
                    'calculated_amount' => 0,
                    'rate_used' => 1,
                    'pln_equivalent' => 0,
                    'status' => 'calculated',
                ]
            );
        }

        $usedCurrencyIds = array_values(array_unique(array_map('intval', $usedCurrencyIds)));

        $staleQuery = $this->pilotCashPreparations()->whereNull('provided_amount');

        if (! empty($usedCurrencyIds)) {
            $staleQuery->whereNotIn('currency_id', $usedCurrencyIds);
        }

        $staleQuery->delete();

        $this->applyPilotCurrencyExchangeBalances();
    }

    /**
     * @return list<int>
     */
    public function busCollectionCurrencyIds(): array
    {
        if (! Schema::hasTable('event_bus_collections')) {
            return [];
        }

        return $this->busCollections()
            ->whereNotNull('currency_id')
            ->pluck('currency_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    protected function applyPilotCurrencyExchangeBalances(): void
    {
        if (! Schema::hasTable('pilot_currency_exchanges')) {
            return;
        }

        $exchanges = $this->currencyExchanges()->get();
        if ($exchanges->isEmpty()) {
            return;
        }

        $currencyIds = $exchanges
            ->flatMap(fn ($row) => [$row->from_currency_id, $row->to_currency_id])
            ->filter()
            ->unique()
            ->values();

        foreach ($currencyIds as $currencyId) {
            $this->pilotCashPreparations()->firstOrCreate(
                ['currency_id' => (int) $currencyId],
                [
                    'calculated_amount' => 0,
                    'rate_used' => 1,
                    'pln_equivalent' => 0,
                    'status' => 'calculated',
                ]
            );
        }

        foreach ($this->pilotCashPreparations()->get() as $cash) {
            $currencyId = (int) $cash->currency_id;
            $exchangeIn = (float) $exchanges->where('to_currency_id', $currencyId)->sum('to_amount');
            $exchangeOut = (float) $exchanges->where('from_currency_id', $currencyId)->sum('from_amount');
            $received = (float) ($cash->provided_amount ?? $cash->approved_amount ?? $cash->calculated_amount ?? 0);
            $received = round($received + $exchangeIn - $exchangeOut, 2);
            $spent = (float) ($cash->spent_amount ?? 0);
            $returned = (float) ($cash->returned_amount ?? 0);

            $cash->updateQuietly([
                'balance' => round($received - $spent - $returned, 2),
            ]);
        }
    }

    /**
     * @return array{0: float, 1: float} [kwota w walucie planu, ekwiwalent PLN]
     */
    private function resolvePilotCashRemaining(
        EventSettlementCost $cost,
        \Illuminate\Support\Collection $allCosts,
        \App\Services\SettlementPaymentHealthService $health,
    ): array {
        $plannedPln = $health->indicativePlannedPlnForCost($cost);
        $paidPln = $health->paidPlnForPlanCost($cost, $allCosts);

        // Legacy: wpłata zapisana bezpośrednio na wierszu planu (bez *_payment).
        if ($paidPln <= \App\Services\SettlementPaymentHealthService::TOLERANCE
            && (float) ($cost->actual_amount_pln ?? 0) > \App\Services\SettlementPaymentHealthService::TOLERANCE
            && \App\Services\SettlementPaymentHealthService::isBookedPaymentStatus($cost->payment_status)) {
            $paidPln = (float) $cost->actual_amount_pln;
        }

        $remainingPln = max(0.0, round($plannedPln - $paidPln, 2));
        if ($remainingPln <= \App\Services\SettlementPaymentHealthService::TOLERANCE) {
            return [0.0, 0.0];
        }

        $plannedAmount = (float) ($cost->planned_amount ?? 0);
        $currency = $cost->relationLoaded('plannedCurrency')
            ? $cost->plannedCurrency
            : $cost->plannedCurrency()->first();
        $symbol = strtoupper((string) ($currency?->symbol ?? $currency?->code ?? 'PLN'));
        $rate = (float) ($cost->planned_rate ?? ($currency?->exchange_rate ?? 1));
        if ($rate <= 0) {
            $rate = 1.0;
        }

        if ($plannedAmount > 0 && $symbol !== '' && $symbol !== 'PLN') {
            $paidForeign = 0.0;
            foreach ($health->paymentRowsForPlanCost($cost, $allCosts) as $payment) {
                if (! \App\Services\SettlementPaymentHealthService::isBookedPaymentStatus($payment->payment_status)) {
                    continue;
                }
                $paidForeign += (float) ($payment->actual_amount ?? 0);
            }

            if ($paidForeign <= \App\Services\SettlementPaymentHealthService::TOLERANCE
                && (float) ($cost->actual_amount ?? 0) > \App\Services\SettlementPaymentHealthService::TOLERANCE
                && \App\Services\SettlementPaymentHealthService::isBookedPaymentStatus($cost->payment_status)) {
                $paidForeign = (float) $cost->actual_amount;
            }

            if ($paidForeign <= \App\Services\SettlementPaymentHealthService::TOLERANCE && $paidPln > 0) {
                $paidForeign = round($paidPln / $rate, 2);
            }

            $remainingForeign = max(0.0, round($plannedAmount - $paidForeign, 2));

            return [$remainingForeign, $remainingPln];
        }

        return [$remainingPln, $remainingPln];
    }

    protected function resolveFallbackPlnCurrencyId(): ?int
    {
        return Currency::query()
            ->where(function ($q) {
                $q->where('code', 'PLN')
                    ->orWhere('symbol', 'PLN')
                    ->orWhere('name', 'like', '%złoty%');
            })
            ->orderBy('id')
            ->value('id');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::$statuses[$this->status] ?? $this->status;
    }

    /**
     * Istniejące aktywne rozliczenie (draft/active/pilot_settled) — bez tworzenia.
     */
    public static function findActiveForEvent(Event $event): ?self
    {
        return $event->settlements()
            ->whereIn('status', ['draft', 'active', 'pilot_settled'])
            ->latest('id')
            ->first();
    }

    public static function findOrCreateActiveForEvent(Event $event): self
    {
        $existing = self::findActiveForEvent($event);

        if ($existing) {
            return $existing;
        }

        return $event->settlements()->create([
            'status' => 'draft',
            'pilot_id' => $event->assigned_to,
            'created_by' => Auth::id(),
        ]);
    }

    public function upsertCostFromProgramPoint(EventProgramPoint $point): EventSettlementCost
    {
        return \App\Services\EventSettlementImportService::for($this)->upsertCostFromProgramPoint($point);
    }
}
