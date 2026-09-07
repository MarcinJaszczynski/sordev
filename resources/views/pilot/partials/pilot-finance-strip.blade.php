@php
    /** @var array<string, mixed> $financeHint */
    $compact = $compact ?? false;
@endphp

<div @class([
    'mt-2 rounded-lg border px-3 py-2',
    'border-teal-200 bg-teal-50 dark:border-teal-800 dark:bg-teal-950/30' => empty($financeHint['has_pilot_obligation']),
    'border-sky-200 bg-sky-50 dark:border-sky-800 dark:bg-sky-950/30' => ! empty($financeHint['has_pilot_obligation']),
])>
    <p @class([
        'text-[11px] font-semibold uppercase tracking-wide',
        'text-teal-800 dark:text-teal-200' => empty($financeHint['has_pilot_obligation']),
        'text-sky-800 dark:text-sky-200' => ! empty($financeHint['has_pilot_obligation']),
    ])>
        {{ $financeHint['payer_label'] }}
    </p>
    <ul @class([
        'mt-1 space-y-0.5 text-xs',
        'text-teal-900 dark:text-teal-100' => empty($financeHint['has_pilot_obligation']),
        'text-sky-900 dark:text-sky-100' => ! empty($financeHint['has_pilot_obligation']),
    ])>
        @foreach(($financeHint['lines'] ?? []) as $line)
            <li>{{ $line }}</li>
        @endforeach
        @if(! $compact && filled($financeHint['planned_label'] ?? null))
            <li><span class="font-medium">Planowane:</span> {{ $financeHint['planned_label'] }}</li>
        @endif
        @if(! $compact && filled($financeHint['advance_label'] ?? null))
            <li><span class="font-medium">Zaliczka:</span> {{ $financeHint['advance_label'] }}</li>
        @endif
        @if(! $compact && filled($financeHint['due_date_label'] ?? null))
            <li><span class="font-medium">Płatne do:</span> {{ $financeHint['due_date_label'] }}</li>
        @endif
        @foreach(($financeHint['payment_lines'] ?? []) as $line)
            <li>
                @if(! $compact)
                    <span class="font-medium">Termin:</span>
                @endif
                {{ $line }}
            </li>
        @endforeach
    </ul>
</div>
