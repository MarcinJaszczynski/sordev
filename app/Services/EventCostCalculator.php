<?php

namespace App\Services;

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
 *  - ubezpieczenie liczone raz (Event::insuranceCostPln),
 *  - baza → marża → podatki → SUMA KOŃCOWA,
 *  - cena za osobę = SUMA KOŃCOWA ÷ liczba osób PŁACĄCYCH (bez gratisów/obsługi/kierowcy).
 */
class EventCostCalculator
{
    public function __construct(private readonly Event $event) {}

    public static function for(Event $event): self
    {
        return new self($event);
    }

    /**
     * @return array<string, mixed>
     */
    public function calculate(?int $participantCount = null): array
    {
        $event = $this->event;
        $payingCount = max(1, (int) ($participantCount ?? $event->participant_count ?? 1));

        $variant = $event->qtyVariants()
            ->get(['qty', 'gratis', 'staff', 'driver'])
            ->sortBy(fn ($v) => abs((int) ($v->qty ?? 0) - $payingCount))
            ->first();

        $gratis = max(0, (int) ($variant->gratis ?? 0));
        $staff = max(0, (int) ($variant->staff ?? 1));
        $driver = max(0, (int) ($variant->driver ?? 1));

        $hotelPlanTotal = $this->hotelPlanTotal();
        $hasHotelPlan = $hotelPlanTotal !== null;

        $lines = [];

        // 1) Punkty programu (bez noclegu gdy jest plan hotelowy, bez transportu).
        foreach ($this->activeProgramPoints() as $point) {
            // Usługa hotelu (bankiet, obiad, DJ...) ma priorytet nad heurystyką nazwy/noclegu:
            // liczona jest zawsze raz w bazie i NIE jest wykluczana przez plan hotelowy.
            $isHotelService = (bool) ($point->is_hotel_service ?? false);

            if (! $isHotelService && $this->isTransportPoint($point)) {
                continue; // transport liczony osobno
            }

            $isAccommodation = ! $isHotelService && $this->isAccommodationPoint($point);
            if ($isAccommodation && $hasHotelPlan) {
                continue; // nocleg z planu hotelowego ma priorytet
            }

            $costPln = $this->pointCostPln($point, $payingCount);
            if ($costPln <= 0) {
                continue;
            }

            $category = 'program';
            if ($isHotelService) {
                $category = 'hotel_service';
            } elseif ($isAccommodation) {
                $category = 'accommodation';
            }

            $lines[] = [
                'category' => $category,
                'name' => (string) ($point->templatePoint?->name ?? $point->name ?? 'Pozycja'),
                'cost_pln' => round($costPln, 2),
            ];
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

        return [
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
        ];
    }

    private function activeProgramPoints()
    {
        return $this->event->programPoints()
            ->with(['templatePoint:id,name', 'currency:id,code,symbol,exchange_rate'])
            ->get()
            ->filter(fn (EventProgramPoint $p) => (bool) ($p->active ?? true) && (bool) ($p->include_in_calculation ?? true));
    }

    private function pointCostPln(EventProgramPoint $point, int $count): float
    {
        $cost = (float) $point->resolveEffectiveTotalPrice($count);
        if ($cost <= 0) {
            return 0.0;
        }

        $code = strtoupper((string) ($point->currency?->code ?? $point->currency?->symbol ?? 'PLN'));
        if ($code !== 'PLN' && $code !== '' && ! ($point->convert_to_pln ?? false)) {
            return 0.0;
        }

        if ($code === 'PLN' || $code === '') {
            return $cost;
        }

        // Koszt w walucie obcej → PLN po kursie (zgodnie z logiką cennika).
        $rate = (float) ($point->currency?->exchange_rate ?? 0);

        return $rate > 0 ? round($cost * $rate, 2) : $cost;
    }

    private function hotelPlanTotal(): ?float
    {
        if (! $this->event->hotelStays()->exists()) {
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
