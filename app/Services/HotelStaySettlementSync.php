<?php

namespace App\Services;

use App\Models\Contractor;
use App\Models\Currency;
use App\Models\Event;
use App\Models\EventHotelStay;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\Reservation;
use App\Support\EventHotelPlanFormatting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Zbiorczy koszt rozliczeniowy hotelu — jedna pozycja planu + wpłaty na kontrahenta (wiele nocy).
 *
 * Sync może odłączyć koszt od aktualnego planu, ale nie kasuje pozycji z zaliczkami / wpłatami.
 */
class HotelStaySettlementSync
{
    public const SOURCE_HOTEL = 'accommodation_hotel';

    public const SOURCE_STAY = 'accommodation_hotel_stay';

    public function syncForEvent(Event $event): void
    {
        if (! Schema::hasTable('event_hotel_stays')) {
            return;
        }

        $event->loadMissing(['hotelStays.contractor', 'hotelStays.roomLines.currency']);
        if ($event->hotelStays->isEmpty()) {
            return;
        }

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        app(HotelStayReservationSync::class)->backfillForEvent($event->fresh(['hotelStays']));

        $groups = $this->groupStays($event->hotelStays);
        $activeKeys = [];

        foreach ($groups as $group) {
            $cost = $this->upsertGroupCost($settlement, $event, $group);
            if ($cost) {
                $activeKeys[] = $this->groupKey($group);
            }
        }

        $this->removeStaleCosts($settlement, $activeKeys);

        // Stare zbiorcze koszty noclegu (cała impreza / per waluta) zastępują koszty per hotel.
        $settlement->costs()
            ->where('source_type', 'accommodation')
            ->delete();

        $settlement->costs()
            ->where('source_type', 'accommodation_payment')
            ->delete();

        $settlement->recalculateTotals();
    }

    public function ensureForContractor(Event $event, int $contractorId): ?EventSettlementCost
    {
        $this->syncForEvent($event);

        return $this->findForContractor($event, $contractorId);
    }

    public function ensureForStay(Event $event, EventHotelStay $stay): ?EventSettlementCost
    {
        $this->syncForEvent($event);

        return $this->findForStay($event, $stay);
    }

    /**
     * Odczyt istniejącego kosztu hotelu (bez sync) — do list / cache UI.
     */
    public function findForContractor(Event $event, int $contractorId): ?EventSettlementCost
    {
        $settlement = $this->activeSettlement($event);
        if (! $settlement) {
            return null;
        }

        return $this->costsCollection($settlement)
            ->first(fn (EventSettlementCost $cost): bool => $cost->source_type === self::SOURCE_HOTEL
                && (int) $cost->source_id === $contractorId);
    }

    /**
     * Odczyt istniejącego kosztu dla nocy (bez sync).
     */
    public function findForStay(Event $event, EventHotelStay $stay): ?EventSettlementCost
    {
        if (filled($stay->contractor_id)) {
            return $this->findForContractor($event, (int) $stay->contractor_id);
        }

        $settlement = $this->activeSettlement($event);
        if (! $settlement) {
            return null;
        }

        return $this->costsCollection($settlement)
            ->first(fn (EventSettlementCost $cost): bool => $cost->source_type === self::SOURCE_STAY
                && (int) $cost->source_id === (int) $stay->id);
    }

    /**
     * Koszt rozliczeniowy dla punktu noclegowego — ten sam co w panelu hotelu / Finansach.
     * null gdy punkt nie jest hotelem albo brak planu noclegów.
     */
    public function findForProgramPoint(Event $event, \App\Models\EventProgramPoint $point): ?EventSettlementCost
    {
        if (! (bool) ($point->is_hotel ?? false)) {
            return null;
        }

        $event->loadMissing(['hotelStays']);
        $point->loadMissing(['hotelStays']);

        $stay = $point->hotelStays->sortBy('day')->first()
            ?? $event->hotelStays->first(
                fn (EventHotelStay $candidate): bool => (int) ($candidate->event_program_point_id ?? 0) === (int) $point->id
            );

        if ($stay instanceof EventHotelStay) {
            return $this->findForStay($event, $stay);
        }

        if (filled($point->contractor_id)) {
            return $this->findForContractor($event, (int) $point->contractor_id);
        }

        return null;
    }

