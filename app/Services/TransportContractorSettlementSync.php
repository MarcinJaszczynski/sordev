<?php

namespace App\Services;

use App\Models\Contractor;
use App\Models\Currency;
use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;

/**
 * Zbiorczy koszt transportu — jedna pozycja planu + wpłaty na przewoźnika.
 * Legacy `transport` / source_id=null migrujemy do `transport_contractor`.
 */
class TransportContractorSettlementSync
{
    public const SOURCE_CONTRACTOR = 'transport_contractor';

    public const SOURCE_LEGACY = 'transport';

    public function syncForEvent(Event $event): void
    {
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $this->migrateLegacyAggregate($settlement, $event);

        $calculator = new EventTransportCostCalculator($event);
        $hasTransportCost = $calculator->usesManualTransportCost() || (bool) $event->bus;

        if (! $hasTransportCost) {
            $this->removeStaleCosts($settlement, []);

            return;
        }

        $referencePln = round($calculator->effectiveTransportCost(), 2);
        if ($referencePln <= 0) {
            $this->removeStaleCosts($settlement, []);

            return;
        }

        $groups = $this->resolveContractorGroups($event);
        $primaryId = $this->primaryContractorId($event);
        $activeKeys = [];

        if ($groups === []) {
            $cost = $this->upsertUnassignedCost($settlement, $event, $referencePln, $calculator);
            if ($cost) {
                $activeKeys[] = self::SOURCE_LEGACY.':0';
            }
        } else {
            foreach ($groups as $contractorId) {
                $isPrimary = $primaryId !== null
                    ? $contractorId === $primaryId
                    : $contractorId === $groups[0];

                $cost = $this->upsertContractorCost(
                    $settlement,
                    $event,
                    $contractorId,
                    $isPrimary ? $referencePln : 0.0,
                    $calculator,
                    $isPrimary,
                );

                if ($cost) {
                    $activeKeys[] = self::SOURCE_CONTRACTOR.':'.$contractorId;
                }
            }
        }

        $this->removeStaleCosts($settlement, $activeKeys);
        $settlement->recalculateTotals();
    }

    public function ensureForContractor(Event $event, int $contractorId): ?EventSettlementCost
    {
        $this->syncForEvent($event);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        return $settlement->costs()
            ->where('source_type', self::SOURCE_CONTRACTOR)
            ->where('source_id', $contractorId)
            ->first();
    }

    public function ensurePrimaryCost(Event $event): ?EventSettlementCost
    {
        $this->syncForEvent($event);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $primaryId = $this->primaryContractorId($event);

        if ($primaryId) {
            $cost = $settlement->costs()
                ->where('source_type', self::SOURCE_CONTRACTOR)
                ->where('source_id', $primaryId)
                ->first();

            if ($cost) {
                return $cost;
            }
        }

        $grouped = $settlement->costs()
            ->where('source_type', self::SOURCE_CONTRACTOR)
            ->orderBy('id')
            ->first();

        if ($grouped) {
            return $grouped;
        }

        return $settlement->costs()
            ->where('source_type', self::SOURCE_LEGACY)
            ->whereNull('source_id')
            ->first();
    }

    public function isPrimaryContractor(Event $event, int $contractorId): bool
    {
        $primary = $this->primaryContractorId($event);

        if ($primary !== null) {
            return $primary === $contractorId;
        }

        $groups = $this->resolveContractorGroups($event);

        return $groups !== [] && $groups[0] === $contractorId;
    }

    public function primaryContractorId(Event $event): ?int
    {
        $event->loadMissing(['transportContractor', 'driverContractor', 'transportProgramPoints', 'eventVehicles.vehicle']);

        if (filled($event->transport_contractor_id)) {
            return (int) $event->transport_contractor_id;
        }

        $fromFleet = $event->eventVehicles
            ->map(fn ($row) => $row->vehicle?->contractor_id)
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($fromFleet->count() === 1) {
            return (int) $fromFleet->first();
        }

        if (filled($event->driver_contractor_id)) {
            return (int) $event->driver_contractor_id;
        }

        $transportPoint = $event->transportProgramPoints
            ->first(fn ($point): bool => filled($point->contractor_id));

        return $transportPoint && filled($transportPoint->contractor_id)
            ? (int) $transportPoint->contractor_id
            : null;
    }

