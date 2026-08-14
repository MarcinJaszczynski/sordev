<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\ChangeSettlementCostPayerData;
use App\Data\UpsertFinanceProgramCostData;
use App\Models\Currency;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Services\EventProgramPointCreator;
use App\Services\ProgramPointPricingCalculator;
use App\Support\ProgramPointCostPricing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Tworzy / aktualizuje koszt programu z ekranu Finanse:
 * EventProgramPoint ↔ EventSettlementCost (upsert) + płatnik / termin.
 */
final class UpsertFinanceProgramCostAction
{
    public function __construct(
        private readonly EventProgramPointCreator $programPointCreator,
        private readonly ChangeSettlementCostPayerAction $changePayer,
    ) {}

    public function __invoke(UpsertFinanceProgramCostData $data): EventSettlementCost
    {
        $event = $data->event;
        Gate::authorize('manageFinance', $event);

        $name = trim($data->name);
        if ($name === '') {
            throw new InvalidArgumentException('Nazwa kosztu jest wymagana.');
        }

        $amount = round($data->amount, 2);
        if ($amount < 0) {
            throw new InvalidArgumentException('Kwota nie może być ujemna.');
        }

        $paidBy = array_key_exists($data->paidBy, EventSettlementCost::$paidByOptions)
            ? $data->paidBy
            : 'office';

        $day = max(1, $data->day);
        $duration = max(1, (int) ($event->duration_days ?? 1));
        if ($day > $duration) {
            $day = $duration;
        }

        $currencyId = $data->currencyId;
        if ($currencyId !== null && ! Currency::query()->whereKey($currencyId)->exists()) {
            throw new InvalidArgumentException('Nieprawidłowa waluta.');
        }

        return DB::transaction(function () use ($data, $event, $name, $amount, $paidBy, $day, $currencyId): EventSettlementCost {
            $settlement = EventSettlement::findOrCreateActiveForEvent($event);
            Gate::authorize('updateCostPlan', $settlement);

            $point = $this->resolveProgramPoint($data, $event, $name, $amount, $day, $currencyId);

            $cost = $settlement->upsertCostFromProgramPoint(
                $point->loadMissing('templatePoint', 'currency', 'event', 'reservations'),
            );

            $cost->update([
                'notes' => $data->notes,
                'advance_due_date' => $data->dueDate,
                'contractor_id' => $data->contractorId,
            ]);

            if ((int) ($point->contractor_id ?? 0) !== (int) ($data->contractorId ?? 0)) {
                $point->update(['contractor_id' => $data->contractorId]);
            }

            // upsertCostFromProgramPoint zawsze ustawia paid_by=office — przywracamy wybór użytkownika.
            ($this->changePayer)(new ChangeSettlementCostPayerData(
                planCost: $cost->fresh(),
                paidBy: $paidBy,
            ));

            return $cost->fresh();
        });
    }

    private function resolveProgramPoint(
        UpsertFinanceProgramCostData $data,
        Event $event,
        string $name,
        float $amount,
        int $day,
        ?int $currencyId,
    ): EventProgramPoint {
        $existing = $data->planCost;

        if ($existing) {
            if ($existing->source_type !== 'program_point' || ! $existing->source_id) {
                throw new InvalidArgumentException('Z Finansów można edytować tylko koszty punktów programu.');
            }

            $point = EventProgramPoint::query()
                ->where('event_id', $event->id)
                ->whereKey($existing->source_id)
                ->first();

            if (! $point) {
                throw new InvalidArgumentException('Nie znaleziono powiązanego punktu programu.');
            }

            $unitPrice = $this->unitPriceForDesiredTotal($point, $event, $amount);

            $point->update([
                'name' => $name,
                'day' => $day,
                'unit_price' => $unitPrice,
                'planned_price' => $amount,
                'currency_id' => $currencyId,
                'convert_to_pln' => $data->convertToPln,
                'notes' => $data->notes,
                'include_in_program' => true,
                'include_in_calculation' => true,
                'active' => true,
            ]);

            return $point->fresh();
        }

        return $this->programPointCreator->addBlank($event, $name, $day, null, [
            'unit_price' => $amount,
            'planned_price' => $amount,
            'quantity' => 1,
            'group_size' => 0,
            'currency_id' => $currencyId,
            'convert_to_pln' => $data->convertToPln,
            'notes' => $data->notes,
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);
    }

    private function unitPriceForDesiredTotal(EventProgramPoint $point, Event $event, float $desiredTotal): float
    {
        $paying = max(1, (int) ($event->participant_count ?? 1));
        $headcount = ProgramPointCostPricing::costHeadcountForPoint($point, $event, $paying);
        $billable = ProgramPointPricingCalculator::billableUnits(
            $headcount,
            $point->group_size,
            max(1, (int) ($point->quantity ?? 1)),
        );

        if ($billable <= 0) {
            return round($desiredTotal, 2);
        }

        return round($desiredTotal / $billable, 4);
    }
}
