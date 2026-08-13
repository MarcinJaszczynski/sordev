<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\RecalculateSettlementTotalsData;
use App\Data\RecordSettlementCostPaymentData;
use App\Models\EventSettlementCost;
use App\Support\CurrencyAmountDisplay;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Dodaje kolejną wpłatę (zaliczkę / dopłatę) do pozycji planu kosztów.
 * Persystencja: osobny wiersz event_settlement_costs ze source_type *_payment
 * (zgodnie z istniejącym SettlementAggregateFinanceService).
 */
final class RecordSettlementCostPaymentAction
{
    public function __construct(
        private readonly RecalculateSettlementTotalsAction $recalculateSettlement,
    ) {}

    public function __invoke(RecordSettlementCostPaymentData $data): EventSettlementCost
    {
        $plan = $data->planCost;
        $settlement = $plan->settlement;
        if ($settlement) {
            \Illuminate\Support\Facades\Gate::authorize('recordCostPayment', $settlement);
        }

        if (EventSettlementCost::isPaymentSourceType($plan->source_type)) {
            throw new InvalidArgumentException('Wpłatę można dodać tylko do pozycji planu, nie do wiersza płatności.');
        }

        [$amount, $rate, $amountPln, $currencyId] = $this->resolveAmounts($data, $plan);

        if ($amountPln <= 0 && $amount <= 0) {
            throw new InvalidArgumentException('Kwota wpłaty musi być większa od zera.');
        }

        $paidBy = array_key_exists($data->paidBy, EventSettlementCost::$paidByOptions)
            ? $data->paidBy
            : 'office';
        // Pilot płaci wyłącznie gotówką.
        $method = $paidBy === 'pilot'
            ? 'cash'
            : (array_key_exists($data->paymentMethod, EventSettlementCost::$paymentMethods)
                ? $data->paymentMethod
                : 'transfer');
        $advanceType = array_key_exists($data->advanceType, EventSettlementCost::$advanceTypes)
            ? $data->advanceType
            : 'advance';

        return DB::transaction(function () use ($data, $plan, $amount, $rate, $amountPln, $currencyId, $method, $paidBy, $advanceType): EventSettlementCost {
            $settlement = $plan->settlement()->firstOrFail();
            [$paymentSourceType, $paymentSourceId] = $this->resolvePaymentSource($plan);

            $existingCount = $settlement->costs()
                ->where('source_type', $paymentSourceType)
                ->when(
                    $paymentSourceId === null,
                    fn ($q) => $q->whereNull('source_id'),
                    fn ($q) => $q->where('source_id', $paymentSourceId),
                )
                ->count();

            $baseName = $plan->name ?: 'Koszt';
            $label = match ($advanceType) {
                'advance' => 'zaliczka',
                'deposit' => 'kaucja',
                'supplement' => 'dopłata',
                'final' => 'dopłata całkowita',
                'full' => 'wpłata całkowita',
                default => 'wpłata',
            };

            $payment = $settlement->costs()->create([
                'source_type' => $paymentSourceType,
                'source_id' => $paymentSourceId,
                'name' => $baseName.' • '.$label.' #'.($existingCount + 1),
                'planned_amount' => 0,
                'planned_currency_id' => $plan->planned_currency_id,
                'planned_convert_to_pln' => $plan->planned_convert_to_pln ?? true,
                'planned_rate' => $plan->planned_rate ?? 1,
                'planned_amount_pln' => 0,
                'actual_amount' => $amount,
                'actual_currency_id' => $currencyId,
                'actual_rate' => $rate,
                'actual_amount_pln' => $amountPln,
                'paid_by' => $paidBy,
                'advance_type' => $advanceType,
                'payment_method' => $method,
                'document_number' => $data->documentNumber,
                'paid_at' => $data->paidAt ?? now(),
                'paid_by_user_id' => $data->paidByUserId,
                'payment_status' => EventSettlementCost::isAdvancePaymentType($advanceType) ? 'advance_paid' : 'paid',
                'advance_due_date' => $data->dueDate,
                'advance_amount' => EventSettlementCost::isAdvancePaymentType($advanceType) ? $amount : null,
                'notes' => $data->notes,
                'contractor_id' => $plan->contractor_id,
                'order' => (int) ($plan->order ?? 0) + $existingCount + 1,
            ]);

            $this->refreshPlanPaymentStatus($plan, $settlement->fresh(['costs']));

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
    private function resolveAmounts(RecordSettlementCostPaymentData $data, EventSettlementCost $plan): array
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

        // PLN (lub legacy: tylko amountPln)
        $amountPln = round($data->amountPln, 2);

        return [$amountPln, 1.0, $amountPln, $currencyId ?: $plan->planned_currency_id];
    }

    /**
     * @return array{0: string, 1: int|null}
     */
    private function resolvePaymentSource(EventSettlementCost $plan): array
    {
        if ($plan->source_type === 'program_point') {
            return ['program_point_payment', $plan->source_id ? (int) $plan->source_id : null];
        }

        if (in_array($plan->source_type, ['transport', 'accommodation'], true)) {
            return [$plan->source_type.'_payment', null];
        }

        if ($plan->source_type === 'manual') {
            return ['manual_payment', (int) $plan->id];
        }

        return [$plan->source_type.'_payment', $plan->source_id ? (int) $plan->source_id : null];
    }

    private function refreshPlanPaymentStatus(EventSettlementCost $plan, $settlement): void
    {
        app(\App\Services\SettlementPaymentHealthService::class)
            ->syncPlanPaymentStatus($plan, $settlement->costs);
    }
}
