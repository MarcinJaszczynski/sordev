@php
    /** @var string $calc */
    /** @var string $planned */
    /** @var string $paid */
    /** @var string $paidStatus */
    /** @var string|null $advanceHtml */
@endphp

<div class="epp-prices-cell">
    @if (! empty($isSetRollup))
        <div class="epp-prices-set-label" style="font-size:10px;color:#6b7280;margin-bottom:4px;font-weight:600">Σ set</div>
    @endif
    <div class="epp-prices-row">
        <span class="epp-prices-label">Kalkulacja</span>
        <span class="epp-prices-value">{{ $calc }}</span>
    </div>

    <div class="epp-prices-row">
        <span class="epp-prices-label">Planowana</span>
        <span class="epp-prices-value">{{ $planned }}</span>
    </div>

    <div @class([
        'epp-prices-row',
        'epp-prices-row--paid',
        'epp-prices-row--paid-full' => $paidStatus === 'full',
        'epp-prices-row--paid-partial' => $paidStatus === 'partial',
        'epp-prices-row--paid-none' => $paidStatus === 'none',
    ])>
        <span class="epp-prices-label">Zapłacona</span>
        <span class="epp-prices-value epp-prices-paid">
            @if ($paidStatus === 'full')
                <span class="epp-prices-paid-icon" aria-hidden="true">✓</span>
            @endif
            {{ $paid }}
        </span>
    </div>

    @if ($advanceHtml)
        <div class="epp-prices-advance">{!! $advanceHtml !!}</div>
    @else
        <div class="epp-prices-advance epp-prices-advance--empty">—</div>
    @endif
</div>
