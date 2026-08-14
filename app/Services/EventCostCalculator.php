<?php

namespace App\Services;

use App\Models\Bus;
use App\Models\Event;
use App\Models\EventProgramPoint;

/**
 * Jeden autorytatywny kalkulator kosztów imprezy.
 *
 * Zasady (uzgodnione z biznesem):
 *  - każdy koszt liczony DOKŁADNIE RAZ,
 *  - nocleg pochodzi z planu hotelowego (gdy istnieje); punkty programu typu nocleg
 *    są wtedy pomijane (żeby uniknąć podwójnego liczenia),
 *  - transport liczony raz przez EventTransportCostCalculator (nie z punktów programu),
 *  - ubezpieczenie liczone raz (InsuranceCostCalculator / Event::insuranceCostPln — z gratisami),
 *  - baza → marża → podatki → SUMA KOŃCOWA,
 *  - punkty programu: domyślnie od płacących; opiekunowie/gratisy tylko gdy
 *    include_gratis_in_cost na punkcie,
 *  - cena za osobę = SUMA KOŃCOWA ÷ liczba osób PŁACĄCYCH (bez gratisów/obsługi/kierowcy).
 */
class EventCostCalculator
{
    /** @var array<string, array<string, mixed>> */
    private static array $requestCache = [];

    public function __construct(private readonly Event $event) {}

    public static function for(Event $event): self
    {
        return new self($event);
    }

    /**
     * Czyści cache w obrębie requestu (testy / po zapisie atrybutów).
     */
    public static function clearRequestCache(): void
    {
        self::$requestCache = [];
    }

