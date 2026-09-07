<?php

namespace App\Services;

use App\Models\Currency;
use App\Support\CurrencyAmountDisplay;
use Filament\Forms;
use Illuminate\Support\HtmlString;

final class SettlementFinanceFormSupport
{
    public static function formatAmountLabel(float $amount, mixed $currencyId, bool $convertToPln): string
    {
        return CurrencyAmountDisplay::format($amount, Currency::find($currencyId), $convertToPln);
    }

    /**
     * @param  array{
     *     planned_label: string,
     *     paid_label: string,
     *     advance_label: string,
     *     remaining_label: string
     * }  $summary
     */
    public static function notificationBody(array $summary): string
    {
        return sprintf(
            'Planowane: %s • Zapłacono: %s • Zaliczki: %s • Pozostało: %s',
            $summary['planned_label'],
            $summary['paid_label'],
            $summary['advance_label'],
            $summary['remaining_label'],
        );
    }

    public static function paymentsSummaryHtml(Forms\Get $get): HtmlString
    {
        $currency = Currency::find($get('settlement_planned_currency_id'));
        $convertToPln = (bool) ($get('settlement_planned_convert_to_pln') ?? true);
        $plannedAmount = (float) ($get('settlement_planned_amount') ?? $get('event_point_total') ?? 0);
        $plannedPln = $get('settlement_planned_amount_pln');
        $entries = collect($get('payment_entries') ?? []);

        $advanceAmount = (float) ($get('settlement_advance_amount') ?? 0);
        $advancePaidPln = (float) ($get('settlement_advance_paid_amount_pln') ?? 0);
        $advancePaidForeign = (float) ($get('settlement_advance_paid_amount') ?? 0);

        $paidPln = $entries
            ->sum(fn (array $row) => (float) ($row['actual_amount_pln'] ?? 0));
        $paidPln += $advancePaidPln;

        $paidForeign = $entries
            ->sum(fn (array $row) => (float) ($row['actual_amount'] ?? 0));
        $paidForeign += $advancePaidForeign;

        $remainingForeign = max(0, $plannedAmount - $paidForeign);
        $remainingPln = $plannedPln !== null && $plannedPln !== ''
            ? max(0, (float) $plannedPln - $paidPln)
            : null;

        $officePaid = $entries->where('paid_by', 'office')->sum(fn (array $row) => (float) ($row['actual_amount_pln'] ?? 0));
        $pilotPaid = $entries->where('paid_by', 'pilot')->sum(fn (array $row) => (float) ($row['actual_amount_pln'] ?? 0));
        $officePaid += ($get('settlement_advance_paid_by') ?? $get('settlement_paid_by')) === 'office' ? $advancePaidPln : 0;
        $pilotPaid += ($get('settlement_advance_paid_by') ?? $get('settlement_paid_by')) === 'pilot' ? $advancePaidPln : 0;

        $statusLabel = 'Planowana';
        $isFullyPaid = $remainingPln !== null
            ? ($plannedPln > 0 && $paidPln >= (float) $plannedPln)
            : ($plannedAmount > 0 && $paidForeign >= $plannedAmount);

        if ($isFullyPaid) {
            $statusLabel = 'Opłacona w całości';
        } elseif ($paidPln > 0 || $paidForeign > 0) {
            $statusLabel = 'Częściowo opłacona';
        }

        $fmt = fn (float $amount) => htmlspecialchars(CurrencyAmountDisplay::format($amount, $currency, $convertToPln));
        $fmtPln = fn (?float $amount) => $amount === null
            ? 'bez przeliczenia PLN'
            : htmlspecialchars(number_format($amount, 2, ',', ' ').' PLN');

        $html = '<div class="space-y-2 text-sm">'
            .'<div><strong>Planowane:</strong> '.$fmt($plannedAmount).'</div>'
            .'<div><strong>Zapłacono łącznie:</strong> '.$fmt($paidForeign > 0 ? $paidForeign : $paidPln).' <span class="text-gray-500">(biuro: '.$fmtPln($officePaid > 0 ? $officePaid : null).', pilot: '.$fmtPln($pilotPaid > 0 ? $pilotPaid : null).')</span></div>'
            .'<div><strong>Zaliczka:</strong> '.$fmt($advanceAmount).' <span class="text-gray-500">(zapłacono: '.$fmt($advancePaidForeign > 0 ? $advancePaidForeign : $advancePaidPln).')</span></div>'
            .'<div><strong>Pozostało do zapłaty:</strong> '.$fmt($remainingForeign).($remainingPln !== null ? ' <span class="text-gray-500">('.$fmtPln($remainingPln).')</span>' : '').'</div>'
            .'<div><strong>Status (szacunek):</strong> '.htmlspecialchars($statusLabel).'</div>'
            .'</div>';

        return new HtmlString($html);
    }
}
