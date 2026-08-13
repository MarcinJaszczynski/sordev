<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\RecalculateSettlementTotalsData;
use App\Data\UpsertFinanceManualCostData;
use App\Models\Currency;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Tworzy / aktualizuje wydatek tylko w rozliczeniu (source_type=manual), bez punktu programu.
 */
final class UpsertFinanceManualCostAction
{
    public function __construct(
        private readonly RecalculateSettlementTotalsAction $recalculateSettlement,
    ) {}

    public function __invoke(UpsertFinanceManualCostData $data): EventSettlementCost
    {
        $event = $data->event;
        Gate::authorize('manageFinance', $event);

        $name = trim($data->name);
        if ($name === '') {
            throw new InvalidArgumentException('Nazwa wydatku jest wymagana.');
        }

        $amount = round($data->amount, 2);
        if ($amount < 0) {
            throw new InvalidArgumentException('Kwota nie może być ujemna.');
        }

        $paidBy = array_key_exists($data->paidBy, EventSettlementCost::$paidByOptions)
            ? $data->paidBy
            : 'office';

        $currencyId = $data->currencyId;
        if ($currencyId !== null && ! Currency::query()->whereKey($currencyId)->exists()) {
            throw new InvalidArgumentException('Nieprawidłowa waluta.');
        }

        return DB::transaction(function () use ($data, $event, $name, $amount, $paidBy, $currencyId): EventSettlementCost {
            $settlement = EventSettlement::findOrCreateActiveForEvent($event);
            Gate::authorize('updateCostPlan', $settlement);

            $existing = $data->planCost;
            if ($existing && $existing->source_type !== 'manual') {
                throw new InvalidArgumentException('Ten formularz służy tylko do wydatków nieprzewidzianych.');
            }

            $currency = $currencyId ? Currency::query()->find($currencyId) : null;
            $rate = (float) ($currency?->exchange_rate ?? 1);
            if ($rate <= 0) {
                $rate = 1.0;
            }

            $symbol = strtoupper((string) ($currency?->symbol ?? $currency?->code ?? 'PLN'));
            $convertToPln = $data->convertToPln;
            $plannedAmountPln = $symbol === 'PLN' || $convertToPln
                ? round($amount * ($symbol === 'PLN' ? 1 : $rate), 2)
                : null;

            $payload = [
                'name' => $name,
                'planned_amount' => $amount,
                'planned_currency_id' => $currencyId,
                'planned_convert_to_pln' => $convertToPln,
                'planned_rate' => $rate,
                'planned_amount_pln' => $plannedAmountPln,
                'paid_by' => $paidBy,
                'notes' => $data->notes,
                'advance_due_date' => $data->dueDate,
                'contractor_id' => $data->contractorId,
            ];

            if ($existing) {
                abort_unless((int) $existing->settlement_id === (int) $settlement->id, 403);
                $existing->update($payload);
                $cost = $existing->fresh();
            } else {
                $maxOrder = (int) $settlement->costs()->max('order');
                $cost = $settlement->costs()->create(array_merge($payload, [
                    'source_type' => 'manual',
                    'payment_status' => 'planned',
                    'order' => $maxOrder + 1,
                ]));
            }

            ($this->recalculateSettlement)(new RecalculateSettlementTotalsData(
                settlement: $settlement->fresh(),
                fullRefresh: false,
            ));

            return $cost->fresh();
        });
    }
}
