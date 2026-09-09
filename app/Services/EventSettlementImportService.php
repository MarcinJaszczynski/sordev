<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Currency;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\HotelRoom;
use App\Models\Reservation;
use App\Support\ProgramPointCostPricing;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Import kosztów rozliczenia z imprezy (program, transport, hotel, ubezpieczenia)
 * oraz sync pojedynczego punktu programu → EventSettlementCost.
 *
 * Warstwa Service — model EventSettlement deleguje tu przez cienkie wrappery.
 */
final class EventSettlementImportService
{
    public function __construct(
        private EventSettlement $settlement,
    ) {}

    public static function for(EventSettlement $settlement): self
    {
        return new self($settlement);
    }

    /**
     * Importuje wszystkie punkty programu imprezy oraz koszty transportu i noclegu jako pozycje kosztów planowanych
     */
    public function import(): void
    {
        $event = $this->settlement->event()->with(
            'programPoints.currency',
            'programPoints.reservations',
            'bus',
            'eventTemplate.hotelDays',
            'dayInsurances.insurance',
            'hotelStays',
        )->first();
        if (! $event) {
            return;
        }

        $fallbackPlnCurrencyId = $this->resolveFallbackPlnCurrencyId();
        $importedProgramPointIds = [];

        // Import programu (punkty programu)
        $hasHotelPlan = $event->relationLoaded('hotelStays')
            ? $event->hotelStays->isNotEmpty()
            : $event->hotelStays()->exists();
        $skippedHotelPointIds = [];

        foreach ($event->programPoints as $pp) {
            if (! $pp->include_in_calculation || ! $pp->active) {
                continue;
            }

            // Nocleg z planu hotelowego → accommodation_hotel (nie osobny program_point).
            if ($hasHotelPlan && (bool) ($pp->is_hotel ?? false)) {
                $skippedHotelPointIds[] = (int) $pp->id;

                continue;
            }

            // Plan imprezy = planned_price (seed z kalkulacji gdy puste).
            // Kalkulacja w UI Finansów liczy się osobno live z unit_price.
            $plannedAmount = $this->resolveProgramPointPlanAmount($pp, $event);

            $currencyId = $this->normalizeCurrencyId($pp->currency_id) ?? $fallbackPlnCurrencyId;
            $currency = $pp->currency;
            $currencyCode = strtoupper((string) ($currency?->code ?? $currency?->symbol ?? 'PLN'));
            $convertToPln = (bool) ($pp->convert_to_pln ?? false);
            $pln = $this->plannedAmountToPln($plannedAmount, $currencyId, $currencyCode, $convertToPln, (float) ($currency?->exchange_rate ?? 1));

            $existing = $this->settlement->costs()
                ->where('source_type', 'program_point')
                ->where('source_id', $pp->id)
                ->first();

            // Plan (ustalenia): seed przy tworzeniu; przy update sync tylko z jawnego planned_price.
            // Zmiana unit_price przelicza wyłącznie Kalkulację (live) — nie nadpisuje Planu przez fallback.
            $attributes = [
                'name' => $pp->name,
                'contractor_id' => $this->resolveContractorIdForProgramPoint($pp),
                'paid_by' => $existing?->paid_by ?? $this->inferPaidByFromProgramPoint($pp),
                'advance_type' => $existing?->advance_type ?? 'full',
                'payment_status' => $existing?->payment_status ?? 'planned',
                'order' => $pp->order ?? 0,
            ];

            $explicitPlan = round((float) ($pp->planned_price ?? 0), 2);
            if (! $existing) {
                $attributes = array_merge($attributes, [
                    'planned_amount' => $plannedAmount,
                    'planned_currency_id' => $currencyId,
                    'planned_convert_to_pln' => $convertToPln,
                    'planned_rate' => $currency?->exchange_rate ?? 1,
                    'planned_amount_pln' => $pln,
                ]);
            } elseif ($explicitPlan > 0.009) {
                $attributes = array_merge($attributes, [
                    'planned_amount' => $explicitPlan,
                    'planned_currency_id' => $currencyId,
                    'planned_convert_to_pln' => $convertToPln,
                    'planned_rate' => $currency?->exchange_rate ?? 1,
                    'planned_amount_pln' => $this->plannedAmountToPln(
                        $explicitPlan,
                        $currencyId,
                        $currencyCode,
                        $convertToPln,
                        (float) ($currency?->exchange_rate ?? 1),
                    ),
                ]);
            }

            $this->settlement->costs()->updateOrCreate(
                ['source_type' => 'program_point', 'source_id' => $pp->id],
                $this->mergeExistingCostPilotReporting($existing, $attributes)
            );

            $importedProgramPointIds[] = (int) $pp->id;
        }

        $retainProgramPointIds = array_values(array_unique(array_merge(
            $importedProgramPointIds,
            $skippedHotelPointIds,
        )));

        // Puste koszty punktów, których już nie ma w programie — sprzątamy.
        // Zaliczki / wpłaty zostają jako „odłączony od programu”.
        $this->discardStaleCosts(
            sourceType: 'program_point',
            retainSourceIds: $retainProgramPointIds,
            detachedLabel: 'odłączony od programu',
        );
        $this->discardStalePayments(
            sourceType: 'program_point_payment',
            retainSourceIds: $retainProgramPointIds,
        );

        // Import kosztów transportu (autokar)
        $this->importTransportCosts($event, $fallbackPlnCurrencyId);

        // Import kosztów noclegu (hotel)
        $this->importAccommodationCosts($event, $fallbackPlnCurrencyId);

        // Import kosztów ubezpieczeń dziennych
        $this->importInsuranceCosts($event, $fallbackPlnCurrencyId);

        $this->settlement->recalculateTotals();
    }

