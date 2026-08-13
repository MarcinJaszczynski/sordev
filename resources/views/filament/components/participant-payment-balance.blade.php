@php
    $balance = \App\Support\ParticipantPaymentBalancePresenter::fromRow($row ?? []);
    $compact = $compact ?? false;
@endphp

@if ($compact)
    <div class="flex flex-wrap gap-x-3 gap-y-0.5 text-xs tabular-nums">
        <span title="Należne">N: {!! $balance['due_html'] !!}</span>
        <span title="Wpłacone" class="text-emerald-700">W: {!! $balance['paid_html'] !!}</span>
        <span title="Różnica" @class(['text-rose-700' => $balance['difference_pln'] > 0.009, 'text-emerald-700' => $balance['difference_pln'] < -0.009])>
            R: {!! $balance['difference_html'] !!}
        </span>
    </div>
@else
    <div class="grid grid-cols-3 gap-2 text-center text-xs">
        <div class="rounded-lg bg-gray-50 p-2 dark:bg-gray-800">
            <div class="text-[10px] font-medium uppercase tracking-wide text-gray-500">Należne</div>
            <div class="mt-0.5 font-semibold tabular-nums">{!! $balance['due_html'] !!}</div>
        </div>
        <div class="rounded-lg bg-emerald-50 p-2 dark:bg-emerald-950/30">
            <div class="text-[10px] font-medium uppercase tracking-wide text-emerald-700">Wpłacone</div>
            <div class="mt-0.5 font-semibold tabular-nums text-emerald-800">{!! $balance['paid_html'] !!}</div>
        </div>
        <div @class([
            'rounded-lg p-2',
            'bg-rose-50 dark:bg-rose-950/30' => $balance['difference_pln'] > 0.009,
            'bg-emerald-50 dark:bg-emerald-950/30' => $balance['difference_pln'] < -0.009,
            'bg-gray-50 dark:bg-gray-800' => abs($balance['difference_pln']) <= 0.009,
        ])>
            <div class="text-[10px] font-medium uppercase tracking-wide text-gray-500">Różnica</div>
            <div class="mt-0.5 font-semibold tabular-nums">{!! $balance['difference_html'] !!}</div>
        </div>
    </div>
@endif