    /**
     * Jak findForProgramPoint, ale po syncu planu hotelowego (drawer „Płat.”).
     */
    public function ensureForProgramPoint(Event $event, \App\Models\EventProgramPoint $point): ?EventSettlementCost
    {
        if (! (bool) ($point->is_hotel ?? false)) {
            return null;
        }

        $event->loadMissing(['hotelStays.roomLines']);
        if ($event->hotelStays->isNotEmpty()) {
            $this->syncForEvent($event);
        }

        return $this->findForProgramPoint($event->fresh(['hotelStays']) ?? $event, $point->fresh(['hotelStays']) ?? $point);
    }

    protected function activeSettlement(Event $event): ?EventSettlement
    {
        if ($event->relationLoaded('activeSettlement')) {
            return $event->activeSettlement;
        }

        return EventSettlement::findActiveForEvent($event);
    }

    /**
     * @return Collection<int, EventSettlementCost>
     */
    protected function costsCollection(EventSettlement $settlement): Collection
    {
        if ($settlement->relationLoaded('costs')) {
            return $settlement->costs;
        }

        return $settlement->costs()->get();
    }

    public function referenceTotalPlnForContractor(Event $event, int $contractorId): float
    {
        $event->loadMissing(['hotelStays.roomLines.currency']);

        $stays = $event->hotelStays->filter(
            fn (EventHotelStay $stay): bool => (int) ($stay->contractor_id ?? 0) === $contractorId
        );

        return $this->referenceTotalPlnForStays($event, $stays);
    }

    public function referenceTotalPlnForStay(Event $event, EventHotelStay $stay): float
    {
        $stay->loadMissing(['roomLines.currency']);

        return $this->referenceTotalPlnForStays($event, collect([$stay]));
    }

    /**
     * @param  Collection<int, EventHotelStay>  $stays
     */
    protected function referenceTotalPlnForStays(Event $event, Collection $stays): float
    {
        if ($stays->isEmpty()) {
            return 0.0;
        }

        $eventMode = $event->hotel_pricing_mode ?? 'lines';

        // Stała kwota za pobyt (impreza) — nie liczymy z cennika pokoi.
        if (EventHotelPlanFormatting::isEventFlatPricing($eventMode)) {
            $event->loadMissing('hotelStays');
            $totalFlat = app(EventHotelPlanService::class)->totalPlnForEvent($event);
            $allCount = $event->hotelStays->count();
            $groupCount = $stays->count();

            if ($totalFlat <= 0 || $allCount <= 0) {
                return 0.0;
            }

            // Jedna kwota na imprezę — rozdzielamy proporcjonalnie do liczby nocy w grupie.
            return round($totalFlat * ($groupCount / $allCount), 2);
        }

        $currencies = Currency::query()->pluck('symbol', 'id');
        $peoplePerNight = (int) app(EventHotelOccupancyService::class)->forEvent($event)['required_beds_per_night'];

        $total = 0.0;
        foreach ($stays as $stay) {
            $total += EventHotelPlanFormatting::stayTotalPln(
                $this->stayPayload($stay),
                $eventMode,
                $currencies,
                $peoplePerNight,
            );
        }

        return round($total, 2);
    }

    /**
     * @param  Collection<int, EventHotelStay>  $stays
     * @return list<array{source_type: string, source_id: int, contractor_id: ?int, stays: Collection<int, EventHotelStay>}>
     */
    protected function groupStays(Collection $stays): array
    {
        $groups = [];

        foreach ($stays->sortBy('day') as $stay) {
            $contractorId = filled($stay->contractor_id) ? (int) $stay->contractor_id : null;

            if ($contractorId) {
                $key = self::SOURCE_HOTEL.':'.$contractorId;
                if (! isset($groups[$key])) {
                    $groups[$key] = [
                        'source_type' => self::SOURCE_HOTEL,
                        'source_id' => $contractorId,
                        'contractor_id' => $contractorId,
                        'stays' => collect(),
                    ];
                }
                $groups[$key]['stays']->push($stay);

                continue;
            }

            $key = self::SOURCE_STAY.':'.$stay->id;
            $groups[$key] = [
                'source_type' => self::SOURCE_STAY,
                'source_id' => (int) $stay->id,
                'contractor_id' => null,
                'stays' => collect([$stay]),
            ];
        }

        return array_values($groups);
    }

