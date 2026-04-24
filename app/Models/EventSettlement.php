<?php

namespace App\Models;

use App\Services\AgreementPaymentSyncService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

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
    ];

    protected $casts = [
        'planned_cost_pln' => 'decimal:2',
        'actual_cost_pln' => 'decimal:2',
        'participant_due_pln' => 'decimal:2',
        'participant_paid_pln' => 'decimal:2',
        'settled_at' => 'datetime',
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

    public function documents(): HasMany
    {
        return $this->hasMany(EventSettlementDocument::class, 'settlement_id')->latest('id');
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
     * Przelicza i zapisuje sumy z pozycji kosztów
     */
    public function recalculateTotals(): void
    {
        $costs = $this->costs()->get();
        $this->planned_cost_pln = $costs->sum('planned_amount_pln');
        $this->actual_cost_pln = $costs->sum(fn (EventSettlementCost $cost) => $this->resolvePaidCostPln($cost));

        $payments = $this->participantPayments()->get();
        $agreementTotals = $this->resolveAgreementPaymentTotals($payments);
        $this->participant_due_pln = $payments->sum('due_amount_pln') + $agreementTotals['due'];
        $this->participant_paid_pln = $payments->sum('paid_amount_pln') + $agreementTotals['paid'];

        $this->saveQuietly();
    }

    public function refreshDerivedData(): void
    {
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

        $syncService = app(AgreementPaymentSyncService::class);

        foreach ($event->agreements as $agreement) {
            $syncService->sync($agreement, $this);
        }
    }

    private function resolvePaidCostPln(EventSettlementCost $cost): float
    {
        if ($cost->payment_status === 'cancelled') {
            return 0.0;
        }

        if ($cost->actual_amount_pln !== null) {
            return (float) $cost->actual_amount_pln;
        }

        if (in_array($cost->payment_status, ['advance_paid', 'partially_paid'], true)) {
            $advanceAmount = (float) ($cost->advance_amount ?? 0);
            if ($advanceAmount <= 0) {
                return 0.0;
            }

            $rate = (float) ($cost->actual_rate ?? $cost->planned_rate ?? 1);

            return $advanceAmount * $rate;
        }

        if ($cost->payment_status === 'paid') {
            return (float) ($cost->planned_amount_pln ?? 0);
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

        $unsyncedAgreements = $event->agreements->filter(function (EventAgreement $agreement) use ($paymentIds, $references) {
            if (! $this->shouldIncludeAgreementInSettlementTotals($agreement)) {
                return false;
            }

            if ($agreement->participant_payment_id && in_array((int) $agreement->participant_payment_id, $paymentIds, true)) {
                return false;
            }

            $agreementReference = $this->normalizeSettlementReference($agreement->agreement_number);

            if ($agreementReference && in_array($agreementReference, $references, true)) {
                return false;
            }

            return true;
        });

        return [
            'due' => (float) $unsyncedAgreements->sum(fn (EventAgreement $agreement) => (float) ($agreement->amount_due ?? 0)),
            'paid' => (float) $unsyncedAgreements->sum(fn (EventAgreement $agreement) => (float) ($agreement->amount_paid ?? 0)),
        ];
    }

    private function shouldIncludeAgreementInSettlementTotals(EventAgreement $agreement): bool
    {
        if (($agreement->meta['is_individual_template'] ?? false) === true
            && empty($agreement->meta['parent_template_id'] ?? null)) {
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
        $event = $this->event()->with('programPoints.currency', 'bus', 'eventTemplate.hotelDays', 'dayInsurances.insurance')->first();
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
            $pln = $calculatedAmount;
            if ($currencyId && $pp->convert_to_pln) {
                $currency = $pp->currency;
                $pln = $calculatedAmount * ($currency?->exchange_rate ?? 1);
            }

            $this->costs()->updateOrCreate(
                ['source_type' => 'program_point', 'source_id' => $pp->id],
                [
                    'name' => $pp->name,
                    'planned_amount' => $calculatedAmount,
                    'planned_currency_id' => $currencyId,
                    'planned_rate' => $pp->currency?->exchange_rate ?? 1,
                    'planned_amount_pln' => $pln,
                    'paid_by' => 'office',
                    'advance_type' => 'full',
                    'payment_status' => 'planned',
                    'order' => $pp->order ?? 0,
                ]
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

            $this->costs()->updateOrCreate(
                ['source_type' => 'insurance_day', 'source_id' => $dayInsurance->id],
                [
                    'name' => 'Ubezpieczenie Dzień '.$day.': '.($insurance->name ?? ('ID '.$insurance->id)),
                    'planned_amount' => $amount,
                    'planned_currency_id' => $fallbackPlnCurrencyId,
                    'planned_rate' => 1,
                    'planned_amount_pln' => $amount,
                    'paid_by' => 'office',
                    'advance_type' => 'full',
                    'payment_status' => 'planned',
                    'order' => 1100 + $day,
                ]
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
        if (! $event->bus) {
            return;
        }

        $bus = $event->bus;
        $transferKm = (float) ($event->transfer_km ?? 0);
        $programKm = (float) ($event->program_km ?? 0);
        $duration = (int) ($event->duration_days ?? 1);
        $participantCount = (int) ($event->participant_count ?? 1);

        // Obliczenie kosztów transportu
        $totalKm = 2 * $transferKm + $programKm;
        $includedKm = $duration * ((float) ($bus->package_km_per_day ?? 0));
        $baseCost = $duration * ((float) ($bus->package_price_per_day ?? 0));

        $transportCostPerBus = $baseCost;
        if ($totalKm > $includedKm) {
            $extraKm = $totalKm - $includedKm;
            $transportCostPerBus += $extraKm * ((float) ($bus->extra_km_price ?? 0));
        }

        // Liczba autobusów
        $busCapacity = (int) ($bus->capacity > 0 ? $bus->capacity : 50);
        $busCount = (int) ceil($participantCount / $busCapacity);
        $totalTransportCost = $transportCostPerBus * $busCount;

        // Konwersja na PLN jeśli potrzeba
        $busCurrency = $bus->currency ?? 'PLN';
        $currencyId = $fallbackPlnCurrencyId;
        $rate = 1;
        $totalTransportCostPln = $totalTransportCost;

        if ($busCurrency !== 'PLN') {
            $currency = Currency::where('symbol', $busCurrency)->first();
            if ($currency) {
                $currencyId = $currency->id;
                $rate = (float) ($currency->exchange_rate ?? 1);
                $totalTransportCostPln = $totalTransportCost * $rate;
            }
        }

        if ($totalTransportCost > 0) {
            $this->costs()->updateOrCreate(
                ['source_type' => 'transport', 'source_id' => null],
                [
                    'name' => 'Koszt transportu (autokar)',
                    'planned_amount' => $totalTransportCost,
                    'planned_currency_id' => $currencyId,
                    'planned_rate' => $rate,
                    'planned_amount_pln' => $totalTransportCostPln,
                    'paid_by' => 'office',
                    'advance_type' => 'full',
                    'payment_status' => 'planned',
                    'order' => 1000, // wysoki numer żeby był na dole listy
                ]
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
            $this->costs()->updateOrCreate(
                ['source_type' => 'accommodation', 'source_id' => null],
                [
                    'name' => 'Koszty noclegu (hotel)',
                    'planned_amount' => $totalAccommodationCostPln,
                    'planned_currency_id' => $fallbackPlnCurrencyId,
                    'planned_rate' => 1,
                    'planned_amount_pln' => $totalAccommodationCostPln,
                    'paid_by' => 'office',
                    'advance_type' => 'full',
                    'payment_status' => 'planned',
                    'order' => 1001,
                ]
            );
        }
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

            $this->pilotCashPreparations()->updateOrCreate(
                ['currency_id' => $currencyId],
                [
                    'calculated_amount' => $total,
                    'rate_used' => $rate,
                    'pln_equivalent' => $pln,
                    'status' => 'calculated',
                ]
            );
        }

        $this->pilotCashPreparations()
            ->when(! empty($usedCurrencyIds), fn ($query) => $query->whereNotIn('currency_id', $usedCurrencyIds))
            ->when(empty($usedCurrencyIds), fn ($query) => $query)
            ->delete();
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
        if ($currencyId && $currencyCode !== 'PLN') {
            $plannedAmountPln = $plannedAmount * $rate;
        }

        $activeReservations = $point->reservations->where('status', '!=', 'cancelled');
        $reservationContractorId = $activeReservations
            ->pluck('contractor_id')
            ->filter()
            ->last();
        $contractorId = $point->contractor_id ?: $reservationContractorId;
        $hasReservations = $activeReservations->isNotEmpty();
        $reservedAmount = (float) $activeReservations->sum(fn (Reservation $reservation) => (float) ($reservation->reserved_amount ?? 0));
        $reservationExpiry = $activeReservations
            ->pluck('expires_at')
            ->filter()
            ->sort()
            ->first();

        $existingCost = $this->costs()
            ->where('source_type', 'program_point')
            ->where('source_id', $point->id)
            ->first();

        $paymentStatus = $existingCost?->payment_status;

        if (in_array($paymentStatus, [null, 'planned', 'reservation_required', 'reserved'], true)) {
            $paymentStatus = $hasReservations ? 'reserved' : 'planned';
        }

        $cost = $this->costs()->updateOrCreate(
            [
                'source_type' => 'program_point',
                'source_id' => $point->id,
            ],
            [
                'name' => $point->name ?: ($point->templatePoint->name ?? ('Punkt #'.$point->id)),
                'planned_amount' => $plannedAmount,
                'planned_currency_id' => $currencyId,
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
            ]
        );

        if ($point->reservations()->exists()) {
            $point->reservations()->update([
                'settlement_cost_id' => $cost->id,
            ]);
        }

        $this->recalculateTotals();

        return $cost;
    }
}
