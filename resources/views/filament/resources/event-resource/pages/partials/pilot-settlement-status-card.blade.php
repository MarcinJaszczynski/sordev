@php
    /** @var array<string, mixed> $summary */
    $statusLabel = (string) ($summary['settlement_status_label'] ?? '—');
    $isClosed = (bool) ($summary['settlement_status_is_closed'] ?? false);
    $isSubmitted = (bool) ($summary['settlement_status_is_submitted'] ?? false);
    $feeDue = (string) ($summary['fee_due_label'] ?? '—');
    $feeRemaining = (string) ($summary['fee_remaining_label'] ?? '—');
    $feeDanger = (bool) ($summary['fee_remaining_danger'] ?? false);
    $returnLabel = (string) ($summary['return_label'] ?? '—');
    $returnDanger = (bool) ($summary['return_danger'] ?? false);
    $payPilotLabel = (string) ($summary['pay_pilot_label'] ?? '—');
    $payPilotDanger = (bool) ($summary['pay_pilot_danger'] ?? false);
    $formLabel = (string) ($summary['pilot_settlement_form_label'] ?? '');
@endphp

<div class="pilot-card pilot-card--tight">
    <div class="pilot-card-header">
        <div>
            <p class="pilot-card-title">Rozliczenie — obowiązek biura</p>
            <p class="pilot-card-meta">Gotówka operacyjna i wynagrodzenie. Pilot zgłasza, biuro potwierdza.</p>
        </div>
        <span @class([
            'pilot-settlement-badge',
            'pilot-settlement-badge--closed' => $isClosed,
            'pilot-settlement-badge--submitted' => $isSubmitted && ! $isClosed,
        ])>{{ $statusLabel }}</span>
    </div>

    <div class="pilot-settlement-grid">
        <div class="pilot-settlement-cell">
            <p class="pilot-stat-label">Gotówka — do zwrotu</p>
            <p @class(['pilot-stat-value', 'text-danger' => $returnDanger])>{{ $returnLabel }}</p>
        </div>
        <div class="pilot-settlement-cell">
            <p class="pilot-stat-label">Gotówka — do dopłaty</p>
            <p @class(['pilot-stat-value', 'text-danger' => $payPilotDanger])>{{ $payPilotLabel }}</p>
        </div>
        <div class="pilot-settlement-cell">
            <p class="pilot-stat-label">Wynagrodzenie — należne{{ $formLabel !== '' ? ' ('.$formLabel.')' : '' }}</p>
            <p class="pilot-stat-value">{{ $feeDue }}</p>
        </div>
        <div class="pilot-settlement-cell">
            <p class="pilot-stat-label">Wynagrodzenie — pozostało</p>
            <p @class(['pilot-stat-value', 'text-danger' => $feeDanger])>{{ $feeRemaining }}</p>
        </div>
    </div>
</div>
