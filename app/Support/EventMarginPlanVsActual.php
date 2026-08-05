<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Event;
use App\Models\EventSettlement;
use App\Services\EventCalculationLifecycle;
use App\Services\SettlementPaymentHealthService;

/**
 * Live panel marży plan vs real na imprezie.
 */
final class EventMarginPlanVsActual
{
    /**
     * @return array{
     *   planned_cost: float,
     *   actual_cost: float,
     *   planned_revenue: float,
     *   actual_revenue: float,
     *   planned_margin: float,
     *   actual_margin: float,
     *   margin_delta: float,
     *   margin_delta_percent: float|null,
     *   health_shortfalls: int,
     *   health_overdue: int,
     *   labels: array<string, string>
     * }
     */
    public static function forEvent(Event $event): array
    {
        $lifecycle = EventCalculationLifecycle::for($event)->build();
        $stages = $lifecycle['stages'] ?? [];

        $plannedCost = (float) ($stages['predicted']['cost_pln'] ?? $stages['preliminary']['cost_pln'] ?? 0);
        $plannedRevenue = (float) ($stages['predicted']['revenue_pln'] ?? $stages['preliminary']['revenue_pln'] ?? 0);
        $actualCost = (float) ($stages['actual']['cost_pln'] ?? 0);
        $actualRevenue = (float) ($stages['actual']['revenue_pln'] ?? 0);

        $plannedMargin = round($plannedRevenue - $plannedCost, 2);
        $actualMargin = round($actualRevenue - $actualCost, 2);
        $delta = round($actualMargin - $plannedMargin, 2);
        $deltaPercent = abs($plannedMargin) > 0.009
            ? round(($delta / $plannedMargin) * 100, 1)
            : null;

        $shortfalls = 0;
        $overdue = 0;
        /** @var EventSettlement|null $settlement */
        $settlement = $event->activeSettlement()->first();
        if ($settlement) {
            $health = app(SettlementPaymentHealthService::class)->evaluateSettlement($settlement);
            $shortfalls = $health->where('coverage_status', SettlementPaymentHealthService::STATUS_SHORTFALL)->count();
            $overdue = $health->where('coverage_status', SettlementPaymentHealthService::STATUS_OVERDUE)->count();
        }

        return [
            'planned_cost' => $plannedCost,
            'actual_cost' => $actualCost,
            'planned_revenue' => $plannedRevenue,
            'actual_revenue' => $actualRevenue,
            'planned_margin' => $plannedMargin,
            'actual_margin' => $actualMargin,
            'margin_delta' => $delta,
            'margin_delta_percent' => $deltaPercent,
            'health_shortfalls' => $shortfalls,
            'health_overdue' => $overdue,
            'labels' => [
                'planned_cost' => MoneyFormatter::format($plannedCost, 'PLN'),
                'actual_cost' => MoneyFormatter::format($actualCost, 'PLN'),
                'planned_revenue' => MoneyFormatter::format($plannedRevenue, 'PLN'),
                'actual_revenue' => MoneyFormatter::format($actualRevenue, 'PLN'),
                'planned_margin' => MoneyFormatter::format($plannedMargin, 'PLN'),
                'actual_margin' => MoneyFormatter::format($actualMargin, 'PLN'),
                'margin_delta' => MoneyFormatter::format($delta, 'PLN'),
            ],
        ];
    }
}
