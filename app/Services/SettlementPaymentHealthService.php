<?php

namespace App\Services;

use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class SettlementPaymentHealthService
{
    public const STATUS_OK = 'ok';

    public const STATUS_SHORTFALL = 'shortfall';

    public const STATUS_DUE = 'due';

    public const STATUS_OVERDUE = 'overdue';

    public const TOLERANCE = 0.01;

    public static array $statusLabels = [
        self::STATUS_OK => 'Zgadza się',
        self::STATUS_SHORTFALL => 'Brakuje',
        self::STATUS_DUE => 'W terminie',
        self::STATUS_OVERDUE => 'Po terminie',
    ];

    public static array $statusColors = [
        self::STATUS_OK => ['#dcfce7', '#166534'],
        self::STATUS_SHORTFALL => ['#fee2e2', '#991b1b'],
        self::STATUS_DUE => ['#dbeafe', '#1e40af'],
        self::STATUS_OVERDUE => ['#ffedd5', '#9a3412'],
    ];

    /**
     * @return array{
     *     coverage_status: string,
     *     coverage_label: string,
     *     planned_pln: float,
     *     paid_pln: float,
     *     remaining_pln: float,
     *     next_due_date: ?Carbon,
     *     paid_by: string,
     *     cost_id: int,
     *     name: ?string,
     *     source_type: ?string
     * }
     */
    public function evaluatePlanCost(EventSettlementCost $planCost, Collection $allCosts): array
    {
        $paidPln = $this->paidPlnForPlanCost($planCost, $allCosts);
        $plannedPln = $this->plannedPlnForCost($planCost);
        $remainingPln = max(0, round($plannedPln - $paidPln, 2));
        $nextDue = $this->nextDueDate($planCost, $allCosts);
        $coverageStatus = $this->resolveStatus($paidPln, $plannedPln, $nextDue);

        return [
            'coverage_status' => $coverageStatus,
            'coverage_label' => self::$statusLabels[$coverageStatus] ?? $coverageStatus,
            'planned_pln' => $plannedPln,
            'paid_pln' => $paidPln,
            'remaining_pln' => $remainingPln,
            'next_due_date' => $nextDue,
            'paid_by' => (string) ($planCost->paid_by ?? 'office'),
            'cost_id' => (int) $planCost->id,
            'name' => $planCost->name,
            'source_type' => $planCost->source_type,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function evaluateSettlement(EventSettlement $settlement): Collection
    {
        $allCosts = $settlement->relationLoaded('costs')
            ? $settlement->costs
            : $settlement->costs()->get();

        return $this->listPlanCosts($allCosts)
            ->map(fn (EventSettlementCost $cost): array => $this->evaluatePlanCost($cost, $allCosts))
            ->values();
    }

    /**
     * @return Collection<int, EventSettlementCost>
     */
    public function listPlanCosts(Collection $allCosts): Collection
    {
        return $allCosts
            ->filter(fn (EventSettlementCost $cost): bool => $this->isEvaluablePlanCost($cost))
            ->values();
    }

    public function isEvaluablePlanCost(EventSettlementCost $cost): bool
    {
        if ($cost->payment_status === 'cancelled') {
            return false;
        }

        if (EventSettlementCost::isPaymentSourceType($cost->source_type)) {
            return false;
        }

        if ($cost->source_type === 'manual') {
            return ! EventSettlementCost::isManualPaymentRow($cost);
        }

        return in_array($cost->source_type, ['program_point', 'transport', 'accommodation', 'insurance_day'], true);
    }

    public function paidPlnForPlanCost(EventSettlementCost $planCost, Collection $allCosts): float
    {
        return round((float) $this->paymentRowsForPlanCost($planCost, $allCosts)
            ->where('payment_status', '!=', 'cancelled')
            ->sum(fn (EventSettlementCost $row): float => (float) ($row->actual_amount_pln ?? 0)), 2);
    }

    public function plannedPlnForCost(EventSettlementCost $planCost): float
    {
        if ($planCost->planned_amount_pln !== null) {
            return round((float) $planCost->planned_amount_pln, 2);
        }

        return round((float) ($planCost->resolvePlannedAmountPln() ?? 0), 2);
    }

    /**
     * @return Collection<int, EventSettlementCost>
     */
    public function paymentRowsForPlanCost(EventSettlementCost $planCost, Collection $allCosts): Collection
    {
        if ($planCost->source_type === 'program_point') {
            return $allCosts->filter(
                fn (EventSettlementCost $row): bool => $row->source_type === 'program_point_payment'
                    && (int) $row->source_id === (int) $planCost->source_id,
            )->values();
        }

        if (in_array($planCost->source_type, ['transport', 'accommodation'], true)) {
            $paymentType = $planCost->source_type.'_payment';

            return $allCosts->filter(
                fn (EventSettlementCost $row): bool => $row->source_type === $paymentType
                    && $row->source_id === null,
            )->values();
        }

        if ($planCost->source_type === 'manual') {
            return filled($planCost->actual_amount_pln)
                ? collect([$planCost])
                : collect();
        }

        if (filled($planCost->actual_amount_pln)) {
            return collect([$planCost]);
        }

        return collect();
    }

    public function resolveStatus(float $paidPln, float $plannedPln, ?Carbon $nextDue): string
    {
        if ($plannedPln <= self::TOLERANCE) {
            return self::STATUS_OK;
        }

        if ($paidPln >= $plannedPln - self::TOLERANCE) {
            return self::STATUS_OK;
        }

        if ($nextDue === null) {
            return self::STATUS_SHORTFALL;
        }

        if ($nextDue->endOfDay()->isFuture() || $nextDue->isToday()) {
            return self::STATUS_DUE;
        }

        return self::STATUS_OVERDUE;
    }

    public function nextDueDate(EventSettlementCost $planCost, Collection $allCosts): ?Carbon
    {
        $dates = $this->paymentRowsForPlanCost($planCost, $allCosts)
            ->filter(fn (EventSettlementCost $row): bool => filled($row->advance_due_date))
            ->pluck('advance_due_date');

        if (filled($planCost->advance_due_date)
            && $this->paidPlnForPlanCost($planCost, $allCosts) < $this->plannedPlnForCost($planCost) - self::TOLERANCE) {
            $dates = $dates->push($planCost->advance_due_date);
        }

        $sorted = $dates
            ->filter()
            ->map(fn ($date) => $date instanceof Carbon ? $date : Carbon::parse($date))
            ->sortBy(fn (Carbon $date) => $date->timestamp)
            ->values();

        return $sorted->first();
    }

    public function statusBadgeHtml(string $status): string
    {
        [$bg, $fg] = self::$statusColors[$status] ?? ['#f3f4f6', '#374151'];
        $label = htmlspecialchars(self::$statusLabels[$status] ?? $status);

        return "<span class='admin-table-pill' style='background:{$bg};color:{$fg}'>{$label}</span>";
    }

    /**
     * @return array<string, int>
     */
    public function countByStatus(Collection $evaluations): array
    {
        $counts = [
            self::STATUS_OK => 0,
            self::STATUS_DUE => 0,
            self::STATUS_OVERDUE => 0,
            self::STATUS_SHORTFALL => 0,
        ];

        foreach ($evaluations as $row) {
            $status = (string) ($row['coverage_status'] ?? self::STATUS_SHORTFALL);
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }

        return $counts;
    }
}