    /**
     * @return array<string, mixed>
     */
    public function calculate(
        ?int $participantCount = null,
        ?int $gratisOverride = null,
        ?int $staffOverride = null,
        ?int $driverOverride = null,
    ): array {
        $event = $this->event;
        $payingCount = max(1, (int) ($participantCount ?? $event->participant_count ?? 1));

        $cacheKey = $this->requestCacheKey($payingCount, $gratisOverride, $staffOverride, $driverOverride);
        if (isset(self::$requestCache[$cacheKey])) {
            return self::$requestCache[$cacheKey];
        }

        $this->ensureFreshBusRelation($event);
        $event->loadMissing([
            'markup',
            'eventTemplate.markup',
            'eventTemplate.taxes',
            'bus',
            'qtyVariants',
            'programPoints.templatePoint',
            'programPoints.currency',
            'dayInsurances.insurance',
            'hotelStays.roomLines.currency',
        ]);

        $variants = $event->relationLoaded('qtyVariants')
            ? $event->qtyVariants
            : $event->qtyVariants()->get(['qty', 'gratis', 'staff', 'driver']);

        $variant = $variants
            ->sortBy(fn ($v) => abs((int) ($v->qty ?? 0) - $payingCount))
            ->first();

        $gratis = $gratisOverride !== null
            ? max(0, $gratisOverride)
            : max(0, (int) ($variant->gratis ?? 0));
        $staff = $staffOverride !== null
            ? max(0, $staffOverride)
            : max(0, (int) ($variant->staff ?? 1));
        $driver = $driverOverride !== null
            ? max(0, $driverOverride)
            : max(0, (int) ($variant->driver ?? 1));

        $hotelPlanTotal = $this->hotelPlanTotal();
        $hasHotelPlan = $hotelPlanTotal !== null;
        $forceConvertForeign = ! ($event->eventTemplate?->isForeignTrip() ?? true);

        $lines = [];
        $foreignBuckets = [];

        // 1) Punkty programu (bez noclegu gdy jest plan hotelowy, bez transportu).
        foreach ($this->activeProgramPoints() as $point) {
            $isHotelService = (bool) ($point->is_hotel_service ?? false);

            if (! $isHotelService && $this->isTransportPoint($point)) {
                continue;
            }

            $isAccommodation = ! $isHotelService && $this->isAccommodationPoint($point);
            if ($isAccommodation && $hasHotelPlan) {
                continue;
            }

            $priced = $this->pointCostBreakdown($point, $payingCount, $gratis, $forceConvertForeign);
            if ($priced['pln'] > 0) {
                $category = 'program';
                if ($isHotelService) {
                    $category = 'hotel_service';
                } elseif ($isAccommodation) {
                    $category = 'accommodation';
                }

                $lines[] = [
                    'category' => $category,
                    'name' => (string) ($point->templatePoint?->name ?? $point->name ?? 'Pozycja'),
                    'cost_pln' => round($priced['pln'], 2),
                ];
            }

            foreach ($priced['foreign'] as $code => $amount) {
                if ($amount <= 0) {
                    continue;
                }
                $foreignBuckets[$code] = ($foreignBuckets[$code] ?? 0) + $amount;
            }
        }

        // 2) Nocleg z planu hotelowego (raz).
        if ($hasHotelPlan && $hotelPlanTotal > 0) {
            $lines[] = [
                'category' => 'accommodation',
                'name' => 'Nocleg (plan hotelowy)',
                'cost_pln' => round($hotelPlanTotal, 2),
            ];
        }

        // 3) Transport (raz).
        $transport = round((float) (new EventTransportCostCalculator($event))->effectiveTransportCost([
            'qty' => $payingCount, 'gratis' => $gratis, 'staff' => $staff, 'driver' => $driver,
        ]), 2);
        if ($transport > 0) {
            $lines[] = ['category' => 'transport', 'name' => 'Transport', 'cost_pln' => $transport];
        }

        // 4) Ubezpieczenie (raz).
        $insurance = round((float) $event->insuranceCostPln($payingCount, $gratis), 2);
        if ($insurance > 0) {
            $lines[] = ['category' => 'insurance', 'name' => 'Ubezpieczenie', 'cost_pln' => $insurance];
        }

        $base = round(array_sum(array_column($lines, 'cost_pln')), 2);

        $markupPercent = (float) ($event->markup?->percent ?? $event->eventTemplate?->markup?->percent ?? 0);
        $markup = round($base * ($markupPercent / 100), 2);

        [$taxTotal, $taxBreakdown] = $this->taxes($base, $markup);

        $total = round($base + $markup + $taxTotal, 2);

        $pricePerPerson = $payingCount > 0 ? round($total / $payingCount, 2) : 0.0;
        $pricePerPersonRounded = PriceRoundingService::roundPerPerson($pricePerPerson, 'PLN');

        $foreign = [];
        foreach ($foreignBuckets as $code => $foreignBase) {
            $foreignMarkup = round($foreignBase * ($markupPercent / 100), 2);
            $foreignTotal = round($foreignBase + $foreignMarkup, 2);
            $foreign[$code] = [
                'base' => round($foreignBase, 2),
                'markup' => $foreignMarkup,
                'total' => $foreignTotal,
                'price_per_person' => $payingCount > 0 ? round($foreignTotal / $payingCount, 2) : 0.0,
            ];
        }

        $result = [
            'qty' => $payingCount,
            'gratis' => $gratis,
            'staff' => $staff,
            'driver' => $driver,
            'paying' => $payingCount,
            'has_hotel_plan' => $hasHotelPlan,
            'lines' => $lines,
            'base_pln' => $base,
            'markup_percent' => $markupPercent,
            'markup_pln' => $markup,
            'tax_pln' => round($taxTotal, 2),
            'tax_breakdown' => $taxBreakdown,
            'total_pln' => $total,
            'price_per_person' => $pricePerPerson,
            'price_per_person_rounded' => $pricePerPersonRounded,
            'foreign' => $foreign,
        ];

        self::$requestCache[$cacheKey] = $result;

        return $result;
    }

    private function requestCacheKey(
        int $payingCount,
        ?int $gratisOverride,
        ?int $staffOverride = null,
        ?int $driverOverride = null,
    ): string {
        $e = $this->event;

        return implode(':', [
            (string) ($e->getKey() ?? 'new'),
            (string) ($e->updated_at?->timestamp ?? 0),
            (string) $payingCount,
            (string) ($gratisOverride ?? 'auto'),
            (string) ($staffOverride ?? 'auto'),
            (string) ($driverOverride ?? 'auto'),
            (string) round((float) ($e->transfer_km ?? 0), 2),
            (string) round((float) ($e->program_km ?? 0), 2),
            (string) (int) ($e->start_place_id ?? 0),
            (string) (int) ($e->bus_id ?? 0),
            (string) ((int) (bool) ($e->use_manual_transport_cost ?? false)),
            (string) round((float) ($e->manual_transport_cost ?? 0), 2),
        ]);
    }

