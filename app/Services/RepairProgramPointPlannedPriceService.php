<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventProgramPoint;
use Illuminate\Support\Collection;

/**
 * Naprawia planned_price zaseedowane błędnie jako suma dla 1 osoby
 * (stary applyTemplateDefaults), podczas gdy S/kalkulacja jest dla pełnego headcountu.
 */
final class RepairProgramPointPlannedPriceService
{
    /**
     * @return Collection<int, array{
     *     point: EventProgramPoint,
     *     event_id: int,
     *     from: float,
     *     to: float,
     *     name: string,
     * }>
     */
    public function candidates(?int $eventId = null): Collection
    {
        $query = EventProgramPoint::query()
            ->with(['event', 'currency', 'templatePoint'])
            ->whereNotNull('event_id')
            ->where('unit_price', '>', 0)
            ->where('planned_price', '>', 0);

        if ($eventId !== null) {
            $query->where('event_id', $eventId);
        }

        return $query
            ->get()
            ->map(function (EventProgramPoint $point): ?array {
                $fix = $this->detect($point);
                if ($fix === null) {
                    return null;
                }

                return [
                    'point' => $point,
                    'event_id' => (int) $point->event_id,
                    'from' => $fix['from'],
                    'to' => $fix['to'],
                    'name' => (string) ($point->name ?? $point->templatePoint?->name ?? '#'.$point->id),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @return array{from: float, to: float}|null
     */
    public function detect(EventProgramPoint $point): ?array
    {
        $event = $point->event;
        if (! $event instanceof Event) {
            return null;
        }

        $unit = round((float) ($point->unit_price ?? 0), 2);
        $planned = round((float) ($point->planned_price ?? 0), 2);
        if ($unit <= 0.009 || $planned <= 0.009) {
            return null;
        }

        // Stary seed: totalPrice(unit, headcount=1, group_size).
        $groupSize = $point->group_size === null ? 1 : (int) $point->group_size;
        $fixedQty = max(1, (int) ($point->quantity ?? 1));
        $buggySeed = ProgramPointPricingCalculator::totalPrice($unit, 1, $groupSize, $fixedQty);

        if (abs($planned - $buggySeed) > 0.009) {
            return null;
        }

        $correct = round($point->resolveCalculationTotal(
            max(1, (int) ($event->participant_count ?? 1))
        ), 2);

        if ($correct <= 0.009 || abs($correct - $planned) <= 0.009) {
            return null;
        }

        // Bug zawsze zaniżał (1 osoba zamiast grupy) — nie ruszamy wyższych planów.
        if ($correct < $planned) {
            return null;
        }

        return [
            'from' => $planned,
            'to' => $correct,
        ];
    }

    /**
     * @return array{repaired: int, skipped: int}
     */
    public function repair(?int $eventId = null, bool $dryRun = true): array
    {
        $candidates = $this->candidates($eventId);
        $repaired = 0;
        $eventsToRefresh = [];

        foreach ($candidates as $row) {
            if ($dryRun) {
                $repaired++;

                continue;
            }

            /** @var EventProgramPoint $point */
            $point = $row['point'];
            $point->planned_price = $row['to'];
            $point->save();
            $eventsToRefresh[(int) $point->event_id] = true;
            $repaired++;
        }

        if (! $dryRun) {
            foreach (array_keys($eventsToRefresh) as $id) {
                $event = Event::query()->find($id);
                $event?->refreshActiveSettlementCosts();
                $event?->calculateTotalCost();
            }
        }

        return [
            'repaired' => $repaired,
            'skipped' => 0,
        ];
    }
}
