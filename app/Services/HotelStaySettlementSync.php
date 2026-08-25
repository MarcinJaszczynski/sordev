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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Zbiorczy koszt rozliczeniowy hotelu — jedna pozycja planu + wpłaty na kontrahenta (wiele nocy).
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

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        return $settlement->costs()
            ->where('source_type', self::SOURCE_HOTEL)
            ->where('source_id', $contractorId)
            ->first();
    }

    public function ensureForStay(Event $event, EventHotelStay $stay): ?EventSettlementCost
    {
        $this->syncForEvent($event);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        if (filled($stay->contractor_id)) {
            return $settlement->costs()
                ->where('source_type', self::SOURCE_HOTEL)
                ->where('source_id', (int) $stay->contractor_id)
                ->first();
        }

        return $settlement->costs()
            ->where('source_type', self::SOURCE_STAY)
            ->where('source_id', (int) $stay->id)
            ->first();
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

        $referencePln = $this->referenceTotalPlnForStays($event, $stays);
        if ($referencePln <= 0) {
            return null;
        }

        $contractorId = $group['contractor_id'];
        $contractor = $contractorId ? Contractor::query()->find($contractorId) : null;
        $days = $stays->pluck('day')->map(fn ($day) => 'D'.(int) $day)->implode(', ');
        $hotelLabel = $contractor?->displayLabel() ?? $contractor?->name ?? ('Noc '.$stays->first()->day);

        $existing = $settlement->costs()
            ->where('source_type', $group['source_type'])
            ->where('source_id', $group['source_id'])
            ->first();

        $reservation = $this->resolveReservationForGroup($event, $group);
        $reservationMeta = $this->reservationMeta($reservation);

        $plnCurrencyId = Currency::query()
            ->where('symbol', 'PLN')
            ->orWhere('code', 'PLN')
            ->value('id');

        $attributes = [
            'name' => 'Nocleg — '.$hotelLabel.' ('.$days.')',
            'contractor_id' => $contractorId,
            'reservation_id' => $reservation?->id,
            'paid_by' => $existing?->paid_by ?? 'office',
            'advance_type' => $reservation ? 'deposit' : ($existing?->advance_type ?? 'full'),
            'payment_status' => $reservationMeta['payment_status'] ?? ($existing?->payment_status ?? 'planned'),
            'advance_amount' => $reservationMeta['advance_amount'],
            'advance_due_date' => $reservationMeta['advance_due_date'],
            'order' => 1000 + (int) ($stays->first()->day ?? 0),
            'notes' => $existing?->notes,
        ];

        if (! $existing) {
            $attributes = array_merge($attributes, [
                'planned_amount' => $referencePln,
                'planned_currency_id' => $plnCurrencyId,
                'planned_rate' => 1,
                'planned_convert_to_pln' => true,
                'planned_amount_pln' => $referencePln,
            ]);
        } elseif ($this->shouldRefreshPlannedAmount($existing)) {
            $attributes = array_merge($attributes, [
                'planned_amount' => $referencePln,
                'planned_currency_id' => $plnCurrencyId,
                'planned_rate' => 1,
                'planned_convert_to_pln' => true,
                'planned_amount_pln' => $referencePln,
            ]);
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
        if ($existing->payment_status === 'paid') {
            return false;
        }

        $hasPayments = EventSettlementCost::query()
            ->where('settlement_id', $existing->settlement_id)
            ->where('source_type', $existing->source_type.'_payment')
            ->where('source_id', $existing->source_id)
            ->where('payment_status', '!=', 'cancelled')
            ->exists();

        return ! $hasPayments;
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

            DB::transaction(function () use ($settlement, $cost): void {
                $settlement->costs()
                    ->where('source_type', $cost->source_type.'_payment')
                    ->where('source_id', $cost->source_id)
                    ->delete();

                $cost->delete();
            });
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