    /**
     * @param  array{source_type: string, source_id: int, contractor_id: ?int, stays: Collection<int, EventHotelStay>}  $group
     */
    protected function upsertGroupCost(EventSettlement $settlement, Event $event, array $group): ?EventSettlementCost
    {
        /** @var Collection<int, EventHotelStay> $stays */
        $stays = $group['stays'];
        if ($stays->isEmpty()) {
            return null;
        }

        $existing = $settlement->costs()
            ->where('source_type', $group['source_type'])
            ->where('source_id', $group['source_id'])
            ->first();

        $plnCurrencyId = Currency::defaultPlnId()
            ?? Currency::query()
                ->where('symbol', 'PLN')
                ->orWhere('code', 'PLN')
                ->value('id');

        $plannedAttributes = $this->resolvePlannedAttributesForStays($event, $stays, $plnCurrencyId ? (int) $plnCurrencyId : null);
        $hasPlannedAmount = (float) ($plannedAttributes['planned_amount'] ?? 0) > 0.009
            || (float) ($plannedAttributes['planned_amount_pln'] ?? 0) > 0.009;

        if (! $hasPlannedAmount) {
            // Pusty cennik nie może kasować grupy, na której już są zaliczki / wpłaty.
            return ($existing && $existing->mustBePreserved()) ? $existing : null;
        }

        $contractorId = $group['contractor_id'];
        $contractor = $contractorId ? Contractor::query()->find($contractorId) : null;
        $days = $stays->pluck('day')->map(fn ($day) => 'D'.(int) $day)->implode(', ');
        $hotelLabel = $contractor?->displayLabel() ?? $contractor?->name ?? ('Noc '.$stays->first()->day);

        $reservation = $this->resolveReservationForGroup($event, $group);
        $reservationMeta = $this->reservationMeta($reservation);

        $attributes = [
            'name' => 'Nocleg — '.$hotelLabel.' ('.$days.')',
            'contractor_id' => $contractorId,
            'reservation_id' => $reservation?->id,
            'paid_by' => $existing?->paid_by ?? 'office',
            'advance_type' => $reservation ? 'deposit' : ($existing?->advance_type ?? 'full'),
            'payment_status' => $this->resolveSyncedPaymentStatus($existing, $reservationMeta),
            'advance_amount' => $reservationMeta['advance_amount'] ?? $existing?->advance_amount,
            'advance_due_date' => $reservationMeta['advance_due_date'] ?? $existing?->advance_due_date,
            'order' => 1000 + (int) ($stays->first()->day ?? 0),
            'notes' => $existing?->notes,
        ];

        if (! $existing || $this->shouldRefreshPlannedAmount($existing)) {
            $attributes = array_merge($attributes, $plannedAttributes);
        }

        if ($reservationMeta['paid_at']) {
            $attributes['paid_at'] = $reservationMeta['paid_at'];
        }

        $cost = $settlement->costs()->updateOrCreate(
            [
                'source_type' => $group['source_type'],
                'source_id' => $group['source_id'],
            ],
            $attributes,
        );

        if ($reservation && (int) ($reservation->settlement_cost_id ?? 0) !== (int) $cost->id) {
            $reservation->forceFill(['settlement_cost_id' => (int) $cost->id])->saveQuietly();
        }

        return $cost->fresh();
    }

    protected function shouldRefreshPlannedAmount(EventSettlementCost $existing): bool
    {
        if (in_array((string) $existing->payment_status, ['paid', 'advance_paid', 'partially_paid'], true)) {
            return false;
        }

        return ! $existing->hasPaymentRows();
    }