    /**
     * Importuje koszty ubezpieczeń przypisanych do dni imprezy.
     * Koszt z gratisami (InsuranceCostCalculator) — jak w ofercie.
     */
    private function importInsuranceCosts(Event $event, ?int $fallbackPlnCurrencyId): void
    {
        $payingCount = max(1, (int) ($event->participant_count ?? 1));
        $gratisCount = $event->resolveGratisCountForParticipantCount($payingCount);
        $importedInsuranceDayIds = [];

        foreach ($event->dayInsurances as $dayInsurance) {
            $insurance = $dayInsurance->insurance;
            $amount = InsuranceCostCalculator::dayAssignmentCost(
                $insurance,
                $payingCount,
                $gratisCount
            );

            if ($amount <= 0 || ! $insurance) {
                continue;
            }

            $day = (int) ($dayInsurance->day ?? 0);

            $existing = $this->settlement->costs()
                ->where('source_type', 'insurance_day')
                ->where('source_id', $dayInsurance->id)
                ->first();

            $attributes = [
                'name' => 'Ubezpieczenie Dzień '.$day.': '.($insurance->name ?? ('ID '.$insurance->id)),
                'paid_by' => $existing?->paid_by ?? 'office',
                'advance_type' => $existing?->advance_type ?? 'full',
                'payment_status' => $existing?->payment_status ?? 'planned',
                'order' => 1100 + $day,
            ];

            // Kosztorys ubezpieczenia → Plan tylko przy pierwszym imporcie pozycji.
            if (! $existing) {
                $attributes = array_merge($attributes, [
                    'planned_amount' => $amount,
                    'planned_currency_id' => $fallbackPlnCurrencyId,
                    'planned_rate' => 1,
                    'planned_amount_pln' => $amount,
                ]);
            }

            $this->settlement->costs()->updateOrCreate(
                ['source_type' => 'insurance_day', 'source_id' => $dayInsurance->id],
                $this->mergeExistingCostPilotReporting($existing, $attributes)
            );

            $importedInsuranceDayIds[] = (int) $dayInsurance->id;
        }

        // Usuń nieaktualne puste koszty ubezpieczeń; zaliczki zostają.
        $this->discardStaleCosts(
            sourceType: 'insurance_day',
            retainSourceIds: $importedInsuranceDayIds,
            detachedLabel: 'odłączony od ubezpieczenia',
        );
    }

