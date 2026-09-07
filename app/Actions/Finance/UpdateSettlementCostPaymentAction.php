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
            throw new InvalidArgumentException('Nie znaleziono kosztu dla tej wpłaty.');
        }

        [$amount, $rate, $amountPln, $currencyId] = $this->resolveAmounts($data, $plan);

        if ($amountPln < 0 || $amount < 0) {
            throw new InvalidArgumentException('Kwota wpłaty nie może być ujemna.');
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

            $isScheduled = $data->paidAt === null && $data->dueDate !== null;
            $paidAt = $isScheduled ? null : ($data->paidAt ?? $payment->paid_at ?? now());
            $isAdvance = EventSettlementCost::isAdvancePaymentType($advanceType);
            $paymentStatus = $isScheduled
                ? ($isAdvance ? 'advance_required' : 'planned')
                : ($isAdvance ? 'advance_paid' : 'paid');

            $payment->update([
                'planned_convert_to_pln' => $data->convertToPln,
                'planned_amount' => ($isScheduled && ! $isAdvance) ? $amount : 0,
                'planned_currency_id' => $currencyId ?: $payment->planned_currency_id,
                'planned_rate' => $rate ?: ($payment->planned_rate ?? 1),
                'planned_amount_pln' => ($isScheduled && ! $isAdvance) ? $amountPln : 0,
                'actual_amount' => $isScheduled ? null : $amount,
                'actual_currency_id' => $isScheduled ? null : $currencyId,
                'actual_rate' => $isScheduled ? null : $rate,
                'actual_amount_pln' => $isScheduled
                    ? null
                    : ($data->convertToPln ? $amountPln : ($amountPln > 0 ? $amountPln : null)),
                'paid_by' => $paidBy,
                'advance_type' => $advanceType,
                'payment_method' => $method,
                'document_number' => $data->documentNumber,
                'paid_at' => $paidAt,
                'advance_due_date' => $data->dueDate,
                'advance_amount' => $isAdvance ? $amount : null,
                'notes' => $data->notes,
                'payment_status' => $paymentStatus,
                ...(\Illuminate\Support\Facades\Schema::hasColumn('event_settlement_costs', 'reservation_id')
                    ? ['reservation_id' => $data->reservationId ?? $payment->reservation_id]
                    : []),
            ]);

            $this->refreshPlanPaymentStatus(
                $plan->fresh() ?? $plan,
                $settlement->fresh(['costs']),
                $data->approveOverpayment,
            );

            ($this->recalculateSettlement)(new RecalculateSettlementTotalsData(
                settlement: $settlement->fresh(),
                fullRefresh: false,
            ));

            app(\App\Services\PilotSettlementService::class)->refreshCashFromCosts($settlement->fresh() ?? $settlement);

            app(\App\Services\SyncReservationDepositFromCostPayment::class)
                ->refreshAfterPaymentsChanged($plan->fresh());

            return $payment->fresh();
        });
    }

    /**
     * @return array{0: float, 1: float, 2: float, 3: int|string|null}
     */
    private function resolveAmounts(UpdateSettlementCostPaymentData $data, EventSettlementCost $plan): array
    {
        $existing = $data->payment;
        $currencyId = $data->currencyId ?? $existing->actual_currency_id ?? $plan->planned_currency_id;
        $planCurrency = $plan->relationLoaded('plannedCurrency')
            ? $plan->plannedCurrency
            : $plan->plannedCurrency()->first();
        $defaultRate = (float) ($data->rate ?? $existing->actual_rate ?? $plan->planned_rate ?? ($planCurrency?->exchange_rate ?? 1));
        if ($defaultRate <= 0) {
            $defaultRate = 1.0;
        }

        $isForeign = CurrencyAmountDisplay::isForeignCurrency($currencyId);

        if ($isForeign && $data->amount !== null) {
            $amount = round((float) $data->amount, 2);
            $rate = round((float) ($data->rate ?? $defaultRate), 6);
            if ($rate <= 0) {
                $rate = $defaultRate;
            }
            if (! $data->convertToPln) {
                return [$amount, $rate, 0.0, $currencyId];
            }
            $amountPln = $data->amountPln > 0
                ? round($data->amountPln, 2)
                : ($amount > 0 ? round($amount * $rate, 2) : 0.0);

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

        if (in_array($payment->source_type, ['transport_payment', 'accommodation_payment', 'accommodation_hotel_payment', 'accommodation_hotel_stay_payment'], true)) {
            $planType = str_replace('_payment', '', $payment->source_type);

            return EventSettlementCost::query()
                ->where('settlement_id', $settlementId)
                ->where('source_type', $planType)
                ->whereNull('source_id')
                ->orderBy('id')
                ->first();
        }

        if (in_array($payment->source_type, [
            'accommodation_hotel_payment',
            'accommodation_hotel_stay_payment',
            'transport_contractor_payment',
        ], true)) {
            $planType = str_replace('_payment', '', $payment->source_type);

            return EventSettlementCost::query()
                ->where('settlement_id', $settlementId)
                ->where('source_type', $planType)
                ->when(
                    $payment->source_id !== null,
                    fn ($q) => $q->where('source_id', (int) $payment->source_id),
                    fn ($q) => $q->whereNull('source_id'),
                )
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

    private function refreshPlanPaymentStatus(
        EventSettlementCost $plan,
        $settlement,
        bool $approveOverpayment = false,
    ): void {
        $health = app(\App\Services\SettlementPaymentHealthService::class);
        $allCosts = $settlement->fresh(['costs'])?->costs ?? $settlement->costs;
        $health->syncPlanPaymentStatusAfterPayment(
            $plan,
            $allCosts,
            $approveOverpayment,
            auth()->id(),
        );

        if ($plan->source_type === 'insurance_day') {
            app(\App\Services\EventInsuranceOperationalSync::class)->syncFromPlanCost($plan->fresh());
        }
    }
}
