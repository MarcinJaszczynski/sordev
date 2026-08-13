<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\RecalculateSettlementTotalsData;
use App\Data\UpdateSettlementCostPlanData;
use App\Models\EventSettlementCost;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class UpdateSettlementCostPlanAction
{
    public function __construct(
        private readonly RecalculateSettlementTotalsAction $recalculateSettlement,
    ) {}

    public function __invoke(UpdateSettlementCostPlanData $data): EventSettlementCost
    {
        $plan = $data->planCost;
        $settlement = $plan->settlement;
        if ($settlement) {
            \Illuminate\Support\Facades\Gate::authorize('updateCostPlan', $settlement);
        }

        if (EventSettlementCost::isPaymentSourceType($plan->source_type)) {
            throw new InvalidArgumentException('Można edytować tylko pozycję planu.');
        }

        $amountPln = round($data->plannedAmountPln, 2);
        $amount = round($data->plannedAmount ?? $amountPln, 2);
        $paidBy = array_key_exists($data->paidBy, EventSettlementCost::$paidByOptions)
            ? $data->paidBy
            : 'office';

        return DB::transaction(function () use ($data, $plan, $amount, $amountPln, $paidBy): EventSettlementCost {
            $payload = [
                'planned_amount' => $amount,
                'planned_amount_pln' => $amountPln,
                'planned_convert_to_pln' => $data->plannedConvertToPln ?? true,
                'paid_by' => $paidBy,
                'notes' => $data->notes,
                'advance_due_date' => $data->dueDate,
            ];

            if ($data->plannedCurrencyId !== null) {
                $payload['planned_currency_id'] = $data->plannedCurrencyId;
            }

            if ($data->plannedRate !== null) {
                $payload['planned_rate'] = $data->plannedRate;
            }

            if ($data->touchContractor) {
                $payload['contractor_id'] = $data->contractorId;
            }

            if ($data->paymentMethod !== null) {
                $payload['payment_method'] = $data->paymentMethod;
            }

            if ($data->advanceType !== null) {
                $payload['advance_type'] = $data->advanceType;
            }

            $plan->update($payload);

            $settlement = $plan->settlement;
            if ($settlement) {
                ($this->recalculateSettlement)(new RecalculateSettlementTotalsData(
                    settlement: $settlement,
                    fullRefresh: false,
                ));
            }

            return $plan->fresh();
        });
    }
}
