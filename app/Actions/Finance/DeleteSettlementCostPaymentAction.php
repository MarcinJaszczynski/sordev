<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\RecalculateSettlementTotalsData;
use App\Models\EventSettlementCost;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class DeleteSettlementCostPaymentAction
{
    public function __construct(
        private readonly RecalculateSettlementTotalsAction $recalculateSettlement,
    ) {}

    public function __invoke(EventSettlementCost $payment): void
    {
        $settlement = $payment->settlement;
        if ($settlement) {
            Gate::authorize('recordCostPayment', $settlement);
        }

        if (! EventSettlementCost::isPaymentSourceType($payment->source_type)) {
            throw new InvalidArgumentException('Można usunąć tylko wiersz wpłaty.');
        }

        DB::transaction(function () use ($payment): void {
            $settlement = $payment->settlement()->firstOrFail();
            $plan = $this->resolvePlanCost($payment);

            $payment->delete();

            if ($plan) {
                $freshSettlement = $settlement->fresh(['costs']);
                if ($freshSettlement) {
                    app(\App\Services\SettlementPaymentHealthService::class)
                        ->syncPlanPaymentStatus($plan, $freshSettlement->costs);
                }
            }

            ($this->recalculateSettlement)(new RecalculateSettlementTotalsData(
                settlement: $settlement->fresh() ?? $settlement,
                fullRefresh: false,
            ));

            app(\App\Services\PilotSettlementService::class)->refreshCashFromCosts($settlement->fresh() ?? $settlement);
        });
    }

    private function resolvePlanCost(EventSettlementCost $payment): ?EventSettlementCost
    {
        $settlementId = (int) $payment->settlement_id;

        if ($payment->source_type === 'manual_payment' && $payment->source_id) {
            return EventSettlementCost::query()
                ->where('settlement_id', $settlementId)
                ->where('id', (int) $payment->source_id)
                ->first();
        }

        if ($payment->source_type === 'program_point_payment' && $payment->source_id) {
            return EventSettlementCost::query()
                ->where('settlement_id', $settlementId)
                ->where('source_type', 'program_point')
                ->where('source_id', (int) $payment->source_id)
                ->first();
        }

        if (in_array($payment->source_type, ['transport_payment', 'accommodation_payment'], true)) {
            $planType = str_replace('_payment', '', $payment->source_type);

            return EventSettlementCost::query()
                ->where('settlement_id', $settlementId)
                ->where('source_type', $planType)
                ->orderBy('id')
                ->first();
        }

        if ($payment->source_type === 'insurance_day_payment' && $payment->source_id) {
            return EventSettlementCost::query()
                ->where('settlement_id', $settlementId)
                ->where('source_type', 'insurance_day')
                ->where('source_id', (int) $payment->source_id)
                ->first();
        }

        return null;
    }
}