    /**
     * Importuje koszty transportu z autokarów (grupowanie po przewoźniku).
     */
    private function importTransportCosts(Event $event, ?int $fallbackPlnCurrencyId): void
    {
        app(TransportContractorSettlementSync::class)->syncForEvent($event);
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

                $lines = app(EventHotelPlanService::class)->allocateRoomLines($peopleCount, $roomIds, $groupType);
                if ($lines === []) {
                    continue;
                }

                foreach ($lines as $line) {
                    $qty = max(1, (int) ($line['quantity'] ?? 1));
                    $unitPrice = (float) ($line['unit_price'] ?? 0);
                    $room = HotelRoom::query()->find($line['hotel_room_id'] ?? null);
                    $roomCurrency = (string) ($room?->currency ?? 'PLN');
                    $convertFlag = (bool) ($room?->convert_to_pln ?? ($line['convert_to_pln'] ?? false));

                    $lineTotal = $unitPrice * $qty;
                    if ($roomCurrency === 'PLN' || $convertFlag) {
                        if ($roomCurrency !== 'PLN') {
                            $rate = Currency::where('symbol', $roomCurrency)->first()?->exchange_rate ?? 1;
                            $lineTotal *= $rate;
                        }
                        $dayTotalPln += $lineTotal;
                    } else {
                        // Waluta obca bez konwersji → traktuj jako PLN (brak kursu w settlement)
                        $dayTotalPln += $lineTotal;
                    }
                }
            }

