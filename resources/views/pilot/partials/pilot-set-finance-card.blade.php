@php
    /** @var \App\Support\PilotSetFinanceCard $card */
    $compact = $compact ?? false;
    $showHeader = $showHeader ?? true;
@endphp

<div @class([
    'rounded-lg border px-3 py-2',
    'mt-2' => ! $compact,
    'border-sky-200 bg-sky-50 dark:border-sky-800 dark:bg-sky-950/30' => $card->hasPilotObligation,
    'border-teal-200 bg-teal-50 dark:border-teal-800 dark:bg-teal-950/30' => ! $card->hasPilotObligation,
])>
    @if($showHeader)
        <p @class([
            'text-[11px] font-semibold uppercase tracking-wide',
            'text-sky-800 dark:text-sky-200' => $card->hasPilotObligation,
            'text-teal-800 dark:text-teal-200' => ! $card->hasPilotObligation,
        ])>
            Set: {{ $card->parentName }} (Dzień {{ $card->day }})
        </p>
    @endif

    @if($card->hasPilotObligation)
        <p class="mt-1 text-xs font-semibold text-sky-900 dark:text-sky-100">
            Łącznie do zapłacenia: {{ $card->totalPilotDueLabel }}
            <span class="font-normal text-sky-700 dark:text-sky-300">(plan: {{ $card->plannedPilotLabel }})</span>
        </p>
    @endif

    @if($card->memberLines !== [])
        <ul @class([
            'space-y-0.5 text-xs',
            'mt-1' => $showHeader || $card->hasPilotObligation,
            'text-sky-900 dark:text-sky-100' => $card->hasPilotObligation,
            'text-teal-900 dark:text-teal-100' => ! $card->hasPilotObligation,
        ])>
            @foreach($card->memberLines as $line)
                <li>
                    <span class="mr-1" aria-hidden="true">•</span>
                    {{ $line->displayLabel }}
                </li>
            @endforeach
        </ul>
    @endif
</div>
