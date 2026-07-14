<?php

namespace App\Services;

use App\Models\EventSettlement;
use App\Support\MoneyFormatter;
use Illuminate\Support\Collection;

final class SettlementPayerBreakdownService
{
    public function __construct(
        private SettlementPaymentHealthService $health,
    ) {}

    /**
     * @return array{
     *     counts: array<string, int>,
     *     office: array<string, mixed>,
     *     pilot: array<string, mixed>,
     *     total: array<string, mixed>,
     *     attention_items: list<array<string, mixed>>
     * }
     */
    public function forSettlement(EventSettlement $settlement): array
    {
        $evaluations = $this->health->evaluateSettlement($settlement);
        $counts = $this->health->countByStatus($evaluations);

        $office = $this->emptyPayerBucket();
        $pilot = $this->emptyPayerBucket();
        $total = $this->emptyPayerBucket();

        foreach ($evaluations as $row) {
            $payer = ($row['paid_by'] ?? 'office') === 'pilot' ? 'pilot' : 'office';
            $planned = (float) ($row['planned_pln'] ?? 0);
            $paid = (float) ($row['paid_pln'] ?? 0);
            $remaining = (float) ($row['remaining_pln'] ?? 0);
            $status = (string) ($row['coverage_status'] ?? SettlementPaymentHealthService::STATUS_SHORTFALL);

            if ($payer === 'pilot') {
                $pilot['planned_pln'] += $planned;
                $pilot['paid_pln'] += $paid;
                $pilot['remaining_pln'] += $remaining;
                $pilot['counts'][$status] = ($pilot['counts'][$status] ?? 0) + 1;
            } else {
                $office['planned_pln'] += $planned;
                $office['paid_pln'] += $paid;
                $office['remaining_pln'] += $remaining;
                $office['counts'][$status] = ($office['counts'][$status] ?? 0) + 1;
            }

            $total['planned_pln'] += $planned;
            $total['paid_pln'] += $paid;
            $total['remaining_pln'] += $remaining;
            $total['counts'][$status] = ($total['counts'][$status] ?? 0) + 1;
        }

        foreach ([$office, $pilot, $total] as &$bucket) {
            $bucket['planned_pln'] = round($bucket['planned_pln'], 2);
            $bucket['paid_pln'] = round($bucket['paid_pln'], 2);
            $bucket['remaining_pln'] = round($bucket['remaining_pln'], 2);
            $bucket['planned_label'] = MoneyFormatter::format($bucket['planned_pln'], 'PLN');
            $bucket['paid_label'] = MoneyFormatter::format($bucket['paid_pln'], 'PLN');
            $bucket['remaining_label'] = MoneyFormatter::format($bucket['remaining_pln'], 'PLN');
        }
        unset($bucket);

        $attentionItems = $evaluations
            ->filter(fn (array $row): bool => in_array(
                (string) ($row['coverage_status'] ?? ''),
                [SettlementPaymentHealthService::STATUS_SHORTFALL, SettlementPaymentHealthService::STATUS_OVERDUE],
                true,
            ))
            ->values()
            ->all();

        return [
            'counts' => $counts,
            'office' => $office,
            'pilot' => $pilot,
            'total' => $total,
            'attention_items' => $attentionItems,
            'control_rows' => $evaluations->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayerBucket(): array
    {
        return [
            'planned_pln' => 0.0,
            'paid_pln' => 0.0,
            'remaining_pln' => 0.0,
            'counts' => [
                SettlementPaymentHealthService::STATUS_OK => 0,
                SettlementPaymentHealthService::STATUS_DUE => 0,
                SettlementPaymentHealthService::STATUS_OVERDUE => 0,
                SettlementPaymentHealthService::STATUS_SHORTFALL => 0,
            ],
        ];
    }

    /**
     * Mini-semafor kosztów wykonawców dla listy imprez.
     *
     * @return array{status: string, label: string, remaining_pln: float}|null
     */
    public function miniSummaryForSettlement(?EventSettlement $settlement): ?array
    {
        if (! $settlement) {
            return null;
        }

        $evaluations = $this->health->evaluateSettlement($settlement);

        if ($evaluations->isEmpty()) {
            return null;
        }

        $counts = $this->health->countByStatus($evaluations);
        $remaining = round((float) $evaluations->sum('remaining_pln'), 2);

        $status = SettlementPaymentHealthService::STATUS_OK;
        if (($counts[SettlementPaymentHealthService::STATUS_OVERDUE] ?? 0) > 0) {
            $status = SettlementPaymentHealthService::STATUS_OVERDUE;
        } elseif (($counts[SettlementPaymentHealthService::STATUS_SHORTFALL] ?? 0) > 0) {
            $status = SettlementPaymentHealthService::STATUS_SHORTFALL;
        } elseif (($counts[SettlementPaymentHealthService::STATUS_DUE] ?? 0) > 0) {
            $status = SettlementPaymentHealthService::STATUS_DUE;
        }

        return [
            'status' => $status,
            'label' => SettlementPaymentHealthService::$statusLabels[$status] ?? $status,
            'remaining_pln' => $remaining,
        ];
    }
}