            $totalAccommodationCostPln += $dayTotalPln;
        }

        // Dodaj/zaktualizuj jako jedną zagregowaną pozycję kosztów noclegu
        if ($totalAccommodationCostPln > 0) {
            $existing = $this->settlement->costs()
                ->where('source_type', 'accommodation')
                ->whereNull('source_id')
                ->first();

            $attributes = [
                'name' => 'Koszty noclegu (hotel)',
                'contractor_id' => $existing?->contractor_id ?? $this->resolveAccommodationContractorId($event),
                'planned_amount' => $totalAccommodationCostPln,
                'planned_currency_id' => $fallbackPlnCurrencyId,
                'planned_rate' => 1,
                'planned_amount_pln' => $totalAccommodationCostPln,
                'paid_by' => $existing?->paid_by ?? 'office',
                'advance_type' => 'full',
                'payment_status' => $existing?->payment_status ?? 'planned',
                'order' => 1001,
            ];

            $this->settlement->costs()->updateOrCreate(
                ['source_type' => 'accommodation', 'source_id' => null],
                $this->mergeExistingCostPilotReporting($existing, $attributes)
            );
        }
    }

    private function importAccommodationCostsFromEventPlan(Event $event, ?int $fallbackPlnCurrencyId): void
    {
        app(HotelStaySettlementSync::class)->syncForEvent($event);

        // Zachowaj kompatybilność: gdy sync nie utworzył kosztów (pusty plan), stary import jako fallback.
        $settlement = $this->settlement;
        $hasHotelCosts = $settlement->costs()
            ->whereIn('source_type', [HotelStaySettlementSync::SOURCE_HOTEL, HotelStaySettlementSync::SOURCE_STAY])
            ->exists();

        if ($hasHotelCosts) {
            return;
        }

        $event->loadMissing(['hotelStays.roomLines.currency']);
        $totals = app(\App\Services\EventHotelPlanService::class)->totalsByCurrencyForEvent($event);
        $plnTotal = round((float) ($totals['PLN'] ?? 0), 2);
        $foreignTotals = collect($totals)
            ->reject(fn ($amount, $code) => strtoupper((string) $code) === 'PLN' || (float) $amount <= 0)
            ->map(fn ($amount) => round((float) $amount, 2));

        if ($plnTotal <= 0 && $foreignTotals->isEmpty()) {
            return;
        }

        $existing = $this->settlement->costs()
            ->where('source_type', 'accommodation')
            ->whereNull('source_id')
            ->first();

        // Jedna waluta obca bez PLN → plan w tej walucie (bez przeliczania).
        if ($plnTotal <= 0 && $foreignTotals->count() === 1) {
            $code = strtoupper((string) $foreignTotals->keys()->first());
            $amount = (float) $foreignTotals->first();
            $currency = Currency::query()
                ->where('symbol', $code)
                ->first();

            $attributes = [
                'name' => 'Koszty noclegu (hotel)',
                'contractor_id' => $existing?->contractor_id ?? $this->resolveAccommodationContractorId($event),
                'planned_amount' => $amount,
                'planned_currency_id' => $currency?->id ?? $fallbackPlnCurrencyId,
                'planned_rate' => (float) ($currency?->exchange_rate ?? 1),
                'planned_convert_to_pln' => false,
                'planned_amount_pln' => null,
                'paid_by' => $existing?->paid_by ?? 'office',
                'advance_type' => 'full',
                'payment_status' => $existing?->payment_status ?? 'planned',
                'order' => 1001,
            ];

            $this->settlement->costs()->updateOrCreate(
                ['source_type' => 'accommodation', 'source_id' => null],
                $this->mergeExistingCostPilotReporting($existing, $attributes)
            );

            return;
        }

        // PLN (ew. z przeliczonych walut) — główna pozycja.
        if ($plnTotal > 0) {
            $attributes = [
                'name' => 'Koszty noclegu (hotel)',
                'contractor_id' => $existing?->contractor_id ?? $this->resolveAccommodationContractorId($event),
                'planned_amount' => $plnTotal,
                'planned_currency_id' => $fallbackPlnCurrencyId,
                'planned_rate' => 1,
                'planned_convert_to_pln' => true,
                'planned_amount_pln' => $plnTotal,
                'paid_by' => $existing?->paid_by ?? 'office',
                'advance_type' => 'full',
                'payment_status' => $existing?->payment_status ?? 'planned',
                'order' => 1001,
            ];

            $this->settlement->costs()->updateOrCreate(
                ['source_type' => 'accommodation', 'source_id' => null],
                $this->mergeExistingCostPilotReporting($existing, $attributes)
            );
        }

        // Dodatkowe waluty obce (bez konwersji) — osobne wiersze keyed po currency_id.
        foreach ($foreignTotals as $code => $amount) {
            $currency = Currency::query()
                ->where('symbol', strtoupper((string) $code))
                ->first();
            if (! $currency) {
                continue;
            }

            $foreignExisting = $this->settlement->costs()
                ->where('source_type', 'accommodation')
                ->where('source_id', $currency->id)
                ->first();

            $foreignAttributes = [
                'name' => 'Koszty noclegu (hotel) — '.$currency->symbol,
                'contractor_id' => $foreignExisting?->contractor_id
                    ?? $existing?->contractor_id
                    ?? $this->resolveAccommodationContractorId($event),
                'planned_amount' => $amount,
                'planned_currency_id' => $currency->id,
                'planned_rate' => (float) ($currency->exchange_rate ?? 1),
                'planned_convert_to_pln' => false,
                'planned_amount_pln' => null,
                'paid_by' => $foreignExisting?->paid_by ?? 'office',
                'advance_type' => 'full',
                'payment_status' => $foreignExisting?->payment_status ?? 'planned',
                'order' => 1001 + (int) $currency->id,
            ];

            $this->settlement->costs()->updateOrCreate(
                ['source_type' => 'accommodation', 'source_id' => $currency->id],
                $this->mergeExistingCostPilotReporting($foreignExisting, $foreignAttributes)
            );
        }
    }

    private function resolveTransportContractorId(Event $event): ?int
    {
        $event->loadMissing(['transportProgramPoints']);

        if (filled($event->transport_contractor_id)) {
            return (int) $event->transport_contractor_id;
        }

        if (filled($event->driver_contractor_id)) {
            return (int) $event->driver_contractor_id;
        }

        $transportPoint = $event->transportProgramPoints
            ->first(fn (EventProgramPoint $point): bool => filled($point->contractor_id));

        return $transportPoint && filled($transportPoint->contractor_id)
            ? (int) $transportPoint->contractor_id
            : null;
    }

    private function resolveAccommodationContractorId(Event $event): ?int
    {
        $primaryHotelId = $event->assignedHotelContractorIds()->first();

        return $primaryHotelId ? (int) $primaryHotelId : null;
    }

    private function mergeExistingCostPilotReporting(?EventSettlementCost $existing, array $attributes): array
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
    private function resolveContractorIdForProgramPoint(
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

    private function inferPaidByFromProgramPoint(EventProgramPoint $point): string
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

    private function resolveFallbackPlnCurrencyId(): ?int
    {
        return $this->normalizeCurrencyId(
            Currency::query()
                ->where(function ($q) {
                    $q->where('code', 'PLN')
                        ->orWhere('symbol', 'PLN')
                        ->orWhere('name', 'like', '%złoty%');
                })
                ->orderBy('id')
                ->value('id')
        );
    }

    /**
     * Filament/Livewire i PDO często zwracają FK jako string — normalizujemy przed ?int.
     */
    private function normalizeCurrencyId(mixed $currencyId): ?int
    {
        if ($currencyId === null || $currencyId === '') {
            return null;
        }

        return (int) $currencyId;
    }

    public function upsertCostFromProgramPoint(EventProgramPoint $point): EventSettlementCost
    {
        $point->loadMissing(['event', 'reservations', 'currency', 'templatePoint']);

        $event = $point->event;
        // Plan = planned_price; fallback = kalkulacja z osobami koszowymi (płacący + gratis).
        $plannedAmount = $event
            ? $this->resolveProgramPointPlanAmount($point, $event)
            : round((float) ($point->planned_price ?: $point->resolveEffectiveTotalPrice()), 2);

        $currency = $point->currency;
        $currencyCode = strtoupper((string) ($currency?->code ?? $currency?->symbol ?? 'PLN'));
        $rate = (float) ($currency?->exchange_rate ?? 1);
        $currencyId = $this->normalizeCurrencyId($point->currency_id) ?? $this->resolveFallbackPlnCurrencyId();
        $convertToPln = (bool) ($point->convert_to_pln ?? false);
        $plannedAmountPln = $this->plannedAmountToPln($plannedAmount, $currencyId, $currencyCode, $convertToPln, $rate);

        $activeReservations = $point->reservations->whereNotIn('status', ['cancelled', 'not_required']);
        $contractorId = $this->resolveContractorIdForProgramPoint($point, $activeReservations);
        $hasReservations = $activeReservations->isNotEmpty();
        $reservedAmount = (float) $activeReservations->sum(fn (Reservation $reservation) => (float) ($reservation->reserved_amount ?? 0));
        $reservationExpiry = $this->resolveReservationAdvanceDueDate($activeReservations);

        $existingCost = $this->settlement->costs()
            ->where('source_type', 'program_point')
            ->where('source_id', $point->id)
            ->first();

        $paymentStatus = $existingCost?->payment_status;
        $depositPaidAt = $activeReservations
            ->map(fn (Reservation $reservation) => $reservation->deposit_paid_at)
            ->filter()
            ->sortBy(fn ($value) => $value instanceof Carbon ? $value->timestamp : Carbon::parse($value)->timestamp)
            ->first();

        if ($depositPaidAt) {
            $paymentStatus = 'advance_paid';
        } elseif (in_array($paymentStatus, [null, 'planned', 'reservation_required', 'reserved'], true)) {
            $paymentStatus = $hasReservations ? 'reserved' : 'planned';
        }

        $costPayload = [
            'name' => $point->name ?: ($point->templatePoint->name ?? ('Punkt #'.$point->id)),
            'contractor_id' => $contractorId,
            // Zachowaj płatnika z istniejącego planu (UI Finansów / ChangeSettlementCostPayerAction).
            'paid_by' => $existingCost?->paid_by ?? 'office',
            'advance_type' => $hasReservations ? 'deposit' : ($existingCost?->advance_type ?? 'full'),
            'payment_status' => $paymentStatus,
            'advance_amount' => $reservedAmount > 0 ? $reservedAmount : null,
            'advance_due_date' => $reservationExpiry,
            'order' => $point->order ?? 0,
            'notes' => $point->notes,
        ];

        // Plan: seed przy tworzeniu; przy update sync z jawnego planned_price (nie z fallbacku kalkulacji).
        $explicitPlan = round((float) ($point->planned_price ?? 0), 2);
        if (! $existingCost) {
            $costPayload = array_merge($costPayload, [
                'planned_amount' => $plannedAmount,
                'planned_currency_id' => $currencyId,
                'planned_convert_to_pln' => $convertToPln,
                'planned_rate' => $rate,
                'planned_amount_pln' => $plannedAmountPln,
            ]);
        } elseif ($explicitPlan > 0.009) {
            $costPayload = array_merge($costPayload, [
                'planned_amount' => $explicitPlan,
                'planned_currency_id' => $currencyId,
                'planned_convert_to_pln' => $convertToPln,
                'planned_rate' => $rate,
                'planned_amount_pln' => $this->plannedAmountToPln($explicitPlan, $currencyId, $currencyCode, $convertToPln, $rate),
            ]);
        }

        if ($depositPaidAt) {
            $costPayload['paid_at'] = self::sanitizeTimestampDate($depositPaidAt);
        }

        $cost = $this->settlement->costs()->updateOrCreate(
            [
                'source_type' => 'program_point',
                'source_id' => $point->id,
            ],
            $this->mergeExistingCostPilotReporting($existingCost, $costPayload)
        );

        foreach ($point->reservations as $reservation) {
            if ((int) $reservation->settlement_cost_id !== (int) $cost->id) {
                $reservation->forceFill(['settlement_cost_id' => $cost->id])->saveQuietly();
            }
        }

        $this->settlement->recalculateTotals();

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

    /**
     * Plan imprezy dla punktu: planned_price (ustalenie), a gdy brak — seed z kalkulacji unit×osoby koszowe.
     */
    private function resolveProgramPointPlanAmount(EventProgramPoint $point, Event $event): float
    {
        $planned = round((float) ($point->planned_price ?? 0), 2);
        if ($planned > 0) {
            return $planned;
        }

        $paying = max(1, (int) ($event->participant_count ?? 1));
        $headcount = ProgramPointCostPricing::costHeadcountForPoint($point, $event, $paying);

        return round($point->resolveEffectiveTotalPrice($headcount), 2);
    }

    private function plannedAmountToPln(
        float $plannedAmount,
        ?int $currencyId,
        string $currencyCode,
        bool $convertToPln,
        float $rate,
    ): ?float {
        if ($currencyId && $currencyCode !== 'PLN') {
            return $convertToPln ? round($plannedAmount * $rate, 2) : null;
        }

        return round($plannedAmount, 2);
    }

    /**
     * @param  list<int>  $retainSourceIds
     */
    private function discardStaleCosts(string $sourceType, array $retainSourceIds, string $detachedLabel): void
    {
        $this->settlement->costs()
            ->where('source_type', $sourceType)
            ->when(
                $retainSourceIds !== [],
                fn ($query) => $query->whereNotIn('source_id', $retainSourceIds),
                fn ($query) => $query
            )
            ->get()
            ->each(fn (EventSettlementCost $cost) => $cost->discardIfNotPreserved($detachedLabel));
    }

    /**
     * @param  list<int>  $retainSourceIds
     */
    private function discardStalePayments(string $sourceType, array $retainSourceIds): void
    {
        $this->settlement->costs()
            ->where('source_type', $sourceType)
            ->when(
                $retainSourceIds !== [],
                fn ($query) => $query->whereNotIn('source_id', $retainSourceIds),
                fn ($query) => $query
            )
            ->get()
            ->each(function (EventSettlementCost $payment): void {
                if (! $payment->mustBePreserved()) {
                    $payment->delete();
                }
            });
    }
}
