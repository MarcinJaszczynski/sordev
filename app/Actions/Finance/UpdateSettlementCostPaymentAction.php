<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\RecalculateSettlementTotalsData;
use App\Data\UpdateSettlementCostPaymentData;
use App\Models\EventSettlementCost;
use App\Support\CurrencyAmountDisplay;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class UpdateSettlementCostPaymentAction
{
    public function __construct(
        private readonly RecalculateSettlementTotalsAction $recalculateSettlement,
    ) {}

    public function __invoke(UpdateSettlementCostPaymentData $data): EventSettlementCost
    {
        $payment = $data->payment;
        $settlement = $payment->settlement;
        if ($settlement) {
            Gate::authorize('recordCostPayment', $settlement);
        }

        if (! EventSettlementCost::isPaymentSourceType($payment->source_type)) {
            throw new InvalidArgumentException('Można edytować tylko wiersz wpłaty.');
        }

        $plan = $this->resolvePlanCost($payment);
        if (! $plan) {
            throw new InvalidArgumentException('Nie znaleziono pozycji planu dla tej wpłaty.');
        }

        [$amount, $rate, $amountPln, $currencyId] = $this->resolveAmounts($data, $plan);

        if ($amountPln <= 0 && $amount <= 0) {
            throw new InvalidArgumentException('Kwota wpłaty musi być większa od zera.');
        }

        $paidBy = array_key_exists($data->paidBy, EventSettlementCost::$paidByOptions)
            ? $data->paidBy
            : 'office';
        $method = $paidBy === 'pilot'
            ? 'cash'
            : (array_key_exists($data->paymentMethod, EventSettlementCost::$paymentMethods)
                ? $data->paymentMethod
                : 'transfer');
        $advanceType = array_key_exists($data->advanceType, EventSettlementCost::$advanceTypes)
            ? $data->advanceType
            : 'advance';

        return DB::transaction(function () use ($data, $payment, $plan, $amount, $rate, $amountPln, $currencyId, $method, $paidBy, $advanceType): EventSettlementCost {
            $settlement = $payment->settlement()->firstOrFail();

            $payment->update([
                'actual_amount' => $amount,
                'actual_currency_id' => $currencyId,
                'actual_rate' => $rate,
                'actual_amount_pln' => $amountPln,
                'paid_by' => $paidBy,
                'advance_type' => $advanceType,
                'payment_method' => $method,
                'document_number' => $data->documentNumber,
                'paid_at' => $data->paidAt ?? $payment->paid_at ?? now(),
                'advance_due_date' => $data->dueDate,
                'advance_amount' => EventSettlementCost::isAdvancePaymentType($advanceType) ? $amount : null,
                'notes' => $data->notes,
                'payment_status' => EventSettlementCost::isAdvancePaymentType($advanceType) ? 'advance_paid' : 'paid',
            ]);

            $this->refreshPlanPaymentStatus($plan->fresh(), $settlement->fresh(['costs']));

            ($this->recalculateSettlement)(new RecalculateSettlementTotalsData(
                settlement: $settlement->fresh(),
                fullRefresh: false,
            ));

            app(\App\Services\PilotSettlementService::class)->refreshCashFromCosts($settlement->fresh() ?? $settlement);

            return $payment->fresh();
        });
    }

    /**
     * @return array{0: float, 1: float, 2: float, 3: int|string|null}
     */
    private function resolveAmounts(UpdateSettlementCostPaymentData $data, EventSettlementCost $plan): array
    {
        $currencyId = $data->currencyId ?? $plan->planned_currency_id;
        $planCurrency = $plan->relationLoaded('plannedCurrency')
            ? $plan->plannedCurrency
            : $plan->plannedCurrency()->first();
        $symbol = CurrencyAmountDisplay::symbol($planCurrency);
        $defaultRate = (float) ($plan->planned_rate ?? ($planCurrency?->exchange_rate ?? 1));
        if ($defaultRate <= 0) {
            $defaultRate = 1.0;
        }

        $isForeign = $symbol !== 'PLN' && $currencyId;

        if ($isForeign && $data->amount !== null && $data->amount > 0) {
            $amount = round((float) $data->amount, 2);
            $rate = round((float) ($data->rate ?? $defaultRate), 6);
            if ($rate <= 0) {
                $rate = $defaultRate;
            }
            $amountPln = $data->amountPln > 0
                ? round($data->amountPln, 2)
                : round($amount * $rate, 2);

            return [$amount, $rate, $amountPln, $currencyId];
        }

        $amountPln = round($data->amountPln, 2);

        return [$amountPln, 1.0, $amountPln, $currencyId ?: $plan->planned_currency_id];
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

    private function refreshPlanPaymentStatus(EventSettlementCost $plan, $settlement): void
    {
        app(\App\Services\SettlementPaymentHealthService::class)
            ->syncPlanPaymentStatus($plan, $settlement->costs);
    }
}
