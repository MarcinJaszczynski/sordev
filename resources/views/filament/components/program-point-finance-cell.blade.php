@php
    /** @var \App\Models\EventProgramPoint|null $record */
    $record = $getRecord();
@endphp

@if (! $record)
    <span class="text-gray-400">—</span>
@else
    @php
        $finance = method_exists($this, 'programPointFinanceViewData')
            ? $this->programPointFinanceViewData($record)
            : [];
        $hide = ! empty($finance['hideSetParentFinance']);
        $calc = (string) ($finance['calc'] ?? '—');
        $planned = (string) ($finance['planned'] ?? '—');
        $paid = (string) ($finance['paid'] ?? '—');
        $planDiffers = ! empty($finance['planDiffersFromCalc']);
        $paidStatus = (string) ($finance['paidStatus'] ?? 'none');
        $paidLabel = ($paid !== '' && $paid !== '—') ? $paid : '—';
    @endphp

    <div class="epp-finance-cell" title="S = szablon · P = plan · Z = zapłacono">
        @if ($hide)
            <span class="text-gray-400">—</span>
        @else
            <div class="epp-ops epp-ops--finance">
                <div class="epp-ops__row" title="Szablon: {{ $calc }}">
                    <span class="epp-ops__label">S</span>
                    <span class="tabular-nums">{{ $calc }}</span>
                </div>
                <div
                    @class([
                        'epp-ops__row',
                        'epp-ops__row--total',
                        'epp-ops__row--warn' => $planDiffers,
                    ])
                    title="Plan: {{ $planned }}{{ $planDiffers ? ' (różni się od szablonu '.$calc.')' : '' }}"
                >
                    <span class="epp-ops__label">P</span>
                    <span class="tabular-nums">{{ $planned }}</span>
                </div>
                <div
                    @class([
                        'epp-ops__row',
                        'epp-ops__row--ok' => $paidStatus === 'full',
                        'epp-ops__row--warn' => $paidStatus === 'partial',
                        'epp-ops__row--muted' => $paidStatus === 'none' || $paidLabel === '—',
                    ])
                    title="Zapłacono: {{ $paidLabel }}"
                >
                    <span class="epp-ops__label">Z</span>
                    <span class="tabular-nums">{{ $paidLabel }}</span>
                </div>
            </div>
        @endif
    </div>
@endif
