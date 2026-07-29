<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
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
     * Przelicza i zapisuje sumy z pozycji kosztów
     */
    public function recalculateTotals(): void
    {
        $costs = $this->costs()->get();
        $this->planned_cost_pln = round((float) $costs
            ->reject(fn (EventSettlementCost $cost): bool => EventSettlementCost::isPaymentSourceType($cost->source_type))
            ->sum(fn (EventSettlementCost $cost): float => (float) ($cost->planned_amount_pln ?? 0)), 2);
        $this->actual_cost_pln = round((float) $costs->sum(fn (EventSettlementCost $cost) => $this->resolvePaidCostPln($cost)), 2);

        $payments = $this->participantPayments()->get();
        $agreementTotals = $this->resolveAgreementPaymentTotals($payments);
        $this->participant_due_pln = $payments->sum('due_amount_pln') + $agreementTotals['due'];
        $this->participant_paid_pln = $payments->sum('paid_amount_pln') + $agreementTotals['paid'];

        $this->saveQuietly();
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

    private function resolvePaidCostPln(EventSettlementCost $cost): float
    {
        if ($cost->payment_status === 'cancelled') {
            return 0.0;
        }

        if (EventSettlementCost::isPaymentSourceType($cost->source_type)) {
            return (float) ($cost->actual_amount_pln ?? 0);
        }

        if ($cost->source_type === 'manual' && $cost->actual_amount_pln !== null) {
            return (float) $cost->actual_amount_pln;
        }

        return 0.0;
    }

    private function resolveAgreementPaymentTotals($participantPayments): array
    {
        $event = $this->event()->with('agreements')->first();

        if (! $event) {
            return ['due' => 0.0, 'paid' => 0.0];
        }

        $paymentIds = $participantPayments
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        $references = $participantPayments
            ->flatMap(function (EventSettlementParticipantPayment $payment) {
                return array_filter([
                    $this->normalizeSettlementReference($payment->booking_reference),
                    $this->normalizeSettlementReference($payment->document_number),
                ]);
            })
            ->unique()
            ->values()
            ->all();

        $unsyncedAgreements = $event->agreements->filter(function ($agreement) use ($paymentIds, $references) {
            if (! $this->shouldIncludeAgreementInSettlementTotals($agreement)) {
                return false;
            }

            if ($agreement->participant_payment_id && in_array((int) $agreement->participant_payment_id, $paymentIds, true)) {
                return false;
            }

            $agreementReference = $this->normalizeSettlementReference(
                $agreement->operational_number
                    ?? $agreement->agreement_number
                    ?? $agreement->contract_number
                    ?? null
            );

            if ($agreementReference && in_array($agreementReference, $references, true)) {
                return false;
            }

            return true;
        });

        return [
            'due' => (float) $unsyncedAgreements->sum(fn ($agreement) => (float) ($agreement->amount_due ?? $agreement->total_price ?? 0)),
            'paid' => (float) $unsyncedAgreements->sum(fn ($agreement) => (float) ($agreement->amount_paid ?? 0)),
        ];
    }

    private function shouldIncludeAgreementInSettlementTotals(EventAgreement|Contract $agreement): bool
    {
        if (($agreement->meta['is_individual_template'] ?? false) === true
            && empty($agreement->meta['parent_template_id'] ?? null)) {
            return false;
        }

        if ($agreement instanceof Contract && $agreement->usesIndividualParticipantPayments()) {
            return false;
        }

        if (in_array($agreement->status, ['draft', 'template', 'cancelled'], true)) {
            return false;
        }

        if ($agreement->payment_status === 'failed') {
            return false;
        }

        return (float) ($agreement->amount_due ?? 0) > 0
            || (float) ($agreement->amount_paid ?? 0) > 0
            || $agreement->participant_payment_id !== null;
    }

    private function normalizeSettlementReference(?string $value): ?string
    {
        $value = strtoupper(trim((string) $value));

        return $value !== '' ? $value : null;
    }

    /**
     * Importuje wszystkie punkty programu imprezy oraz koszty transportu i noclegu jako pozycje kosztów planowanych
     */
    public function importFromEvent(): void
    {
        $event = $this->event()->with('programPoints.currency', 'programPoints.reservations', 'bus', 'eventTemplate.hotelDays', 'dayInsurances.insurance')->first();
        if (! $event) {
            return;
        }

        $fallbackPlnCurrencyId = $this->resolveFallbackPlnCurrencyId();
        $participantCount = max(1, (int) ($event->participant_count ?? 1));
        $importedProgramPointIds = [];

        // Import programu (punkty programu)
        foreach ($event->programPoints as $pp) {
            if (! $pp->include_in_calculation || ! $pp->active) {
                continue;
            }

            // W rozliczeniu bazujemy na realnej, zapisanej kwocie punktu programu,
            // żeby każda ręczna zmiana ceny była widoczna 1:1.
            $calculatedAmount = $pp->resolveEffectiveTotalPrice($participantCount);

            $currencyId = $pp->currency_id ?: $fallbackPlnCurrencyId;
            $currency = $pp->currency;
            $currencyCode = strtoupper((string) ($currency?->code ?? $currency?->symbol ?? 'PLN'));
            $convertToPln = (bool) ($pp->convert_to_pln ?? false);
            $pln = $calculatedAmount;

            if ($currencyId && $currencyCode !== 'PLN') {
                $pln = $convertToPln
                    ? $calculatedAmount * ($currency?->exchange_rate ?? 1)
                    : null;
            }

            $existing = $this->costs()
                ->where('source_type', 'program_point')
                ->where('source_id', $pp->id)
                ->first();

            $attributes = [
                'name' => $pp->name,
                'planned_amount' => $calculatedAmount,
                'planned_currency_id' => $currencyId,
                'planned_convert_to_pln' => $convertToPln,
                'planned_rate' => $currency?->exchange_rate ?? 1,
                'planned_amount_pln' => $pln,
                'contractor_id' => $this->resolveContractorIdForProgramPoint($pp),
                'paid_by' => $existing?->paid_by ?? $this->inferPaidByFromProgramPoint($pp),
                'advance_type' => 'full',
                'payment_status' => $existing?->payment_status ?? 'planned',
                'order' => $pp->order ?? 0,
            ];

            $this->costs()->updateOrCreate(
                ['source_type' => 'program_point', 'source_id' => $pp->id],
                $this->mergeExistingCostPilotReporting($existing, $attributes)
            );

            $importedProgramPointIds[] = (int) $pp->id;
        }

        // Usuń koszty punktów programu, które już nie powinny być liczone
        $this->costs()
            ->where('source_type', 'program_point')
            ->when(
                ! empty($importedProgramPointIds),
                fn ($query) => $query->whereNotIn('source_id', $importedProgramPointIds),
                fn ($query) => $query
            )
            ->delete();

        $softDeletedProgramPointIds = EventProgramPoint::query()
            ->onlyTrashed()
            ->where('event_id', $event->id)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($softDeletedProgramPointIds !== []) {
            $this->costs()
                ->where('source_type', 'program_point')
                ->whereIn('source_id', $softDeletedProgramPointIds)
                ->delete();

            $this->costs()
                ->where('source_type', 'program_point_payment')
                ->whereIn('source_id', $softDeletedProgramPointIds)
                ->delete();
        }

        $this->costs()
            ->where('source_type', 'program_point_payment')
            ->when(
                ! empty($importedProgramPointIds),
                fn ($query) => $query->whereNotIn('source_id', $importedProgramPointIds),
                fn ($query) => $query
            )
            ->delete();

        // Import kosztów transportu (autokar)
        $this->importTransportCosts($event, $fallbackPlnCurrencyId);

        // Import kosztów noclegu (hotel)
        $this->importAccommodationCosts($event, $fallbackPlnCurrencyId);

        // Import kosztów ubezpieczeń dziennych
        $this->importInsuranceCosts($event, $fallbackPlnCurrencyId);

        $this->recalculateTotals();
    }

    /**
     * Importuje koszty ubezpieczeń przypisanych do dni imprezy.
     */
    private function importInsuranceCosts(Event $event, ?int $fallbackPlnCurrencyId): void
    {
        $participantCount = max(1, (int) ($event->participant_count ?? 1));
        $importedInsuranceDayIds = [];

        foreach ($event->dayInsurances as $dayInsurance) {
            $insurance = $dayInsurance->insurance;

            if (! $insurance || ! $insurance->insurance_enabled || ! $insurance->active) {
                continue;
            }

            $amount = (float) ($insurance->price_per_person ?? 0) * $participantCount;
            if ($amount <= 0) {
                continue;
            }

            $day = (int) ($dayInsurance->day ?? 0);

            $existing = $this->costs()
                ->where('source_type', 'insurance_day')
                ->where('source_id', $dayInsurance->id)
                ->first();

            $attributes = [
                'name' => 'Ubezpieczenie Dzień '.$day.': '.($insurance->name ?? ('ID '.$insurance->id)),
                'planned_amount' => $amount,
                'planned_currency_id' => $fallbackPlnCurrencyId,
                'planned_rate' => 1,
                'planned_amount_pln' => $amount,
                'paid_by' => $existing?->paid_by ?? 'office',
                'advance_type' => 'full',
                'payment_status' => $existing?->payment_status ?? 'planned',
                'order' => 1100 + $day,
            ];

            $this->costs()->updateOrCreate(
                ['source_type' => 'insurance_day', 'source_id' => $dayInsurance->id],
                $this->mergeExistingCostPilotReporting($existing, $attributes)
            );

            $importedInsuranceDayIds[] = (int) $dayInsurance->id;
        }

        // Usuń nieaktualne koszty ubezpieczeń po odpięciu ubezpieczenia od dnia
        $this->costs()
            ->where('source_type', 'insurance_day')
            ->when(
                ! empty($importedInsuranceDayIds),
                fn ($query) => $query->whereNotIn('source_id', $importedInsuranceDayIds),
                fn ($query) => $query
            )
            ->delete();
    }

    /**
     * Importuje koszty transportu z autokarów
     */
    private function importTransportCosts(Event $event, ?int $fallbackPlnCurrencyId): void
    {
        $calculator = new \App\Services\EventTransportCostCalculator($event);

        if (! $calculator->usesManualTransportCost() && ! $event->bus) {
            return;
        }

        $manual = $calculator->usesManualTransportCost();
        $totalTransportCostPln = $calculator->effectiveTransportCost();
        $currencyId = $fallbackPlnCurrencyId;
        $rate = 1.0;
        $plannedAmount = $totalTransportCostPln;

        if (! $manual && $event->bus) {
            $bus = $event->bus;
            $busCurrency = $bus->currency ?? 'PLN';

            if ($busCurrency !== 'PLN') {
                $currency = Currency::where('symbol', $busCurrency)->first();
                if ($currency) {
                    $currencyId = $currency->id;
                    $rate = (float) ($currency->exchange_rate ?? 1);
                    $plannedAmount = $rate > 0 ? round($totalTransportCostPln / $rate, 2) : $totalTransportCostPln;
                }
            }
        }

        if ($totalTransportCostPln > 0) {
            $existing = $this->costs()
                ->where('source_type', 'transport')
                ->whereNull('source_id')
                ->first();

            $attributes = [
                'name' => $manual
                    ? \App\Services\EventTransportCostCalculator::MANUAL_TRANSPORT_POINT_NAME
                    : \App\Services\EventTransportCostCalculator::TRANSPORT_POINT_NAME,
                'planned_amount' => $plannedAmount,
                'planned_currency_id' => $currencyId,
                'planned_rate' => $rate,
                'planned_amount_pln' => $totalTransportCostPln,
                'paid_by' => $existing?->paid_by ?? 'office',
                'advance_type' => 'full',
                'payment_status' => $existing?->payment_status ?? 'planned',
                'order' => 1000,
            ];

            $this->costs()->updateOrCreate(
                ['source_type' => 'transport', 'source_id' => null],
                $this->mergeExistingCostPilotReporting($existing, $attributes)
            );
        }
    }

    /**
     * Importuje koszty noclegu z hoteli.
     *
     * Używa algorytmu DP (tak jak EventTemplateCalculationEngine) do wyznaczenia
     * minimalnej kombinacji pokoi pokrywającej faktyczną liczebność każdej grupy
     * (uczestnicy, gratis, obsługa, kierowca). Dzięki temu kwota w rozliczeniu
     * odpowiada kwocie w kalkulacji.
     */
    private function importAccommodationCosts(Event $event, ?int $fallbackPlnCurrencyId): void
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('event_hotel_stays') && $event->hotelStays()->exists()) {
            $this->importAccommodationCostsFromEventPlan($event, $fallbackPlnCurrencyId);

            return;
        }

        if (! $event->eventTemplate) {
            return;
        }

        $template = $event->eventTemplate;
        $hotelDays = $template->hotelDays()->get();
        if ($hotelDays->isEmpty()) {
            return;
        }

        // Ustal liczebność grup z wariantu ilościowego imprezy
        $participantCount = max(1, (int) ($event->participant_count ?? 1));
        $qtyVariant = $event->qtyVariants()
            ->get()
            ->sortBy(fn ($v) => abs(((int) ($v->qty ?? 0)) - $participantCount))
            ->first();

        $groupCounts = [
            'qty' => $participantCount,
            'gratis' => (int) ($qtyVariant->gratis ?? 0),
            'staff' => (int) ($qtyVariant->staff ?? 0),
            'driver' => (int) ($qtyVariant->driver ?? 0),
        ];

        $totalAccommodationCostPln = 0;

        foreach ($hotelDays as $hotelDay) {
            $dayNumber = (int) ($hotelDay->day ?? 0);
            if ($dayNumber <= 0) {
                continue;
            }

            $roomGroupMap = [
                'qty' => $hotelDay->hotel_room_ids_qty ?? [],
                'gratis' => $hotelDay->hotel_room_ids_gratis ?? [],
                'staff' => $hotelDay->hotel_room_ids_staff ?? [],
                'driver' => $hotelDay->hotel_room_ids_driver ?? [],
            ];

            $dayTotalPln = 0;

            foreach ($roomGroupMap as $groupType => $roomIds) {
                $peopleCount = $groupCounts[$groupType];
                if ($peopleCount <= 0 || empty($roomIds)) {
                    continue;
                }

                $rooms = HotelRoom::whereIn('id', $roomIds)->get();
                if ($rooms->isEmpty()) {
                    continue;
                }

                // DP: minimalny koszt kombinacji pokoi pokrywającej >= $peopleCount osób
                $maxCapacity = $rooms->sum('people_count') * $peopleCount;
                $dp = array_fill(0, $maxCapacity + 1, INF);
                $choice = array_fill(0, $maxCapacity + 1, null);
                $dp[0] = 0;

                foreach ($rooms as $room) {
                    $cap = max(1, (int) $room->people_count);
                    for ($i = $cap; $i <= $maxCapacity; $i++) {
                        if ($dp[$i] > $dp[$i - $cap] + $room->price) {
                            $dp[$i] = $dp[$i - $cap] + $room->price;
                            $choice[$i] = $room->id;
                        }
                    }
                }

                // Najtańsze rozwiązanie pokrywające >= $peopleCount
                $minCost = INF;
                $bestI = null;
                for ($i = $peopleCount; $i <= $maxCapacity; $i++) {
                    if ($dp[$i] < $minCost) {
                        $minCost = $dp[$i];
                        $bestI = $i;
                    }
                }

                if ($minCost === INF || $bestI === null) {
                    continue;
                }

                // Odtwórz wybór pokoi i zsumuj koszt w PLN
                $i = $bestI;
                while ($i > 0 && $choice[$i] !== null) {
                    $room = $rooms->firstWhere('id', $choice[$i]);
                    $roomPrice = (float) ($room->price ?? 0);
                    $roomCurrency = (string) ($room->currency ?? 'PLN');
                    $convertFlag = (bool) ($room->convert_to_pln ?? false);

                    if ($roomCurrency === 'PLN' || $convertFlag) {
                        if ($roomCurrency !== 'PLN') {
                            $rate = Currency::where('symbol', $roomCurrency)->first()?->exchange_rate ?? 1;
                            $roomPrice *= $rate;
                        }
                        $dayTotalPln += $roomPrice;
                    } else {
                        // Waluta obca bez konwersji → traktuj jako PLN (brak kursu w settlement)
                        $dayTotalPln += $roomPrice;
                    }

                    $cap = max(1, (int) $room->people_count);
                    $i -= $cap;
                }
            }

            $totalAccommodationCostPln += $dayTotalPln;
        }

        // Dodaj/zaktualizuj jako jedną zagregowaną pozycję kosztów noclegu
        if ($totalAccommodationCostPln > 0) {
            $existing = $this->costs()
                ->where('source_type', 'accommodation')
                ->whereNull('source_id')
                ->first();

            $attributes = [
                'name' => 'Koszty noclegu (hotel)',
                'planned_amount' => $totalAccommodationCostPln,
                'planned_currency_id' => $fallbackPlnCurrencyId,
                'planned_rate' => 1,
                'planned_amount_pln' => $totalAccommodationCostPln,
                'paid_by' => $existing?->paid_by ?? 'office',
                'advance_type' => 'full',
                'payment_status' => $existing?->payment_status ?? 'planned',
                'order' => 1001,
            ];

            $this->costs()->updateOrCreate(
                ['source_type' => 'accommodation', 'source_id' => null],
                $this->mergeExistingCostPilotReporting($existing, $attributes)
            );
        }
    }

    private function importAccommodationCostsFromEventPlan(Event $event, ?int $fallbackPlnCurrencyId): void
    {
        $event->loadMissing(['hotelStays.roomLines.currency']);
        $totalAccommodationCostPln = app(\App\Services\EventHotelPlanService::class)->totalPlnForEvent($event);

        if ($totalAccommodationCostPln <= 0) {
            return;
        }

        $existing = $this->costs()
            ->where('source_type', 'accommodation')
            ->whereNull('source_id')
            ->first();

        $attributes = [
            'name' => 'Koszty noclegu (hotel)',
            'planned_amount' => $totalAccommodationCostPln,
            'planned_currency_id' => $fallbackPlnCurrencyId,
            'planned_rate' => 1,
            'planned_amount_pln' => $totalAccommodationCostPln,
            'paid_by' => $existing?->paid_by ?? 'office',
            'advance_type' => 'full',
            'payment_status' => $existing?->payment_status ?? 'planned',
            'order' => 1001,
        ];

        $this->costs()->updateOrCreate(
            ['source_type' => 'accommodation', 'source_id' => null],
            $this->mergeExistingCostPilotReporting($existing, $attributes)
        );
    }

    /**
     * Oblicza gotówkę pilota per waluta i zapisuje/aktualizuje wpisy
     */
    public function recalculatePilotCash(): void
    {
        $pilotCosts = $this->costs()
            ->where('paid_by', 'pilot')
            ->where('payment_status', '!=', 'cancelled')
            ->get();

        $fallbackPlnCurrencyId = $this->resolveFallbackPlnCurrencyId();

        $byCurrency = $pilotCosts->groupBy(function ($cost) use ($fallbackPlnCurrencyId) {
            if ($cost->actual_amount !== null) {
                return $cost->actual_currency_id ?: $cost->planned_currency_id ?: $fallbackPlnCurrencyId;
            }

            return $cost->planned_currency_id ?: $fallbackPlnCurrencyId;
        });

        $usedCurrencyIds = [];

        foreach ($byCurrency as $currencyId => $items) {
            if (! $currencyId) {
                continue;
            }

            $usedCurrencyIds[] = (int) $currencyId;

            $total = $items->sum(fn (EventSettlementCost $cost) => $this->resolvePilotCashAmount($cost));
            $rate = (float) ($items->first()?->actual_rate ?? $items->first()?->planned_rate ?? 1);
            $pln = $items->sum(fn (EventSettlementCost $cost) => $this->resolvePilotCashAmountPln($cost));

            $existing = $this->pilotCashPreparations()->where('currency_id', $currencyId)->first();

            $attributes = [
                'calculated_amount' => $total,
                'rate_used' => $rate,
                'pln_equivalent' => $pln,
            ];

            if (! $existing || blank($existing->provided_amount)) {
                $attributes['status'] = 'calculated';
            }

            $this->pilotCashPreparations()->updateOrCreate(
                ['currency_id' => $currencyId],
                $attributes
            );
        }

        $staleQuery = $this->pilotCashPreparations()->whereNull('provided_amount');

        if (! empty($usedCurrencyIds)) {
            $staleQuery->whereNotIn('currency_id', $usedCurrencyIds);
        }

        $staleQuery->delete();

        $this->applyPilotCurrencyExchangeBalances();
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

    private function resolvePilotCashAmount(EventSettlementCost $cost): float
    {
        if ($cost->actual_amount !== null) {
            return (float) $cost->actual_amount;
        }

        if (in_array($cost->payment_status, ['advance_paid', 'partially_paid'], true) && (float) ($cost->advance_amount ?? 0) > 0) {
            return (float) $cost->advance_amount;
        }

        return (float) ($cost->planned_amount ?? 0);
    }

    private function resolvePilotCashAmountPln(EventSettlementCost $cost): float
    {
        if ($cost->actual_amount_pln !== null) {
            return (float) $cost->actual_amount_pln;
        }

        if (in_array($cost->payment_status, ['advance_paid', 'partially_paid'], true) && (float) ($cost->advance_amount ?? 0) > 0) {
            $rate = (float) ($cost->actual_rate ?? $cost->planned_rate ?? 1);

            return (float) $cost->advance_amount * $rate;
        }

        return (float) ($cost->planned_amount_pln ?? 0);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function mergeExistingCostPilotReporting(?EventSettlementCost $existing, array $attributes): array
    {
        if (! $existing) {
            return $attributes;
        }

        foreach ([
            'paid_by',
            'actual_amount',
            'actual_currency_id',
            'actual_rate',
            'actual_amount_pln',
            'notes',
            'payment_method',
            'payment_status',
            'advance_amount',
        ] as $field) {
            $value = $existing->{$field};

            if ($value !== null && $value !== '') {
                $attributes[$field] = $value;
            }
        }

        return $attributes;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Reservation>|null  $activeReservations
     */
    protected function resolveContractorIdForProgramPoint(
        EventProgramPoint $point,
        ?\Illuminate\Support\Collection $activeReservations = null,
    ): ?int {
        if (filled($point->contractor_id)) {
            return (int) $point->contractor_id;
        }

        $point->loadMissing('reservations');
        $reservations = $activeReservations ?? $point->reservations->whereNotIn('status', ['cancelled', 'not_required']);

        $reservationContractorId = $reservations
            ->pluck('contractor_id')
            ->filter()
            ->last();

        return $reservationContractorId ? (int) $reservationContractorId : null;
    }

    protected function inferPaidByFromProgramPoint(EventProgramPoint $point): string
    {
        $text = mb_strtolower(trim(($point->pilot_notes ?? '').' '.($point->office_notes ?? '')));

        $pilotHints = [
            'pilot płaci',
            'pilot placi',
            'płaci pilot',
            'placi pilot',
            'gotówka pilota',
            'gotowka pilota',
            'dopłata pilota',
            'doplata pilota',
            'pilot dopłaca',
            'pilot doplaca',
        ];

        foreach ($pilotHints as $hint) {
            if (str_contains($text, $hint)) {
                return 'pilot';
            }
        }

        return 'office';
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

    public static function findOrCreateActiveForEvent(Event $event): self
    {
        $existing = $event->settlements()
            ->whereIn('status', ['draft', 'active', 'pilot_settled'])
            ->latest('id')
            ->first();

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
        $point->loadMissing(['event', 'reservations', 'currency', 'templatePoint']);

        // Rozliczenie punktu powinno odzwierciedlać faktyczny total_price punktu.
        $participantCount = max(1, (int) ($point->event?->participant_count ?? 1));
        $plannedAmount = $point->resolveEffectiveTotalPrice($participantCount);

        $currency = $point->currency;
        $currencyCode = strtoupper((string) ($currency?->code ?? $currency?->symbol ?? 'PLN'));
        $rate = (float) ($currency?->exchange_rate ?? 1);
        $currencyId = $point->currency_id ?: $this->resolveFallbackPlnCurrencyId();

        $plannedAmountPln = $plannedAmount;
        $convertToPln = (bool) ($point->convert_to_pln ?? false);
        if ($convertToPln && $currencyId && $currencyCode !== 'PLN') {
            $plannedAmountPln = $plannedAmount * $rate;
        } elseif (! $convertToPln && $currencyId && $currencyCode !== 'PLN') {
            $plannedAmountPln = null;
        }

        $activeReservations = $point->reservations->whereNotIn('status', ['cancelled', 'not_required']);
        $contractorId = $this->resolveContractorIdForProgramPoint($point, $activeReservations);
        $hasReservations = $activeReservations->isNotEmpty();
        $reservedAmount = (float) $activeReservations->sum(fn (Reservation $reservation) => (float) ($reservation->reserved_amount ?? 0));
        $reservationExpiry = $this->resolveReservationAdvanceDueDate($activeReservations);

        $existingCost = $this->costs()
            ->where('source_type', 'program_point')
            ->where('source_id', $point->id)
            ->first();

        $paymentStatus = $existingCost?->payment_status;
        $depositPaidAt = $activeReservations
            ->map(fn (Reservation $reservation) => $reservation->deposit_paid_at)
            ->filter()
            ->sort()
            ->first();

        if ($depositPaidAt) {
            $paymentStatus = 'advance_paid';
        } elseif (in_array($paymentStatus, [null, 'planned', 'reservation_required', 'reserved'], true)) {
            $paymentStatus = $hasReservations ? 'reserved' : 'planned';
        }

        $costPayload = [
            'name' => $point->name ?: ($point->templatePoint->name ?? ('Punkt #'.$point->id)),
            'planned_amount' => $plannedAmount,
            'planned_currency_id' => $currencyId,
            'planned_convert_to_pln' => $convertToPln,
            'planned_rate' => $rate,
            'planned_amount_pln' => $plannedAmountPln,
            'contractor_id' => $contractorId,
            'paid_by' => 'office',
            'advance_type' => $hasReservations ? 'deposit' : ($existingCost?->advance_type ?? 'full'),
            'payment_status' => $paymentStatus,
            'advance_amount' => $reservedAmount > 0 ? $reservedAmount : null,
            'advance_due_date' => $reservationExpiry,
            'order' => $point->order ?? 0,
            'notes' => $point->notes,
        ];

        if ($depositPaidAt) {
            $costPayload['paid_at'] = self::sanitizeTimestampDate($depositPaidAt);
        }

        $cost = $this->costs()->updateOrCreate(
            [
                'source_type' => 'program_point',
                'source_id' => $point->id,
            ],
            $costPayload
        );

        foreach ($point->reservations as $reservation) {
            if ((int) $reservation->settlement_cost_id !== (int) $cost->id) {
                $reservation->forceFill(['settlement_cost_id' => $cost->id])->saveQuietly();
            }
        }

        $this->recalculateTotals();

        return $cost;
    }

    /**
     * @param  Collection<int, Reservation>  $activeReservations
     */
    private function resolveReservationAdvanceDueDate(Collection $activeReservations): ?Carbon
    {
        $date = $activeReservations
            ->map(fn (Reservation $reservation) => $reservation->deposit_due_at
                ?? $reservation->confirm_by
                ?? $reservation->expires_at
                ?? $reservation->reserved_at)
            ->filter()
            ->sortBy(fn (Carbon $value) => $value->timestamp)
            ->first();

        return self::sanitizeTimestampDate($date);
    }

    private static function sanitizeTimestampDate(mixed $date): ?Carbon
    {
        if (! $date instanceof Carbon) {
            return null;
        }

        if ($date->year < 1970 || $date->year > 2038) {
            return null;
        }

        return $date;
    }
}
