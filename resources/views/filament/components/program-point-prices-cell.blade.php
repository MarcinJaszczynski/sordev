@php
    /** @var string $calc */
    /** @var string|null $calcSub */
    /** @var string $planned */
    /** @var string|null $plannedSub */
    /** @var string $paid */
    /** @var string|null $paidSub */
    /** @var string $paidStatus */
    /** @var string|null $advanceHtml */
    /** @var string|null $paymentHint */
    /** @var string|null $pilotDueHint */
    /** @var string|null $remainingHint */
    /** @var string|null $remaining */
    /** @var string|null $remainingSub */
    /** @var string|null $documentHint */
    /** @var string|null $documentStatusLabel */
    /** @var string|null $documentFirstUrl */
    /** @var string|null $statusLabel */
    /** @var string|null $statusColor */
    /** @var bool $hasUploadedFile */
    /** @var bool $planDiffersFromCalc */
    $paymentHint = $paymentHint ?? $advanceHtml ?? null;
    $remaining = $remaining ?? '—';
    $hasUploadedFile = $hasUploadedFile ?? false;
    $statusLabel = $statusLabel ?? null;
    $statusColor = $statusColor ?? 'gray';
    $planDiffersFromCalc = $planDiffersFromCalc ?? false;
    $payerHint = $payerHint ?? null;
    $documentFirstUrl = $documentFirstUrl ?? null;
    $calcSub = $calcSub ?? null;
    $plannedSub = $plannedSub ?? null;
    $paidSub = $paidSub ?? null;
    $remainingSub = $remainingSub ?? null;

    $money = static function (string $main, ?string $sub = null): string {
        $html = '<span class="epp-money"><span class="epp-money__main">'.e($main).'</span>';
        if (filled($sub)) {
            $html .= '<span class="epp-money__sub">'.e($sub).'</span>';
        }
        $html .= '</span>';

        return $html;
    };
@endphp

<div class="epp-prices-cell">
    @if (! empty($hideSetParentFinance))
        <span class="epp-prices-empty" style="color:#9ca3af">—</span>
    @else
        @if (! empty($isSetRollup))
            <div class="epp-prices-set-label" style="font-size:10px;color:#6b7280;margin-bottom:4px;font-weight:600">Σ set</div>
        @endif
        <div class="epp-prices-row">
            <span class="epp-prices-label">Kalkulacja</span>
            <span class="epp-prices-value">{!! $money($calc, $calcSub) !!}</span>
        </div>

        <div @class([
            'epp-prices-row',
            'epp-prices-row--plan-differs' => $planDiffersFromCalc,
        ])>
            <span class="epp-prices-label">Plan</span>
            <span class="epp-prices-value" @if($planDiffersFromCalc) title="Różni się od kalkulacji: {{ $calc }}{{ $calcSub ? ' '.$calcSub : '' }}" @endif>
                {!! $money($planned, $plannedSub) !!}
            </span>
        </div>

        <div @class([
            'epp-prices-row',
            'epp-prices-row--paid',
            'epp-prices-row--paid-full' => $paidStatus === 'full',
            'epp-prices-row--paid-partial' => $paidStatus === 'partial',
            'epp-prices-row--paid-none' => $paidStatus === 'none',
        ])>
            <span class="epp-prices-label">Zapłacono</span>
            <span class="epp-prices-value epp-prices-paid">
                @if ($paidStatus === 'full')
                    <span class="epp-prices-paid-icon" aria-hidden="true">✓</span>
                @endif
                {!! $money($paid, $paidSub) !!}
            </span>
        </div>

        @if ($paidStatus !== 'full' && $remaining !== '—')
            <div class="epp-prices-row epp-prices-row--remaining">
                <span class="epp-prices-label">Pozostało</span>
                <span class="epp-prices-value epp-prices-remaining-inline">{!! $money($remaining, $remainingSub) !!}</span>
            </div>
        @endif

        @if ($paymentHint)
            <div class="epp-prices-advance" title="{{ $paymentHint }}">{{ $paymentHint }}</div>
        @endif

        @if (! empty($pilotDueHint))
            <div class="epp-prices-pilot-due" title="{{ $pilotDueHint }}">{{ $pilotDueHint }}</div>
        @elseif (! empty($payerHint))
            <div class="epp-prices-pilot-due" title="{{ $payerHint }}">{{ $payerHint }}</div>
        @endif

        @if ($statusLabel)
            <div class="epp-prices-status">
                <span @class([
                    'inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold',
                    match ($statusColor) {
                        'success' => 'bg-emerald-100 text-emerald-800',
                        'warning' => 'bg-amber-100 text-amber-800',
                        'danger' => 'bg-rose-100 text-rose-800',
                        default => 'bg-gray-100 text-gray-700',
                    },
                ])>{{ $statusLabel }}</span>
            </div>
        @endif

        @if (! empty($documentHint) && $documentHint !== 'Brak pliku')
            @if ($hasUploadedFile && filled($documentFirstUrl))
                <a
                    href="{{ $documentFirstUrl }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="epp-prices-doc epp-prices-doc--chip"
                    title="{{ $documentStatusLabel ?? $documentHint }}"
                    onclick="event.stopPropagation()"
                >{{ $documentHint }}</a>
            @else
                <div
                    @class([
                        'epp-prices-doc',
                        'epp-prices-doc--chip-warn' => ! $hasUploadedFile,
                        'epp-prices-doc--ok' => $hasUploadedFile,
                    ])
                    title="{{ $documentStatusLabel ?? $documentHint }}"
                >
                    {{ $documentHint }}
                </div>
            @endif
        @endif
    @endif
</div>