    /**
     * Snapshot planu rozliczeniowego: przy jednolitej walucie grupy zachowujemy kwotę źródłową
     * (np. 1000 EUR + kurs → planned_amount_pln). Mieszane waluty → suma PLN.
     *
     * @param  Collection<int, EventHotelStay>  $stays
     * @return array{
     *     planned_amount: float,
     *     planned_currency_id: ?int,
     *     planned_rate: float,
     *     planned_convert_to_pln: bool,
     *     planned_amount_pln: ?float
     * }
     */
    protected function resolvePlannedAttributesForStays(Event $event, Collection $stays, ?int $plnCurrencyId): array
    {
        $eventMode = $event->hotel_pricing_mode ?? 'lines';
        $peoplePerNight = (int) app(EventHotelOccupancyService::class)->forEvent($event)['required_beds_per_night'];

        if (EventHotelPlanFormatting::isEventFlatPricing($eventMode)) {
            return $this->resolveEventFlatPlannedAttributes($event, $stays, $peoplePerNight, $plnCurrencyId);
        }

        $uniformCurrencyId = null;
        $uniformConvert = null;
        $nativeSum = 0.0;
        $hasComponent = false;

        foreach ($stays as $stay) {
            $payload = $this->stayPayload($stay);
            $stayMode = $payload['pricing_mode'] ?? 'lines';

            if (EventHotelPlanFormatting::isStayFlatPricing($stayMode)
                && isset($payload['flat_amount'])
                && $payload['flat_amount'] !== ''
                && $payload['flat_amount'] !== null
            ) {
                $amount = EventHotelPlanFormatting::resolveFlatNativeAmount(
                    (float) $payload['flat_amount'],
                    $stayMode,
                    $peoplePerNight,
                );
                if ($amount <= 0.009) {
                    continue;
                }

                $componentCurrencyId = $this->normalizeCurrencyId(
                    isset($payload['flat_currency_id']) ? (int) $payload['flat_currency_id'] : null,
                    $plnCurrencyId,
                );
                $componentConvert = $this->isPlnCurrencyId($componentCurrencyId, $plnCurrencyId)
                    ? true
                    : (bool) ($payload['flat_convert_to_pln'] ?? true);

                if (! $this->acceptUniformComponent(
                    $hasComponent,
                    $uniformCurrencyId,
                    $uniformConvert,
                    $componentCurrencyId,
                    $componentConvert,
                )) {
                    return $this->plnFallbackPlannedAttributes($event, $stays, $plnCurrencyId);
                }

                $nativeSum = round($nativeSum + $amount, 2);
                $hasComponent = true;

                continue;
            }

            foreach ($payload['room_lines'] ?? [] as $line) {
                $amount = EventHotelPlanFormatting::lineNativeTotal($line);
                if ($amount <= 0.009) {
                    continue;
                }

                $componentCurrencyId = $this->normalizeCurrencyId(
                    isset($line['currency_id']) ? (int) $line['currency_id'] : null,
                    $plnCurrencyId,
                );
                $componentConvert = $this->isPlnCurrencyId($componentCurrencyId, $plnCurrencyId)
                    ? true
                    : (bool) ($line['convert_to_pln'] ?? true);

                if (! $this->acceptUniformComponent(
                    $hasComponent,
                    $uniformCurrencyId,
                    $uniformConvert,
                    $componentCurrencyId,
                    $componentConvert,
                )) {
                    return $this->plnFallbackPlannedAttributes($event, $stays, $plnCurrencyId);
                }

                $nativeSum = round($nativeSum + $amount, 2);
                $hasComponent = true;
            }
        }

        if (! $hasComponent) {
            return $this->plnFallbackPlannedAttributes($event, $stays, $plnCurrencyId);
        }

        return $this->buildPlannedAttributes(
            $nativeSum,
            $uniformCurrencyId,
            (bool) $uniformConvert,
            $plnCurrencyId,
        );
    }

    /**
     * @param  Collection<int, EventHotelStay>  $stays
     * @return array{
     *     planned_amount: float,
     *     planned_currency_id: ?int,
     *     planned_rate: float,
     *     planned_convert_to_pln: bool,
     *     planned_amount_pln: ?float
     * }
     */
    protected function resolveEventFlatPlannedAttributes(
        Event $event,
        Collection $stays,
        int $peoplePerNight,
        ?int $plnCurrencyId,
    ): array {
        $event->loadMissing('hotelStays');
        $allCount = $event->hotelStays->count();
        $groupCount = $stays->count();
        $share = ($allCount > 0 && $groupCount > 0) ? ($groupCount / $allCount) : 0.0;

        $flatAmount = $event->hotel_flat_stay_amount !== null
            ? (float) $event->hotel_flat_stay_amount
            : 0.0;
        $nativeTotal = EventHotelPlanFormatting::resolveFlatNativeAmount(
            $flatAmount,
            $event->hotel_pricing_mode ?? 'lines',
            $peoplePerNight,
        );
        $nativeShare = round($nativeTotal * $share, 2);

        $currencyId = $this->normalizeCurrencyId(
            $event->hotel_flat_stay_currency_id ? (int) $event->hotel_flat_stay_currency_id : null,
            $plnCurrencyId,
        );
        $convert = $this->isPlnCurrencyId($currencyId, $plnCurrencyId)
            ? true
            : (bool) ($event->hotel_flat_stay_convert_to_pln ?? true);

        return $this->buildPlannedAttributes($nativeShare, $currencyId, $convert, $plnCurrencyId);
    }

