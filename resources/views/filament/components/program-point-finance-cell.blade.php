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
    @endphp

    <div class="epp-finance-cell">
        @if ($hide)
            <span class="text-gray-400">—</span>
        @else
            <div class="epp-ops epp-ops--finance">
                <div class="epp-ops__row">
                    <span class="epp-ops__label">Szablon</span>
                    <span class="tabular-nums">{{ $calc }}</span>
                </div>
                <div @class([
                    'epp-ops__row',
                    'epp-ops__row--total',
                    'epp-ops__row--warn' => $planDiffers,
                ])>
                    <span class="epp-ops__label">Plan</span>
                    <span class="tabular-nums" @if ($planDiffers) title="Plan różni się od szablonu: {{ $calc }}" @endif>{{ $planned }}</span>
                </div>
                <div @class([
                    'epp-ops__row',
                    'epp-ops__row--ok' => $paidStatus === 'full',
                    'epp-ops__row--warn' => $paidStatus === 'partial',
                ])>
                    <span class="epp-ops__label">Zapł.</span>
                    <span class="tabular-nums">{{ ($paid !== '' && $paid !== '—') ? $paid : '—' }}</span>
                </div>
            </div>
        @endif
    </div>
@endif
