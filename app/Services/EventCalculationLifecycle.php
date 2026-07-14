<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventCalculationStage;
use App\Models\EventSettlement;

/**
 * Read-model cyklu życia kalkulacji imprezy.
 *
 * Spina istniejące dane w cztery etapy:
 *  1. Wstępna     — z szablonu / oferty (resolvedBaseTotalCost / resolvedFullTotalCost),
 *  2. Przewidywana — ustalenia z podwykonawcami i klientem (rozliczenie: planned_cost / participant_due),
 *  3. Rzeczywista  — faktyczne koszty i wpłaty (rozliczenie: actual_cost / participant_paid),
 *  4. Rozliczenie  — porównanie planu z wykonaniem z uwzględnieniem rezygnacji.
 *
 * Marża = przychód od klienta − koszt podwykonawców (marża brutto).
 */
class EventCalculationLifecycle
{
    public function __construct(private readonly Event $event) {}

    public static function for(Event $event): self
    {
        return new self($event);
    }

    /**
     * @return array{
     *   participant_count: int,
     *   stages: array<string, array<string, mixed>>,
     *   comparison: array<int, array<string, mixed>>,
     *   reconciliation: array<string, mixed>,
     *   resignations: array<string, mixed>,
     * }
     */
    public function build(): array
    {
        $event = $this->event;
        $count = max(1, (int) ($event->participant_count ?? 1));

        $event->loadMissing('calculationStages');
        $preliminaryLock = $event->calculationStage(EventCalculationStage::STAGE_PRELIMINARY);
        $predictedLock = $event->calculationStage(EventCalculationStage::STAGE_PREDICTED);

        // Wartości wyliczone z jednego autorytatywnego kalkulatora (każdy koszt raz).
        $calc = $this->safeCalculation($count);
        $preliminaryCostCalc = (float) ($calc['base_pln'] ?? 0.0);
        $preliminaryRevenueCalc = (float) ($calc['total_pln'] ?? 0.0);

        /** @var EventSettlement|null $settlement */
        $settlement = $event->activeSettlement()->first();
        $hasSettlement = $settlement !== null;

        $predictedCostCalc = $hasSettlement && $settlement->planned_cost_pln !== null
            ? (float) $settlement->planned_cost_pln
            : $preliminaryCostCalc;

        $predictedRevenueCalc = $hasSettlement && (float) $settlement->participant_due_pln > 0
            ? (float) $settlement->participant_due_pln
            : $preliminaryRevenueCalc;

        // Etap wstępny — ręczne zamrożenie ma priorytet nad wyliczeniem.
        $preliminaryCost = $this->resolveLocked($preliminaryLock?->cost_pln, $preliminaryCostCalc);
        $preliminaryRevenue = $this->resolveLocked($preliminaryLock?->client_price_pln, $preliminaryRevenueCalc);

        // Etap przewidywany.
        $predictedCost = $this->resolveLocked($predictedLock?->cost_pln, $predictedCostCalc);
        $predictedRevenue = $this->resolveLocked($predictedLock?->client_price_pln, $predictedRevenueCalc);

        $actualCost = $hasSettlement ? (float) $settlement->actual_cost_pln : 0.0;
        $actualRevenue = $hasSettlement ? (float) $settlement->participant_paid_pln : 0.0;

        $stages = [
            'preliminary' => $this->stage(
                key: 'preliminary',
                label: 'Wstępna',
                icon: 'heroicon-o-document-text',
                source: 'Na podstawie szablonu / oferty',
                cost: $preliminaryCost,
                revenue: $preliminaryRevenue,
                count: $count,
                complete: $preliminaryCost > 0 || $preliminaryRevenue > 0,
                editable: true,
                lock: $preliminaryLock,
            ),
            'predicted' => $this->stage(
                key: 'predicted',
                label: 'Przewidywana',
                icon: 'heroicon-o-document-check',
                source: 'Ustalenia z podwykonawcami i klientem',
                cost: $predictedCost,
                revenue: $predictedRevenue,
                count: $count,
                complete: ($hasSettlement && ((float) $settlement->planned_cost_pln > 0)) || $predictedLock !== null,
                editable: true,
                lock: $predictedLock,
            ),
            'actual' => $this->stage(
                key: 'actual',
                label: 'Rzeczywista',
                icon: 'heroicon-o-banknotes',
                source: 'Faktyczne koszty i wpłaty',
                cost: $actualCost,
                revenue: $actualRevenue,
                count: $count,
                complete: $hasSettlement && ($actualCost > 0 || $actualRevenue > 0),
                editable: false,
                lock: null,
            ),
        ];

        $resignations = $this->resignations();

        $reconciliation = [
            'has_settlement' => $hasSettlement,
            'settlement_id' => $settlement?->id,
            'settlement_status' => $settlement?->status,
            'settlement_status_label' => $settlement ? ($settlement->status_label ?? $settlement->status) : null,
            'is_closed' => $settlement?->status === 'closed',
            'cost_delta_pln' => round($actualCost - $predictedCost, 2),
            'revenue_delta_pln' => round($actualRevenue - $predictedRevenue, 2),
            'margin_delta_vs_preliminary_pln' => round($stages['actual']['margin_pln'] - $stages['preliminary']['margin_pln'], 2),
            'net_result_pln' => $hasSettlement
                ? (float) $settlement->net_result_pln
                : round($actualRevenue - $actualCost, 2),
            'receivable_pln' => round(max(0.0, $predictedRevenue - $actualRevenue), 2),
            'outstanding_cost_pln' => round(max(0.0, $predictedCost - $actualCost), 2),
        ];

        return [
            'participant_count' => $count,
            'stages' => $stages,
            'comparison' => $this->comparison($stages),
            'reconciliation' => $reconciliation,
            'resignations' => $resignations,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stage(
        string $key,
        string $label,
        string $icon,
        string $source,
        float $cost,
        float $revenue,
        int $count,
        bool $complete,
        bool $editable = false,
        ?EventCalculationStage $lock = null,
    ): array {
        $cost = round($cost, 2);
        $revenue = round($revenue, 2);
        $margin = round($revenue - $cost, 2);
        $marginPercent = $revenue > 0 ? round(($margin / $revenue) * 100, 1) : null;

        return [
            'key' => $key,
            'label' => $label,
            'icon' => $icon,
            'source' => $source,
            'complete' => $complete,
            'cost_pln' => $cost,
            'revenue_pln' => $revenue,
            'margin_pln' => $margin,
            'margin_percent' => $marginPercent,
            'cost_per_person_pln' => $count > 0 ? round($cost / $count, 2) : 0.0,
            'revenue_per_person_pln' => $count > 0 ? round($revenue / $count, 2) : 0.0,
            'margin_tone' => $margin > 0 ? 'success' : ($margin < 0 ? 'danger' : 'gray'),
            'editable' => $editable,
            'revenue_is_manual' => $lock !== null && $lock->client_price_pln !== null,
            'cost_is_manual' => $lock !== null && $lock->cost_pln !== null,
            'locked_note' => $lock?->note,
            'locked_at' => $lock?->locked_at,
        ];
    }

    private function resolveLocked(mixed $locked, float $calculated): float
    {
        return $locked !== null ? round((float) $locked, 2) : $calculated;
    }

    /**
     * Wiersze tabeli porównawczej: koszt, przychód, marża, marża %.
     *
     * @param  array<string, array<string, mixed>>  $stages
     * @return array<int, array<string, mixed>>
     */
    private function comparison(array $stages): array
    {
        $rows = [
            ['key' => 'cost_pln', 'label' => 'Koszt (podwykonawcy)', 'type' => 'money'],
            ['key' => 'revenue_pln', 'label' => 'Przychód (klient)', 'type' => 'money'],
            ['key' => 'margin_pln', 'label' => 'Marża', 'type' => 'money'],
            ['key' => 'margin_percent', 'label' => 'Marża %', 'type' => 'percent'],
        ];

        return array_map(function (array $row) use ($stages): array {
            $row['values'] = [
                'preliminary' => $stages['preliminary'][$row['key']] ?? null,
                'predicted' => $stages['predicted'][$row['key']] ?? null,
                'actual' => $stages['actual'][$row['key']] ?? null,
            ];

            return $row;
        }, $rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function resignations(): array
    {
        $items = $this->event->participantResignations()
            ->whereIn('status', ['confirmed', 'settled'])
            ->get();

        return [
            'count' => $items->count(),
            'refund_pln' => round((float) $items->sum('refund_amount_pln'), 2),
            'retention_pln' => round((float) $items->sum('retention_amount_pln'), 2),
            'paid_pln' => round((float) $items->sum('amount_paid_pln'), 2),
            'has_any' => $items->isNotEmpty(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function safeCalculation(int $count): array
    {
        try {
            return EventCostCalculator::for($this->event)->calculate($count);
        } catch (\Throwable) {
            return [];
        }
    }
}