    private function ensureFreshBusRelation(Event $event): void
    {
        $busId = (int) ($event->bus_id ?? 0);
        if ($busId <= 0) {
            return;
        }

        if (! $event->relationLoaded('bus')) {
            return;
        }

        $loaded = $event->getRelation('bus');
        if (
            $loaded === null
            || ! ($loaded instanceof Bus)
            || (int) $loaded->getKey() !== $busId
            || ! $loaded->hasTransportPricingAttributesLoaded()
        ) {
            $event->unsetRelation('bus');
        }
    }

    private function activeProgramPoints()
    {
        if ($this->event->relationLoaded('programPoints')) {
            return $this->event->programPoints
                ->filter(fn (EventProgramPoint $p) => (bool) ($p->active ?? true) && (bool) ($p->include_in_calculation ?? true));
        }

        return $this->event->programPoints()
            ->with(['templatePoint:id,name', 'currency:id,code,symbol,exchange_rate'])
            ->get()
            ->filter(fn (EventProgramPoint $p) => (bool) ($p->active ?? true) && (bool) ($p->include_in_calculation ?? true));
    }

    /**
     * @return array{pln: float, foreign: array<string, float>}
     */
    private function pointCostBreakdown(
        EventProgramPoint $point,
        int $payingCount,
        int $gratis = 0,
        bool $forceConvertForeign = false,
    ): array {
        $costHeadcount = max(
            1,
            $payingCount + ((bool) ($point->include_gratis_in_cost ?? false) ? max(0, $gratis) : 0)
        );
        $cost = (float) $point->resolveEffectiveTotalPrice($costHeadcount);
        if ($cost <= 0) {
            return ['pln' => 0.0, 'foreign' => []];
        }

        $code = strtoupper((string) ($point->currency?->code ?? $point->currency?->symbol ?? 'PLN'));
        if ($code === '' || $code === 'PLN') {
            return ['pln' => $cost, 'foreign' => []];
        }

        $convert = $forceConvertForeign || (bool) ($point->convert_to_pln ?? false);
        $rate = (float) ($point->currency?->exchange_rate ?? 0);

        if ($convert) {
            return [
                'pln' => $rate > 0 ? round($cost * $rate, 2) : $cost,
                'foreign' => [],
            ];
        }

        return [
            'pln' => 0.0,
            'foreign' => [$code => round($cost, 2)],
        ];
    }

    private function hotelPlanTotal(): ?float
    {
        if ($this->event->relationLoaded('hotelStays')) {
            if ($this->event->hotelStays->isEmpty()) {
                return null;
            }
        } elseif (! $this->event->hotelStays()->exists()) {
            return null;
        }

        return round((float) app(EventHotelPlanService::class)->totalPlnForEvent($this->event), 2);
    }

    private function isAccommodationPoint(EventProgramPoint $point): bool
    {
        if ((bool) ($point->is_hotel ?? false)) {
            return true;
        }

        $name = mb_strtolower((string) ($point->templatePoint?->name ?? $point->name ?? ''));

        foreach (['nocleg', 'zakwaterowanie', 'hotel', 'pobyt'] as $keyword) {
            if (str_contains($name, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function isTransportPoint(EventProgramPoint $point): bool
    {
        if ((bool) ($point->is_transport ?? false)) {
            return true;
        }

        return EventTransportCostCalculator::isTransportPointName(
            (string) ($point->templatePoint?->name ?? $point->name ?? '')
        );
    }

    /**
     * @return array{0: float, 1: array<int, array<string, mixed>>}
     */
    private function taxes(float $base, float $markup): array
    {
        $template = $this->event->eventTemplate;
        if (! $template) {
            return [0.0, []];
        }

        $total = 0.0;
        $breakdown = [];

        foreach ($template->taxes as $tax) {
            if (! $tax->is_active) {
                continue;
            }

            $amount = round((float) $tax->calculateTaxAmount($base, $markup), 2);
            if ($amount <= 0) {
                continue;
            }

            $total += $amount;
            $breakdown[] = [
                'name' => $tax->name,
                'percentage' => $tax->percentage,
                'amount' => $amount,
            ];
        }

        return [round($total, 2), $breakdown];
    }
}
