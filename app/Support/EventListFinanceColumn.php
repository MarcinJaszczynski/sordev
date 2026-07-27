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
        $paymentsColor = self::resolveClientPaymentsColor($record, $paidAmount, $dueAmount);
        $paymentsDisplay = $fmt($paidAmount).' / '.$fmt($dueAmount);

        $row = fn (string $label, string $value, string $vColor = '#111827') => '<tr>'
            .'<td style="padding:1px 8px 1px 0;color:#9ca3af;font-size:0.72rem;white-space:nowrap">'.$label.'</td>'
            .'<td style="color:'.$vColor.';font-size:0.78rem;font-weight:600;white-space:nowrap">'.$value.'</td>'
            .'</tr>';

        return '<table style="border-collapse:collapse" title="Szczegóły kalkulacji na karcie finansów imprezy">'
            .$row('Wpłaty klienta:', $paymentsDisplay, $paymentsColor)
            .$row('Cena za os.:', e(MoneyFormatter::format($pricePerPerson, $currencyCode)), '#1f2937')
            .'</table>';
    }

    private static function resolveClientPaymentsColor(Event $record, float $paidAmount, float $dueAmount): string
    {
        $tolerance = \App\Services\SettlementPaymentHealthService::TOLERANCE;

        if ($dueAmount <= $tolerance || $paidAmount >= $dueAmount - $tolerance) {
            return '#047857';
        }

        if (self::isClientPaymentsOverdue($record)) {
            return '#dc2626';
        }

        return '#2563eb';
    }

    private static function isClientPaymentsOverdue(Event $record): bool
    {
        try {
            $aggregate = app(\App\Services\ParticipantPaymentBalanceService::class)->eventAggregate($record);

            if (($aggregate['count'] ?? 0) > 0) {
                return ($aggregate['coverage_status'] ?? '') === \App\Services\SettlementPaymentHealthService::STATUS_OVERDUE;
            }
        } catch (\Throwable) {
            // fallback below
        }

        return false;
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
