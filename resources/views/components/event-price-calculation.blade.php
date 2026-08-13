@props([
    'template' => null,
    'participantCount' => 1,
    'gratisCount' => 0,
    'startPlaceId' => null,
    'calculatedTotal' => null,
])

@php
    use App\Services\EventPriceSummaryService;
    use App\Support\MoneyFormatter;

    $participantCount = max(1, (int) $participantCount);
    $gratisCount = max(0, (int) $gratisCount);
    $startPlaceId = $startPlaceId ? (int) $startPlaceId : null;
    $summary = [
        'ready' => false,
        'message' => 'Wybierz szablon i miejsce wyjazdu.',
        'price_per_person_label' => '—',
        'total_pln' => 0,
        'base_pln' => 0,
        'markup_pln' => 0,
        'tax_pln' => 0,
        'paying' => $participantCount,
        'gratis' => $gratisCount,
        'nearest' => [],
        'foreign_prices' => [],
    ];

    if ($template && $startPlaceId) {
        $summary = app(EventPriceSummaryService::class)->forTemplate(
            $template,
            $startPlaceId,
            $participantCount,
            $gratisCount,
        );
    } elseif ($template && ! $startPlaceId) {
        $summary['message'] = 'Wybierz miejsce wyjazdu oraz podaj liczbę uczestników i opiekunów, aby obliczyć cenę.';
    }
@endphp

<div class="space-y-3">
    @if(! ($summary['ready'] ?? false))
        <div class="rounded border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-100">
            {{ $summary['message'] ?? 'Brak danych do kalkulacji.' }}
        </div>
    @else
        <div class="rounded-lg border border-primary-300 bg-primary-50/60 p-4 dark:border-primary-800 dark:bg-primary-950/30">
            <div class="text-xs font-semibold uppercase tracking-wide text-primary-700 dark:text-primary-300">Cena za osobę (płacący)</div>
            <div class="mt-1 text-2xl font-bold tabular-nums text-gray-900 dark:text-gray-100">
                {{ $summary['price_per_person_label'] }}
            </div>
            <div class="mt-2 grid gap-1 text-sm text-gray-700 dark:text-gray-300 sm:grid-cols-2">
                <div>Suma grupy: <strong class="tabular-nums">{{ MoneyFormatter::format((float) $summary['total_pln'], 'PLN') }}</strong></div>
                <div>Płacących: <strong>{{ (int) $summary['paying'] }}</strong> · opiekunów: <strong>{{ (int) $summary['gratis'] }}</strong></div>
                <div>Baza: <span class="tabular-nums">{{ MoneyFormatter::format((float) $summary['base_pln'], 'PLN') }}</span></div>
                <div>Marża: <span class="tabular-nums">{{ MoneyFormatter::format((float) $summary['markup_pln'], 'PLN') }}</span>
                    · Podatki: <span class="tabular-nums">{{ MoneyFormatter::format((float) $summary['tax_pln'], 'PLN') }}</span></div>
            </div>
            <p class="mt-2 text-xs text-gray-500">
                Pełna kalkulacja szablonu: program + noclegi + transport + ubezpieczenie + marża + podatki.
                Koszty dzielone przez uczestników płacących (bez opiekunów).
            </p>
        </div>

        @if(! empty($summary['nearest']))
            <div class="rounded border border-gray-200 bg-white p-3 text-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Porównanie cennika szablonu (2 najbliższe grupy)</div>
                <ul class="space-y-1">
                    @foreach($summary['nearest'] as $near)
                        <li class="flex flex-wrap items-baseline justify-between gap-2">
                            <span class="text-gray-600 dark:text-gray-400">
                                {{ (int) $near['qty'] }} os.
                                @if((int) ($near['gratis'] ?? 0) > 0)
                                    + {{ (int) $near['gratis'] }} opiek.
                                @endif
                            </span>
                            <strong class="tabular-nums text-gray-900 dark:text-gray-100">{{ $near['label'] }}</strong>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif
</div>
