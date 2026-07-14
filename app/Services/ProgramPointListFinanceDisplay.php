<?php

namespace App\Services;

use App\Models\EventProgramPoint;
use App\Models\EventSettlementCost;
use App\Support\CurrencyAmountDisplay;
use App\Support\EventProgramPointPricesSummary;

final class ProgramPointListFinanceDisplay
{
    /**
     * @return array{
     *     calc: string,
     *     planned: string,
     *     paid: string,
     *     paidStatus: string,
     *     advanceHtml: string|null,
     *     isSetRollup: bool,
     * }
     */
    public function summarizePoint(
        EventProgramPoint $record,
        ProgramPointSettlementCostCache $costCache,
        ?int $participantCount = null,
    ): array {
        $participantCount = max(1, (int) ($participantCount ?? $record->event?->participant_count ?? 1));
        $baseCost = $costCache->baseCost((int) $record->id);
        $paymentRows = $costCache->paymentRows((int) $record->id);
        $plannedCurrency = $baseCost?->plannedCurrency ?? $record->currency;
        $convertToPln = (bool) ($baseCost?->planned_convert_to_pln ?? $record->convert_to_pln ?? true);

        $paidPln = (float) $paymentRows->sum(function (EventSettlementCost $row) {
            if ($row->actual_amount_pln !== null) {
                return (float) $row->actual_amount_pln;
            }

            return (float) ($row->actual_amount ?? 0) * (float) ($row->actual_rate ?? 1);
        });

        if ($paidPln <= 0 && $baseCost) {
            $paidPln = (float) ($baseCost->actual_amount_pln ?? 0);
        }

        $paidForeign = (float) $paymentRows->sum(fn (EventSettlementCost $row) => (float) ($row->actual_amount ?? 0));

        if ($paidForeign <= 0 && $baseCost) {
            $paidForeign = (float) ($baseCost->actual_amount ?? 0);
        }

        $paidAmountRaw = $paidForeign > 0 ? $paidForeign : $paidPln;
        $plannedAmountRaw = (float) ($baseCost?->planned_amount ?? $record->planned_price ?? 0);

        $paidFormatted = ($paidPln > 0 || $paidForeign > 0 || $baseCost || $paymentRows->isNotEmpty())
            ? CurrencyAmountDisplay::format($paidAmountRaw, $plannedCurrency, $convertToPln)
            : $record->formatAmount((float) ($record->paid_price ?? 0));

        if ($paidFormatted === '—' && (float) ($record->paid_price ?? 0) > 0) {
            $paidAmountRaw = (float) ($record->paid_price ?? 0);
            $paidFormatted = $record->formatAmount($paidAmountRaw);
        }

        $plannedFormatted = ($baseCost || $paymentRows->isNotEmpty())
            ? CurrencyAmountDisplay::format($plannedAmountRaw, $plannedCurrency, $convertToPln)
            : $record->formatAmount((float) ($record->planned_price ?? 0));

        if ($plannedFormatted === '—' && (float) ($record->planned_price ?? 0) > 0) {
            $plannedFormatted = $record->formatAmount((float) ($record->planned_price ?? 0));
        }

        $advanceHtml = null;
        $advanceRows = $paymentRows->filter(fn (EventSettlementCost $row): bool => (float) ($row->advance_amount ?? 0) > 0
            || in_array((string) $row->advance_type, ['advance', 'deposit'], true));
        $advanceAmount = (float) $advanceRows->sum(fn (EventSettlementCost $row) => (float) ($row->advance_amount ?? 0));

        if ($advanceAmount <= 0 && $baseCost) {
            $advanceAmount = (float) ($baseCost->advance_amount ?? 0);
        }

        if ($advanceAmount > 0) {
            $advanceLabel = $advanceRows->count() > 1 ? 'Zaliczki' : 'Zaliczka';
            $advAmount = e(CurrencyAmountDisplay::format($advanceAmount, $plannedCurrency, $convertToPln));
            $advPaid = e(CurrencyAmountDisplay::format($paidAmountRaw, $plannedCurrency, $convertToPln));
            $remainingAmount = max(0, $plannedAmountRaw - $paidAmountRaw);
            $totalToPay = e(CurrencyAmountDisplay::format($remainingAmount, $plannedCurrency, $convertToPln));
            $advanceHtml = "💰 {$advanceLabel} {$advAmount} · wpł. {$advPaid} · do dop. {$totalToPay}";
        }

        return [
            'calc' => $record->formatAmount($record->resolveCalculationTotal($participantCount)),
            'planned' => $plannedFormatted,
            'paid' => $paidFormatted,
            'paidStatus' => EventProgramPointPricesSummary::resolvePaidStatus($paidAmountRaw, $plannedAmountRaw),
            'advanceHtml' => $advanceHtml,
            'isSetRollup' => false,
        ];
    }
}