    /**
     * @param  Collection<int, EventHotelStay>  $stays
     * @return array{
     *     planned_amount: float,
     *     planned_currency_id: ?int,
     *     planned_rate: float,
     *     planned_convert_to_pln: bool,
     *     planned_amount_pln: ?float
     * }
     */
    protected function plnFallbackPlannedAttributes(Event $event, Collection $stays, ?int $plnCurrencyId): array
    {
        $referencePln = $this->referenceTotalPlnForStays($event, $stays);

        return [
            'planned_amount' => $referencePln,
            'planned_currency_id' => $plnCurrencyId,
            'planned_rate' => 1.0,
            'planned_convert_to_pln' => true,
            'planned_amount_pln' => $referencePln,
        ];
    }

    /**
     * @return array{
     *     planned_amount: float,
     *     planned_currency_id: ?int,
     *     planned_rate: float,
     *     planned_convert_to_pln: bool,
     *     planned_amount_pln: ?float
     * }
     */
    protected function buildPlannedAttributes(
        float $nativeAmount,
        ?int $currencyId,
        bool $convertToPln,
        ?int $plnCurrencyId,
    ): array {
        if ($nativeAmount <= 0.009) {
            return [
                'planned_amount' => 0.0,
                'planned_currency_id' => $plnCurrencyId,
                'planned_rate' => 1.0,
                'planned_convert_to_pln' => true,
                'planned_amount_pln' => 0.0,
            ];
        }

        if ($this->isPlnCurrencyId($currencyId, $plnCurrencyId)) {
            return [
                'planned_amount' => $nativeAmount,
                'planned_currency_id' => $plnCurrencyId ?? $currencyId,
                'planned_rate' => 1.0,
                'planned_convert_to_pln' => true,
                'planned_amount_pln' => $nativeAmount,
            ];
        }

        $currency = $currencyId ? Currency::query()->find($currencyId) : null;
        $rate = (float) ($currency?->exchange_rate ?? 1);
        if ($rate <= 0) {
            $rate = 1.0;
        }

        return [
            'planned_amount' => $nativeAmount,
            'planned_currency_id' => $currencyId,
            'planned_rate' => $rate,
            'planned_convert_to_pln' => $convertToPln,
            'planned_amount_pln' => $convertToPln ? round($nativeAmount * $rate, 2) : null,
        ];
    }

    protected function normalizeCurrencyId(?int $currencyId, ?int $plnCurrencyId): ?int
    {
        if ($currencyId === null || $currencyId <= 0) {
            return $plnCurrencyId;
        }

        if ($this->isPlnCurrencyId($currencyId, $plnCurrencyId)) {
            return $plnCurrencyId ?? $currencyId;
        }

        return $currencyId;
    }

    protected function isPlnCurrencyId(?int $currencyId, ?int $plnCurrencyId): bool
    {
        if ($currencyId === null || $currencyId <= 0) {
            return true;
        }

        if ($plnCurrencyId !== null && $currencyId === $plnCurrencyId) {
            return true;
        }

        return in_array($currencyId, Currency::plnIds(), true);
    }

    /**
     * @param-out ?int $uniformCurrencyId
     * @param-out ?bool $uniformConvert
     */
    protected function acceptUniformComponent(
        bool $hasComponent,
        ?int &$uniformCurrencyId,
        ?bool &$uniformConvert,
        ?int $componentCurrencyId,
        bool $componentConvert,
    ): bool {
        if (! $hasComponent) {
            $uniformCurrencyId = $componentCurrencyId;
            $uniformConvert = $componentConvert;

            return true;
        }

        return $uniformCurrencyId === $componentCurrencyId
            && $uniformConvert === $componentConvert;
    }

