@php
    /** @var array<string, mixed> $summary */
    $plan = (string) ($summary['plan_label'] ?? '—');
    $paid = (string) ($summary['paid_label'] ?? '—');
    $spent = (string) ($summary['spent_label'] ?? '—');
    $toReturn = (string) ($summary['return_label'] ?? '—');
    $returnDanger = (bool) ($summary['return_danger'] ?? false);
@endphp

<div class="pilot-card pilot-card--tight">
    <div class="pilot-card-header">
        <p class="pilot-card-title">Przepływ gotówki</p>
        <span class="pilot-card-meta">Podgląd — edycja w planie i rozliczeniu</span>
    </div>

    <div class="pilot-cash-flow" aria-label="Przepływ gotówki pilota">
        <div class="pilot-cash-flow-step">
            <p class="pilot-cash-flow-label">Plan</p>
            <p class="pilot-cash-flow-value">{{ $plan }}</p>
        </div>
        <div class="pilot-cash-flow-step">
            <p class="pilot-cash-flow-label">→ Wypłacono</p>
            <p class="pilot-cash-flow-value">{{ $paid }}</p>
        </div>
        <div class="pilot-cash-flow-step">
            <p class="pilot-cash-flow-label">→ Wydano</p>
            <p class="pilot-cash-flow-value">{{ $spent }}</p>
        </div>
        <div @class(['pilot-cash-flow-step', 'pilot-cash-flow-step--danger' => $returnDanger])>
            <p class="pilot-cash-flow-label">→ Do zwrotu</p>
            <p class="pilot-cash-flow-value">{{ $toReturn }}</p>
        </div>
    </div>
</div>