    /**
     * @return list<array{
     *     key: string,
     *     contractor_id: ?int,
     *     contractor_name: string,
     *     is_primary: bool,
     *     cost: ?EventSettlementCost,
     *     reservation: ?Reservation
     * }>
     */
    public function financeGroups(Event $event): array
    {
        $this->syncForEvent($event);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $primaryId = $this->primaryContractorId($event);
        $groups = [];

        $contractorCosts = $settlement->costs()
            ->where('source_type', self::SOURCE_CONTRACTOR)
            ->with(['contractor', 'reservation'])
            ->orderBy('id')
            ->get();

        foreach ($contractorCosts as $cost) {
            $contractorId = (int) ($cost->source_id ?? 0);
            if ($contractorId <= 0) {
                continue;
            }

            $groups[] = [
                'key' => self::SOURCE_CONTRACTOR.':'.$contractorId,
                'contractor_id' => $contractorId,
                'contractor_name' => $cost->contractor?->displayLabel()
                    ?? $cost->contractor?->name
                    ?? ('Przewoźnik #'.$contractorId),
                'is_primary' => $primaryId !== null
                    ? $contractorId === $primaryId
                    : $contractorCosts->first()?->is($cost) === true,
                'cost' => $cost,
                'reservation' => $cost->reservation
                    ?? ($cost->reservation_id ? Reservation::query()->find((int) $cost->reservation_id) : null),
            ];
        }

        $legacy = $settlement->costs()
            ->where('source_type', self::SOURCE_LEGACY)
            ->whereNull('source_id')
            ->with(['contractor', 'reservation'])
            ->first();

        if ($legacy) {
            $groups[] = [
                'key' => self::SOURCE_LEGACY.':0',
                'contractor_id' => null,
                'contractor_name' => $legacy->contractor?->displayLabel()
                    ?? $legacy->contractor?->name
                    ?? 'Przewoźnik nie wybrany',
                'is_primary' => true,
                'cost' => $legacy,
                'reservation' => $legacy->reservation
                    ?? ($legacy->reservation_id ? Reservation::query()->find((int) $legacy->reservation_id) : null),
            ];
        }

        return $groups;
    }

    /**
     * @return list<int>
     */
    protected function resolveContractorGroups(Event $event): array
    {
        $event->loadMissing(['eventVehicles.vehicle', 'transportProgramPoints']);

        $ids = collect();

        if (filled($event->transport_contractor_id)) {
            $ids->push((int) $event->transport_contractor_id);
        }

        foreach ($event->eventVehicles as $assignment) {
            $contractorId = $assignment->vehicle?->contractor_id;
            if (filled($contractorId)) {
                $ids->push((int) $contractorId);
            }
        }

        foreach ($event->transportProgramPoints as $point) {
            if (filled($point->contractor_id)) {
                $ids->push((int) $point->contractor_id);
            }
        }

        return $ids->unique()->values()->all();
    }

    protected function upsertContractorCost(
        EventSettlement $settlement,
        Event $event,
        int $contractorId,
        float $referencePln,
        EventTransportCostCalculator $calculator,
        bool $isPrimary,
    ): ?EventSettlementCost {
        $contractor = Contractor::query()->find($contractorId);
        $label = $contractor?->displayLabel() ?? $contractor?->name ?? ('#'.$contractorId);

        $existing = $settlement->costs()
            ->where('source_type', self::SOURCE_CONTRACTOR)
            ->where('source_id', $contractorId)
            ->first();

        $plnCurrencyId = Currency::query()
            ->where('symbol', 'PLN')
            ->orWhere('code', 'PLN')
            ->value('id');

        [$plannedAmount, $currencyId, $rate, $plannedPln] = $this->resolvePlannedAmounts(
            $event,
            $calculator,
            $referencePln,
            $plnCurrencyId,
            $isPrimary,
        );

        $reservation = $this->resolveReservationForCost($existing, $contractorId, $event);

        $attributes = [
            'name' => $isPrimary
                ? ($calculator->usesManualTransportCost()
                    ? EventTransportCostCalculator::MANUAL_TRANSPORT_POINT_NAME.' — '.$label
                    : EventTransportCostCalculator::TRANSPORT_POINT_NAME.' — '.$label)
                : 'Transport (dodatkowy) — '.$label,
            'contractor_id' => $contractorId,
            'reservation_id' => $reservation?->id ?? $existing?->reservation_id,
            'paid_by' => $existing?->paid_by ?? 'office',
            'advance_type' => $existing?->advance_type ?? 'full',
            'payment_status' => $existing?->payment_status ?? 'planned',
            'order' => $isPrimary ? 1000 : 1001,
            'notes' => $existing?->notes,
        ];

        if (! $existing) {
            $attributes = array_merge($attributes, [
                'planned_amount' => $plannedAmount,
                'planned_currency_id' => $currencyId,
                'planned_rate' => $rate,
                'planned_convert_to_pln' => true,
                'planned_amount_pln' => $plannedPln,
            ]);
        } elseif ($isPrimary && $this->shouldRefreshPlannedAmount($existing)) {
            $attributes = array_merge($attributes, [
                'planned_amount' => $plannedAmount,
                'planned_currency_id' => $currencyId,
                'planned_rate' => $rate,
                'planned_convert_to_pln' => true,
                'planned_amount_pln' => $plannedPln,
            ]);
        }

        $cost = $settlement->costs()->updateOrCreate(
            [
                'source_type' => self::SOURCE_CONTRACTOR,
                'source_id' => $contractorId,
            ],
            $attributes,
        );

        if ($reservation && (int) ($reservation->settlement_cost_id ?? 0) !== (int) $cost->id) {
            $reservation->forceFill(['settlement_cost_id' => (int) $cost->id])->saveQuietly();
        }

        return $cost->fresh();
    }

