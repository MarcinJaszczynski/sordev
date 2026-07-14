<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventSettlementCost;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Jednorazowy preload kosztów rozliczenia dla punktów programu (lista / RM).
 */
class ProgramPointSettlementCostCache
{
    /** @var array<int, EventSettlementCost|null> */
    private array $baseCostsByPointId = [];

    /** @var array<int, EloquentCollection<int, EventSettlementCost>> */
    private array $paymentRowsByPointId = [];

    private bool $warmed = false;

    /**
     * @param  Collection<int, EventProgramPoint>|EloquentCollection<int, EventProgramPoint>  $points
     */
    public function warm(Collection|EloquentCollection $points, Event $event): void
    {
        if ($this->warmed || $points->isEmpty()) {
            return;
        }

        $this->warmed = true;

        $settlement = $event->relationLoaded('activeSettlement')
            ? $event->activeSettlement
            : $event->activeSettlement()->first();

        if (! $settlement) {
            return;
        }

        $pointIds = $points->pluck('id')->filter()->unique()->values();

        $aggregator = app(ProgramPointSetFinanceAggregator::class);
        $childIds = $aggregator->childIdsForParents($points);
        if ($childIds !== []) {
            $pointIds = $pointIds->merge($childIds)->unique()->values();
        }

        if ($pointIds->isEmpty()) {
            return;
        }

        $costs = $settlement->costs()
            ->whereIn('source_id', $pointIds)
            ->whereIn('source_type', ['program_point', 'program_point_payment'])
            ->with(['plannedCurrency', 'actualCurrency'])
            ->get();

        foreach ($pointIds as $pointId) {
            $rows = $costs->where('source_id', $pointId);
            $this->baseCostsByPointId[(int) $pointId] = $rows
                ->first(fn (EventSettlementCost $cost): bool => $cost->source_type === 'program_point');
            $this->paymentRowsByPointId[(int) $pointId] = $rows
                ->filter(fn (EventSettlementCost $cost): bool => $cost->source_type === 'program_point_payment'
                    && $cost->payment_status !== 'cancelled')
                ->values();
        }
    }

    public function baseCost(int $pointId): ?EventSettlementCost
    {
        return $this->baseCostsByPointId[$pointId] ?? null;
    }

    /**
     * @return EloquentCollection<int, EventSettlementCost>|Collection<int, EventSettlementCost>
     */
    public function paymentRows(int $pointId): Collection|EloquentCollection
    {
        return $this->paymentRowsByPointId[$pointId] ?? collect();
    }
}
