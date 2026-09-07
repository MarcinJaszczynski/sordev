<?php

/** @var array<string, mixed> $summary */
$needed = (string) ($summary['needed_label'] ?? $summary['plan_label'] ?? '—');
$fundingSources = (string) ($summary['funding_sources_label'] ?? $needed);
$toPayFromPlan = (string) ($summary['cash_to_pay_label'] ?? '—');
$payFromPlanDanger = (bool) ($summary['cash_to_pay_danger'] ?? false);
$busCoversNeed = (bool) ($summary['bus_covers_need'] ?? false);
$payoutExchange = (string) ($summary['payout_exchange_label'] ?? '—');
$payoutNatura = (string) ($summary['payout_natura_label'] ?? '—');
$payoutExchangeDetail = $summary['payout_exchange_detail'] ?? null;
$busSurplus = $summary['bus_surplus_label'] ?? null;
$paid = (string) ($summary['paid_label'] ?? '—');
$exchange = (string) ($summary['exchange_label'] ?? '—');
$hasExchange = (bool) ($summary['has_exchanges'] ?? false);
$spent = (string) ($summary['spent_label'] ?? '—');
$toReturn = (string) ($summary['return_label'] ?? '—');
$returnDanger = (bool) ($summary['return_danger'] ?? false);
$toPayPilot = (string) ($summary['pay_pilot_label'] ?? '—');
$payPilotDanger = (bool) ($summary['pay_pilot_danger'] ?? false);
?>

<div class="pilot-card pilot-card--tight">
    <div class="pilot-card-header">
        <p class="pilot-card-title">Przepływ gotówki</p>
        <span class="pilot-card-meta">Podgląd — edycja w planie i rozliczeniu</span>
    </div>

    <div class="pilot-cash-flow" aria-label="Przepływ gotówki pilota">
        <div class="pilot-cash-flow-step">
            <p class="pilot-cash-flow-label">Potrzeba z programu</p>
            <p class="pilot-cash-flow-value">{{ $needed }}</p>
        </div>
        <div class="pilot-cash-flow-step">
            <p class="pilot-cash-flow-label">Skąd gotówka</p>
            <p class="pilot-cash-flow-value">{{ $fundingSources }}</p>
            @if ($busSurplus)
                <p class="pilot-cash-flow-hint">{{ $busSurplus }}</p>
            @endif
        </div>
        <div @class(['pilot-cash-flow-step', 'pilot-cash-flow-step--danger' => $payFromPlanDanger])>
            <p class="pilot-cash-flow-label">Wypłać z biura</p>
            @if ($busCoversNeed)
                <p class="pilot-cash-flow-value">{{ $toPayFromPlan }}</p>
            @else
                <p class="pilot-cash-flow-value">{{ $payoutExchange !== '—' ? $payoutExchange : $toPayFromPlan }}</p>
                @if ($payoutExchangeDetail)
                    <p class="pilot-cash-flow-hint">{{ $payoutExchangeDetail }}</p>
                @endif
                @if ($payoutNatura !== '—' && $payoutNatura !== $payoutExchange)
                    <p class="pilot-cash-flow-hint">albo wypłać waluty: {{ $payoutNatura }}</p>
                @endif
            @endif
        </div>
        <div class="pilot-cash-flow-step">
            <p class="pilot-cash-flow-label">→ Wypłacono</p>
            <p class="pilot-cash-flow-value">{{ $paid }}</p>
        </div>
        @if ($hasExchange || $exchange !== '—')
            <div class="pilot-cash-flow-step">
                <p class="pilot-cash-flow-label">→ Wymiana</p>
                <p class="pilot-cash-flow-value">{{ $exchange }}</p>
            </div>
        @endif
        <div class="pilot-cash-flow-step">
            <p class="pilot-cash-flow-label">→ Wydano</p>
            <p class="pilot-cash-flow-value">{{ $spent }}</p>
        </div>
        <div @class(['pilot-cash-flow-step', 'pilot-cash-flow-step--danger' => $returnDanger])>
            <p class="pilot-cash-flow-label">→ Do zwrotu</p>
            <p class="pilot-cash-flow-value">{{ $toReturn }}</p>
        </div>
        <div @class(['pilot-cash-flow-step', 'pilot-cash-flow-step--danger' => $payPilotDanger])>
            <p class="pilot-cash-flow-label">→ Do dopłaty</p>
            <p class="pilot-cash-flow-value">{{ $toPayPilot }}</p>
        </div>
    </div>
</div>