    /**
     * @param  array{payment_status: string, advance_amount: ?float, advance_due_date: mixed, paid_at: mixed}  $reservationMeta
     */
    protected function resolveSyncedPaymentStatus(?EventSettlementCost $existing, array $reservationMeta): string
    {
        $fromReservation = (string) ($reservationMeta['payment_status'] ?? 'planned');
        $existingStatus = (string) ($existing?->payment_status ?? '');
        $booked = ['paid', 'advance_paid', 'partially_paid'];

        if ($existingStatus !== '' && in_array($existingStatus, $booked, true) && ! in_array($fromReservation, $booked, true)) {
            return $existingStatus;
        }

        return $fromReservation !== '' ? $fromReservation : ($existingStatus !== '' ? $existingStatus : 'planned');
    }

    /**
     * @param  array{source_type: string, source_id: int, contractor_id: ?int, stays: Collection<int, EventHotelStay>}  $group
     */
    protected function resolveReservationForGroup(Event $event, array $group): ?Reservation
    {
        $reservationSync = app(HotelStayReservationSync::class);

        if ($group['source_type'] === self::SOURCE_HOTEL && $group['contractor_id']) {
            $stay = $group['stays']->sortBy('day')->first();

            return $stay
                ? ($reservationSync->findForStay($stay) ?? $reservationSync->ensureForStay($stay))
                : null;
        }

        $stay = $group['stays']->first();

        return $stay ? $reservationSync->findForStay($stay) : null;
    }

    /**
     * @return array{payment_status: string, advance_amount: ?float, advance_due_date: mixed, paid_at: mixed}
     */
    protected function reservationMeta(?Reservation $reservation): array
    {
        if (! $reservation) {
            return [
                'payment_status' => 'planned',
                'advance_amount' => null,
                'advance_due_date' => null,
                'paid_at' => null,
            ];
        }

        $reservedAmount = (float) ($reservation->reserved_amount ?? 0);
        $paymentStatus = in_array($reservation->status, ['confirmed', 'partially_confirmed', 'completed'], true)
            ? 'reserved'
            : 'planned';

        if ($reservation->deposit_paid_at) {
            $paymentStatus = 'advance_paid';
        }

        return [
            'payment_status' => $paymentStatus,
            'advance_amount' => $reservedAmount > 0 ? $reservedAmount : null,
            'advance_due_date' => $reservation->deposit_due_at ?? $reservation->confirm_by,
            'paid_at' => $reservation->deposit_paid_at,
        ];
    }

    /**
     * @param  list<string>  $activeKeys
     */
    protected function removeStaleCosts(EventSettlement $settlement, array $activeKeys): void
    {
        $costs = $settlement->costs()
            ->whereIn('source_type', [self::SOURCE_HOTEL, self::SOURCE_STAY])
            ->get();

        foreach ($costs as $cost) {
            $key = $cost->source_type.':'.(int) $cost->source_id;
            if (in_array($key, $activeKeys, true)) {
                continue;
            }

            $cost->discardIfNotPreserved('odłączony od planu');
        }
    }

    /**
     * @param  array{source_type: string, source_id: int}  $group
     */
    protected function groupKey(array $group): string
    {
        return $group['source_type'].':'.(int) $group['source_id'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function stayPayload(EventHotelStay $stay): array
    {
        return [
            'pricing_mode' => $stay->pricing_mode ?? 'lines',
            'flat_amount' => $stay->flat_amount,
            'flat_currency_id' => $stay->flat_currency_id,
            'flat_convert_to_pln' => $stay->flat_convert_to_pln,
            'room_lines' => $stay->roomLines->map(static fn ($line): array => [
                'hotel_room_id' => $line->hotel_room_id,
                'label' => $line->label,
                'quantity' => $line->quantity,
                'people_count' => $line->people_count,
                'unit_price' => $line->unit_price,
                'price_basis' => $line->resolvedPriceBasis(),
                'currency_id' => $line->currency_id,
                'convert_to_pln' => $line->convert_to_pln,
            ])->all(),
        ];
    }
}
