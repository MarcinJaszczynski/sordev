<?php

namespace App\Support;

use App\Models\Event;

final class EventListFinanceColumn
{
    public static function resolveCurrencyCode(mixed $livewire): string
    {
        if (! is_object($livewire) || ! property_exists($livewire, 'tableFilters')) {
            return 'PLN';
        }

        $code = data_get($livewire->tableFilters, 'finance_display_currency.code');

        if (! is_string($code) || $code === '') {
            return 'PLN';
        }

        return strtoupper($code);
    }

    public static function html(Event $record, string $currencyCode = 'PLN'): string
    {
        $currencyCode = strtoupper($currencyCode);
        $fmt = fn ($v) => e(MoneyFormatter::format($v, $currencyCode));

        $participantCount = max(1, (int) ($record->participant_count ?? 1));
        $dueAmount = (float) ($record->total_cost ?? 0);
        $pricePerPerson = $dueAmount > 0
            ? round($dueAmount / $participantCount, 2)
            : 0.0;

        $paidAmount = self::resolvePaidAmount($record, $currencyCode);

        $totalCount = (int) ($record->participant_count ?? 0);
        $paidCount = (int) ($record->paid_participants_count ?? 0);
        $brakuje = max(0.0, $dueAmount - $paidAmount);

        $dayInsuranceCount = (int) ($record->day_insurances_count ?? -1);
        $insuranceRequired = $dayInsuranceCount >= 0
            ? $dayInsuranceCount > 0
            : $record->requiresInsuranceWorkflow();
        $insuranceReady = $record->isInsuranceCompleted();
        $insuranceColor = $insuranceRequired
            ? ($insuranceReady ? '#047857' : '#dc2626')
            : '#6b7280';
        $insuranceLabel = $insuranceRequired
            ? ($insuranceReady ? 'OK' : 'Do zrobienia')
            : 'Brak wymogu';

        $row = fn (string $label, string $value, string $vColor = '#111827') => '<tr>'
            .'<td style="padding:1px 8px 1px 0;color:#9ca3af;font-size:0.72rem;white-space:nowrap">'.$label.'</td>'
            .'<td style="color:'.$vColor.';font-size:0.78rem;font-weight:600;white-space:nowrap">'.$value.'</td>'
            .'</tr>';

        $paidDisplay = $fmt($paidAmount);
        if ($totalCount > 0) {
            $paidDisplay .= ' <span style="color:#9ca3af;font-weight:400;font-size:0.7rem">('
                .$paidCount.'/'.$totalCount.')</span>';
        }

        $brakujeColor = $brakuje > 0.001 ? '#dc2626' : '#047857';

        $vendorHealth = self::resolveVendorCostHealth($record);
        $vendorRow = '';
        if ($vendorHealth !== null) {
            [$bg, $fg] = \App\Services\SettlementPaymentHealthService::$statusColors[$vendorHealth['status']] ?? ['#f3f4f6', '#374151'];
            $vendorLabel = e($vendorHealth['label']);
            $vendorRemaining = e(MoneyFormatter::format($vendorHealth['remaining_pln'], 'PLN'));
            $vendorRow = '<tr>'
                .'<td style="padding:1px 8px 1px 0;color:#9ca3af;font-size:0.72rem;white-space:nowrap">Koszty wykonawców:</td>'
                .'<td style="font-size:0.78rem;font-weight:600;white-space:nowrap">'
                .'<span style="color:'.$fg.'">'.$vendorLabel.'</span>'
                .($vendorHealth['remaining_pln'] > 0 ? ' <span style="color:#9ca3af;font-weight:400">('.$vendorRemaining.')</span>' : '')
                .'</td></tr>';
        }

        return '<table style="border-collapse:collapse" title="Szczegóły kalkulacji na karcie finansów imprezy">'
            .$row('Klient:', e($record->client_name ?: '—'))
            .$row('Do zapłaty (łącznie):', $fmt($dueAmount))
            .$row('Cena za os.:', e(MoneyFormatter::format($pricePerPerson, $currencyCode)), '#1f2937')
            .'<tr>'
            .'<td style="padding:1px 8px 1px 0;color:#9ca3af;font-size:0.72rem;white-space:nowrap">Zapłacono:</td>'
            .'<td style="color:#047857;font-size:0.78rem;font-weight:600;white-space:nowrap">'.$paidDisplay.'</td>'
            .'</tr>'
            .$row('Brakuje:', $fmt($brakuje), $brakujeColor)
            .$vendorRow
            .$row('Ubezpieczenie:', e($insuranceLabel), $insuranceColor)
            .'</table>';
    }

    /**
     * @return array{status: string, label: string, remaining_pln: float}|null
     */
    private static function resolveVendorCostHealth(Event $record): ?array
    {
        try {
            $settlement = $record->relationLoaded('activeSettlement')
                ? $record->activeSettlement
                : $record->activeSettlement()->first();

            return app(\App\Services\SettlementPayerBreakdownService::class)->miniSummaryForSettlement($settlement);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function resolvePaidAmount(Event $record, string $currencyCode): float
    {
        try {
            if ($currencyCode === 'PLN') {
                if ($record->agreements_amount_paid_total !== null) {
                    return (float) $record->agreements_amount_paid_total;
                }

                return (float) $record->agreements()->sum('amount_paid');
            }

            if ($record->agreements_amount_paid_filtered !== null) {
                return (float) $record->agreements_amount_paid_filtered;
            }

            return (float) $record->agreements()
                ->whereRaw('UPPER(currency) = ?', [$currencyCode])
                ->sum('amount_paid');
        } catch (\Throwable) {
            return 0.0;
        }
    }
}
