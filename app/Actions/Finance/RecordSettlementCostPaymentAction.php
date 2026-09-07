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
            throw new InvalidArgumentException('Wpłatę można dodać tylko do kosztu planowanego, nie do wiersza płatności.');
        }

        [$amount, $rate, $amountPln, $currencyId] = $this->resolveAmounts($data, $plan);

        // 0 jest dozwolone (świadome domknięcie / pokrycie na innej pozycji); ujemne nie.
        if ($amountPln < 0 || $amount < 0) {
            throw new InvalidArgumentException('Kwota wpłaty nie może być ujemna.');
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
            $reservationId = app(\App\Services\SyncReservationDepositFromCostPayment::class)
                ->resolveReservationIdForNewPayment($plan, $data->reservationId);

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

            // Zaplanowana płatność: jest termin, brak daty wpłaty — świeci się „do zapłaty”, bez actual.
            // BC: brak paidAt i brak dueDate → księguj jak dotychczas (paid_at = dziś).
            $isScheduled = $data->paidAt === null && $data->dueDate !== null;
            $paidAt = $isScheduled ? null : ($data->paidAt ?? now());
            $isAdvance = EventSettlementCost::isAdvancePaymentType($advanceType);
            $paymentStatus = $isScheduled
                ? ($isAdvance ? 'advance_required' : 'planned')
                : ($isAdvance ? 'advance_paid' : 'paid');

            $payload = [
                'source_type' => $paymentSourceType,
                'source_id' => $paymentSourceId,
                'name' => $baseName.' • '.$label.' #'.($existingCount + 1),
                'planned_amount' => ($isScheduled && ! $isAdvance) ? $amount : 0,
                'planned_currency_id' => $currencyId ?: $plan->planned_currency_id,
                'planned_convert_to_pln' => $data->convertToPln,
                'planned_rate' => $rate ?: ($plan->planned_rate ?? 1),
                'planned_amount_pln' => ($isScheduled && ! $isAdvance) ? $amountPln : 0,
                'actual_amount' => $isScheduled ? null : $amount,
                'actual_currency_id' => $isScheduled ? null : $currencyId,
                'actual_rate' => $isScheduled ? null : $rate,
                // 0 PLN jest poprawną kwotą; null tylko gdy obca bez przeliczenia (convert_to_pln=false).
                'actual_amount_pln' => $isScheduled
                    ? null
                    : ($data->convertToPln ? $amountPln : ($amountPln > 0 ? $amountPln : null)),
                'paid_by' => $paidBy,
                'advance_type' => $advanceType,
                'payment_method' => $method,
                'document_number' => $data->documentNumber,
                'paid_at' => $paidAt,
                'paid_by_user_id' => $isScheduled ? null : $data->paidByUserId,
                'payment_status' => $paymentStatus,
                'advance_due_date' => $data->dueDate,
                'advance_amount' => $isAdvance ? $amount : null,
                'notes' => $data->notes,
                'contractor_id' => $plan->contractor_id,
                'order' => (int) ($plan->order ?? 0) + $existingCount + 1,
            ];

            if ($reservationId && \Illuminate\Support\Facades\Schema::hasColumn('event_settlement_costs', 'reservation_id')) {
                $payload['reservation_id'] = $reservationId;
            }

            $payment = $settlement->costs()->create($payload);

            $this->refreshPlanPaymentStatus(
                $plan,
                $settlement->fresh(['costs']),
                $data->approveOverpayment,
            );

            ($this->recalculateSettlement)(new RecalculateSettlementTotalsData(
                settlement: $settlement->fresh(),
                fullRefresh: false,
            ));

            app(\App\Services\PilotSettlementService::class)->refreshCashFromCosts($settlement->fresh() ?? $settlement);

            if (! $isScheduled) {
                app(\App\Services\SyncReservationDepositFromCostPayment::class)
                    ->markPaidFromPayment($plan->fresh(), $payment->fresh());
            }

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
        $defaultRate = (float) ($data->rate ?? $plan->planned_rate ?? ($planCurrency?->exchange_rate ?? 1));
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

        if (in_array($plan->source_type, [
            'transport',
            'transport_contractor',
            'accommodation',
            'accommodation_hotel',
            'accommodation_hotel_stay',
        ], true)) {
            return [$plan->source_type.'_payment', $plan->source_id ? (int) $plan->source_id : null];
        }

        if ($plan->source_type === 'manual') {
            return ['manual_payment', (int) $plan->id];
        }

        return [$plan->source_type.'_payment', $plan->source_id ? (int) $plan->source_id : null];
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
