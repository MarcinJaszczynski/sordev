<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\ChangeSettlementCostPayerData;
use App\Data\RecalculateSettlementTotalsData;
use App\Models\EventSettlementCost;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Zmienia płatnika pozycji planu (kto odpowiada za pozostałą kwotę).
 * Historyczne wpłaty zachowują swojego płatnika — nie nadpisujemy ich.
 */
final class ChangeSettlementCostPayerAction
{
    public function __construct(
        private readonly RecalculateSettlementTotalsAction $recalculateSettlement,
    ) {}

    public function __invoke(ChangeSettlementCostPayerData $data): EventSettlementCost
    {
        $plan = $data->planCost;
        $settlement = $plan->settlement;
        if ($settlement) {
            Gate::authorize('updateCostPlan', $settlement);
        }

        if (EventSettlementCost::isPaymentSourceType($plan->source_type)) {
            throw new InvalidArgumentException('Można zmieniać płatnika tylko dla kosztu planowanego.');
        }

        $paidBy = array_key_exists($data->paidBy, EventSettlementCost::$paidByOptions)
            ? $data->paidBy
            : 'office';

        return DB::transaction(function () use ($plan, $paidBy, $settlement): EventSettlementCost {
            $plan->update(['paid_by' => $paidBy]);

            if ($settlement) {
                ($this->recalculateSettlement)(new RecalculateSettlementTotalsData(
                    settlement: $settlement->fresh() ?? $settlement,
                    fullRefresh: false,
                ));
                $settlement->touch();
                if ($settlement->event_id) {
                    \App\Services\EventFinanceOverviewService::forgetOverviewCacheForEvent((int) $settlement->event_id);
                }
            }

            return $plan->fresh();
        });
    }

    /**
     * @param  iterable<int, EventSettlementCost>  $planCosts
     */
    public function forMany(iterable $planCosts, string $paidBy): int
    {
        $paidBy = array_key_exists($paidBy, EventSettlementCost::$paidByOptions)
            ? $paidBy
            : 'office';

        $updated = 0;
        $settlementsToRecalc = [];

        DB::transaction(function () use ($planCosts, $paidBy, &$updated, &$settlementsToRecalc): void {
            foreach ($planCosts as $plan) {
                if (! $plan instanceof EventSettlementCost) {
                    continue;
                }

                if (EventSettlementCost::isPaymentSourceType($plan->source_type)) {
                    continue;
                }

                $settlement = $plan->settlement;
                if (! $settlement) {
                    continue;
                }

                Gate::authorize('updateCostPlan', $settlement);

                $plan->update(['paid_by' => $paidBy]);
                $updated++;
                $settlementsToRecalc[(int) $settlement->id] = $settlement;
            }

            foreach ($settlementsToRecalc as $settlement) {
                ($this->recalculateSettlement)(new RecalculateSettlementTotalsData(
                    settlement: $settlement->fresh() ?? $settlement,
                    fullRefresh: false,
                ));
                $settlement->touch();
                if ($settlement->event_id) {
                    \App\Services\EventFinanceOverviewService::forgetOverviewCacheForEvent((int) $settlement->event_id);
                }
            }
        });

        return $updated;
    }
}