    protected function upsertUnassignedCost(
        EventSettlement $settlement,
        Event $event,
        float $referencePln,
        EventTransportCostCalculator $calculator,
    ): ?EventSettlementCost {
        $existing = $settlement->costs()
            ->where('source_type', self::SOURCE_LEGACY)
            ->whereNull('source_id')
            ->first();

        $plnCurrencyId = Currency::query()
            ->where('symbol', 'PLN')
            ->orWhere('code', 'PLN')
            ->value('id');

        [$plannedAmount, $currencyId, $rate, $plannedPln] = $this->resolvePlannedAmounts(
            $event,
            $calculator,
            $referencePln,
            $plnCurrencyId,
            true,
        );

        $attributes = [
            'name' => $calculator->usesManualTransportCost()
                ? EventTransportCostCalculator::MANUAL_TRANSPORT_POINT_NAME
                : EventTransportCostCalculator::TRANSPORT_POINT_NAME,
            'contractor_id' => $existing?->contractor_id,
            'paid_by' => $existing?->paid_by ?? 'office',
            'advance_type' => $existing?->advance_type ?? 'full',
            'payment_status' => $existing?->payment_status ?? 'planned',
            'order' => 1000,
            'notes' => $existing?->notes,
        ];

        if (! $existing) {
            $attributes = array_merge($attributes, [
                'planned_amount' => $plannedAmount,
                'planned_currency_id' => $currencyId,
                'planned_rate' => $rate,
                'planned_convert_to_pln' => true,
                'planned_amount_pln' => $plannedPln,
            ]);
        } elseif ($this->shouldRefreshPlannedAmount($existing)) {
            $attributes = array_merge($attributes, [
                'planned_amount' => $plannedAmount,
                'planned_currency_id' => $currencyId,
                'planned_rate' => $rate,
                'planned_convert_to_pln' => true,
                'planned_amount_pln' => $plannedPln,
            ]);
        }

        return $settlement->costs()->updateOrCreate(
            [
                'source_type' => self::SOURCE_LEGACY,
                'source_id' => null,
            ],
            $attributes,
        )->fresh();
    }

    /**
     * @return array{0: float, 1: ?int, 2: float, 3: float}
     */
    protected function resolvePlannedAmounts(
        Event $event,
        EventTransportCostCalculator $calculator,
        float $referencePln,
        ?int $plnCurrencyId,
        bool $isPrimary,
    ): array {
        if (! $isPrimary || $referencePln <= 0) {
            return [0.0, $plnCurrencyId, 1.0, 0.0];
        }

        $manual = $calculator->usesManualTransportCost();
        $currencyId = $plnCurrencyId;
        $rate = 1.0;
        $plannedAmount = $referencePln;

        if (! $manual && $event->bus) {
            $busCurrency = $event->bus->currency ?? 'PLN';
            if ($busCurrency !== 'PLN') {
                $currency = Currency::query()->where('symbol', $busCurrency)->first();
                if ($currency) {
                    $currencyId = $currency->id;
                    $rate = (float) ($currency->exchange_rate ?? 1);
                    $plannedAmount = $rate > 0 ? round($referencePln / $rate, 2) : $referencePln;
                }
            }
        }

        return [$plannedAmount, $currencyId, $rate, $referencePln];
    }

    protected function shouldRefreshPlannedAmount(EventSettlementCost $existing): bool
    {
        if ($existing->payment_status === 'paid') {
            return false;
        }

        $paymentType = $existing->source_type.'_payment';

        return ! EventSettlementCost::query()
            ->where('settlement_id', $existing->settlement_id)
            ->where('source_type', $paymentType)
            ->when(
                $existing->source_id !== null,
                fn ($q) => $q->where('source_id', $existing->source_id),
                fn ($q) => $q->whereNull('source_id'),
            )
            ->where('payment_status', '!=', 'cancelled')
            ->exists();
    }

    protected function resolveReservationForCost(
        ?EventSettlementCost $existing,
        int $contractorId,
        Event $event,
    ): ?Reservation {
        if ($existing?->reservation_id) {
            return Reservation::query()->find((int) $existing->reservation_id);
        }

        return Reservation::query()
            ->where('event_id', $event->id)
            ->where('contractor_id', $contractorId)
            ->whereNull('program_point_id')
            ->latest('id')
            ->first();
    }

    /**
     * @param  list<string>  $activeKeys
     */
    protected function removeStaleCosts(EventSettlement $settlement, array $activeKeys): void
    {
        $contractorIds = collect($activeKeys)
            ->filter(fn (string $key): bool => str_starts_with($key, self::SOURCE_CONTRACTOR.':'))
            ->map(fn (string $key): int => (int) substr($key, strlen(self::SOURCE_CONTRACTOR) + 1))
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        $keepLegacy = in_array(self::SOURCE_LEGACY.':0', $activeKeys, true);

        $staleContractors = $settlement->costs()
            ->where('source_type', self::SOURCE_CONTRACTOR)
            ->when(
                $contractorIds !== [],
                fn ($q) => $q->whereNotIn('source_id', $contractorIds),
                fn ($q) => $q,
            )
            ->get();

        foreach ($staleContractors as $cost) {
            $this->deletePlanWithPayments($cost);
        }

        if (! $keepLegacy) {
            $legacyPlans = $settlement->costs()
                ->where('source_type', self::SOURCE_LEGACY)
                ->whereNull('source_id')
                ->get();

            foreach ($legacyPlans as $cost) {
                // Nie kasuj legacy, gdy brak grup kontrahenta i sync zostawił pustą listę z powodu braku kosztu —
                // wtedy activeKeys jest puste i legacy też powinno zniknąć.
                $this->deletePlanWithPayments($cost);
            }
        }
    }

    protected function deletePlanWithPayments(EventSettlementCost $plan): void
    {
        $plan->discardIfNotPreserved('odłączony od transportu');
    }

    protected function migrateLegacyAggregate(EventSettlement $settlement, Event $event): void
    {
        $legacy = $settlement->costs()
            ->where('source_type', self::SOURCE_LEGACY)
            ->whereNull('source_id')
            ->first();

        if (! $legacy) {
            return;
        }

        $contractorId = filled($legacy->contractor_id)
            ? (int) $legacy->contractor_id
            : $this->primaryContractorId($event);

        if (! $contractorId) {
            return;
        }

        DB::transaction(function () use ($settlement, $legacy, $contractorId): void {
            $existingGrouped = $settlement->costs()
                ->where('source_type', self::SOURCE_CONTRACTOR)
                ->where('source_id', $contractorId)
                ->first();

            if ($existingGrouped && (int) $existingGrouped->id !== (int) $legacy->id) {
                // Scal płatności legacy → istniejąca grupa, potem usuń legacy plan.
                EventSettlementCost::query()
                    ->where('settlement_id', $settlement->id)
                    ->where('source_type', 'transport_payment')
                    ->whereNull('source_id')
                    ->update([
                        'source_type' => self::SOURCE_CONTRACTOR.'_payment',
                        'source_id' => $contractorId,
                    ]);

                $legacy->delete();

                return;
            }

            $legacy->forceFill([
                'source_type' => self::SOURCE_CONTRACTOR,
                'source_id' => $contractorId,
                'contractor_id' => $contractorId,
            ])->saveQuietly();

            EventSettlementCost::query()
                ->where('settlement_id', $settlement->id)
                ->where('source_type', 'transport_payment')
                ->whereNull('source_id')
                ->update([
                    'source_type' => self::SOURCE_CONTRACTOR.'_payment',
                    'source_id' => $contractorId,
                ]);
        });
    }
}
