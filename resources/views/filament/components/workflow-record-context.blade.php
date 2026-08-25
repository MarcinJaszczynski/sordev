@php
    $context = $context ?? [];
    $livewireId = $livewireId ?? null;
    $financeTick = (int) ($financeTick ?? 0);
    $finance = $context['finance'] ?? null;
    $labels = is_array($finance) ? ($finance['labels'] ?? []) : [];
    $metaItems = collect($context['meta'] ?? []);

    $metaPrimary = static function (array $item): string {
        $raw = (string) ($item['value'] ?? '');
        $parts = array_values(array_filter(array_map('trim', explode(' · ', $raw, 2)), fn ($part) => $part !== ''));

        return $parts[0] ?? $raw;
    };

    $metaHintLines = static function (array $item): array {
        $lines = [];
        $raw = (string) ($item['value'] ?? '');
        $parts = array_values(array_filter(array_map('trim', explode(' · ', $raw, 2)), fn ($part) => $part !== ''));
        $primary = $parts[0] ?? $raw;
        $secondary = $parts[1] ?? null;

        if ($secondary && mb_strtoupper($secondary) !== mb_strtoupper($primary)) {
            $lines[] = $secondary;
        }

        if (! empty($item['hint'])) {
            foreach (explode(' · ', (string) $item['hint']) as $hintPart) {
                $hintPart = trim($hintPart);
                if ($hintPart !== '') {
                    $lines[] = $hintPart;
                }
            }
        }

        return array_values(array_unique($lines));
    };
@endphp

@if (! empty($context['title']))
    <div wire:key="workflow-finance-{{ $financeTick }}" class="workflow-record-context workflow-info-bar mb-3 rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">

        <div class="workflow-info-bar__header">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                    @if (! empty($context['title_url']))
                        <a href="{{ $context['title_url'] }}" class="workflow-info-bar__title truncate hover:text-primary-600 hover:underline dark:hover:text-primary-400">
                            {{ $context['title'] }}
                        </a>
                    @else
                        <h2 class="workflow-info-bar__title truncate">{{ $context['title'] }}</h2>
                    @endif

                    @if (! empty($context['code']))
                        <span class="workflow-info-bar__chip font-mono">{{ $context['code'] }}</span>
                    @endif
                    @if (! empty($context['participants']))
                        <span class="workflow-info-bar__chip">
                            <span class="workflow-info-bar__schedule-label">uczestnicy</span>
                            {{ $context['participants'] }}
                        </span>
                    @endif

                    @if (! empty($context['status']))
                        <span @class([
                            'rounded px-1.5 py-0.5 text-[11px] font-semibold ring-1 ring-inset',
                            'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-950 dark:text-emerald-400' => ($context['statusColor'] ?? '') === 'success',
                            'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-950 dark:text-amber-300' => ($context['statusColor'] ?? '') === 'warning',
                            'bg-sky-50 text-sky-800 ring-sky-600/20 dark:bg-sky-950 dark:text-sky-300' => ($context['statusColor'] ?? '') === 'info',
                            'bg-gray-100 text-gray-700 ring-gray-500/20 dark:bg-gray-800 dark:text-gray-300' => ! in_array(($context['statusColor'] ?? ''), ['success', 'warning', 'info'], true),
                        ])>
                            ● {{ $context['status'] }}
                        </span>
                    @endif
                </div>

                <div class="workflow-info-bar__schedule">
                    @if (! empty($context['substitution_label']))
                        <span>
                            <span class="workflow-info-bar__schedule-label">podstawienie</span>
                            {{ $context['substitution_label'] }}
                        </span>
                    @endif
                    @if (! empty($context['return_label']))
                        <span>
                            <span class="workflow-info-bar__schedule-label">powrót</span>
                            {{ $context['return_label'] }}
                        </span>
                    @endif
                    @if (! empty($context['start_place']))
                        <span>
                            <span class="workflow-info-bar__schedule-label">wyjazd z:</span>
                            {{ $context['start_place'] }}
                        </span>
                    @endif
                </div>
            </div>

            @if (! empty($context['links']))
                <div class="flex shrink-0 flex-wrap items-center gap-1.5">
                    @foreach ($context['links'] as $link)
                        @php
                            $isPrimary = ($link['wire_click'] ?? null) === 'openEventCreateTaskModal'
                                || str_contains(mb_strtolower((string) ($link['label'] ?? '')), 'zadanie');
                        @endphp

                        @if (! empty($link['wire_click']))
                            <button
                                type="button"
                                @if (filled($livewireId))
                                    onclick="window.Livewire.find(@js($livewireId))?.call(@js($link['wire_click']))"
                                @else
                                    wire:click="{{ $link['wire_click'] }}"
                                @endif
                                @class([
                                    'workflow-record-context-action inline-flex items-center gap-1 rounded-md px-2 py-1 text-[11px] font-semibold',
                                    'bg-primary-600 text-white hover:bg-primary-500' => $isPrimary,
                                    'bg-white text-gray-800 ring-1 ring-inset ring-gray-950/10 hover:bg-gray-50 dark:bg-white/5 dark:text-gray-200 dark:ring-white/20' => ! $isPrimary,
                                ])
                            >
                                @if (! empty($link['icon']))
                                    <x-filament::icon :icon="$link['icon']" class="h-3.5 w-3.5" />
                                @endif
                                {{ $link['label'] }}
                            </button>
                        @else
                            <a
                                href="{{ $link['url'] ?? '#' }}"
                                @if (! empty($link['external'])) target="_blank" rel="noopener noreferrer" @endif
                                @class([
                                    'workflow-record-context-action inline-flex items-center gap-1 rounded-md px-2 py-1 text-[11px] font-semibold',
                                    'bg-primary-600 text-white hover:bg-primary-500' => $isPrimary,
                                    'bg-white text-gray-800 ring-1 ring-inset ring-gray-950/10 hover:bg-gray-50 dark:bg-white/5 dark:text-gray-200 dark:ring-white/20' => ! $isPrimary,
                                ])
                            >
                                @if (! empty($link['icon']))
                                    <x-filament::icon :icon="$link['icon']" class="h-3.5 w-3.5" />
                                @endif
                                {{ $link['label'] }}
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        @if ($metaItems->isNotEmpty())
            @php
                $metaOrder = ['Zamawiający', 'Pilot', 'Transport', 'Hotel', 'Opiekun imprezy', 'Kierowca'];
                $metaByLabel = $metaItems->keyBy('label');
                $orderedMeta = collect($metaOrder)
                    ->map(fn (string $label) => $metaByLabel->get($label))
                    ->filter()
                    ->values()
                    ->concat($metaItems->reject(fn ($item) => in_array($item['label'] ?? '', $metaOrder, true))->values());
            @endphp
            <div class="workflow-info-bar__meta">
                @foreach ($orderedMeta as $item)
                    @php
                        $primary = $metaPrimary($item);
                        $hintLines = $metaHintLines($item);
                        $label = (string) ($item['label'] ?? '');
                    @endphp
                    <div class="min-w-0">
                        <div class="text-[10px] leading-tight text-gray-500 dark:text-gray-400">{{ $label }}</div>
                        @if (! empty($item['url']))
                            <a href="{{ $item['url'] }}" @class(['block truncate text-[12px] font-semibold leading-tight text-primary-700 hover:underline dark:text-primary-300', 'font-mono' => $label === 'Kod'])>{{ $primary }}</a>
                        @else
                            <div @class(['truncate text-[12px] font-semibold leading-tight text-gray-900 dark:text-gray-100', 'font-mono' => $label === 'Kod'])>{{ $primary }}</div>
                        @endif
                        @foreach ($hintLines as $hintLine)
                            <div class="truncate text-[10px] leading-tight text-gray-500 dark:text-gray-400">{{ $hintLine }}</div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endif

        @if (is_array($finance))
            <div>
                <div class="workflow-info-bar__section-title">
                    <x-heroicon-m-building-office-2 class="h-3.5 w-3.5 text-gray-400" />
                    Rozliczenie Dostawców
                </div>
                <div class="workflow-info-bar__vendors">
                    <div class="workflow-info-bar__pair">
                        <div class="workflow-info-bar__tile">
                            <div class="workflow-info-bar__tile-label">{{ $labels['planned'] ?? 'Koszty (planowane)' }}</div>
                            <div class="workflow-info-bar__tile-value">{{ $finance['planned'] ?? '—' }}</div>
                        </div>
                        <div class="workflow-info-bar__tile">
                            <div class="workflow-info-bar__tile-label">{{ $labels['calculation'] ?? 'Koszty (szablon)' }}</div>
                            <div class="workflow-info-bar__tile-value">{{ $finance['calculation'] ?? '—' }}</div>
                        </div>
                    </div>
                    <div class="workflow-info-bar__pair">
                        <div class="workflow-info-bar__tile workflow-info-bar__tile--ok">
                            <div class="workflow-info-bar__tile-label">{{ $labels['paid'] ?? 'Zapłacono' }}</div>
                            <div class="workflow-info-bar__tile-value">{{ $finance['paid'] ?? '—' }}</div>
                        </div>
                        <div class="workflow-info-bar__tile workflow-info-bar__tile--danger">
                            <div class="workflow-info-bar__tile-label">{{ $labels['remaining'] ?? 'Do zapłaty dostawcom' }}</div>
                            <div class="workflow-info-bar__tile-value">{{ $finance['remaining'] ?? '—' }}</div>
                        </div>
                    </div>
                    <div class="workflow-info-bar__pair">
                        <div class="workflow-info-bar__tile workflow-info-bar__tile--pilot">
                            <div class="workflow-info-bar__tile-label">{{ $labels['pilot_cash'] ?? 'Gotówka dla pilota (plan)' }}</div>
                            <div class="workflow-info-bar__tile-value">{{ $finance['pilot_cash'] ?? '—' }}</div>
                        </div>
                        <div class="workflow-info-bar__tile workflow-info-bar__tile--pilot">
                            <div class="workflow-info-bar__tile-label">{{ $labels['pilot_cash_paid'] ?? 'Wypłacono pilotowi' }}</div>
                            <div class="workflow-info-bar__tile-value">{{ $finance['pilot_cash_paid'] ?? '—' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <div class="workflow-info-bar__section-title">
                    <x-heroicon-m-user-group class="h-3.5 w-3.5 text-gray-400" />
                    Rozliczenie Klientów
                </div>
                <div class="workflow-info-bar__clients">
                    <div class="workflow-info-bar__tile">
                        <div class="workflow-info-bar__tile-label">{{ $labels['price_per_person'] ?? 'Cena za osobę (umowa / szablon)' }}</div>
                        <div class="workflow-info-bar__tile-value" title="{{ $finance['price_per_person_hint'] ?? '' }}">{{ $finance['price_per_person'] ?? '—' }}</div>
                    </div>
                    <div class="workflow-info-bar__tile workflow-info-bar__tile--info">
                        <div class="workflow-info-bar__tile-label">{{ $labels['client_paid'] ?? 'Wpłacono od klientów' }}</div>
                        <div class="workflow-info-bar__tile-value">{{ $finance['client_paid'] ?? '—' }}</div>
                    </div>
                    <div class="workflow-info-bar__tile">
                        <div class="workflow-info-bar__tile-label">{{ $labels['client_due'] ?? 'Należne od klientów' }}</div>
                        <div class="workflow-info-bar__tile-value">{{ $finance['client_due'] ?? '—' }}</div>
                    </div>
                    @php
                        $remainingTone = (string) ($finance['client_remaining_tone'] ?? 'due');
                        $remainingTileClass = match ($remainingTone) {
                            'ok' => 'workflow-info-bar__tile--ok',
                            'over' => 'workflow-info-bar__tile--warn',
                            default => 'workflow-info-bar__tile--danger',
                        };
                    @endphp
                    <div @class(['workflow-info-bar__tile', $remainingTileClass])>
                        <div class="workflow-info-bar__tile-label">{{ $labels['client_remaining'] ?? 'Do dopłaty od klientów' }}</div>
                        <div class="workflow-info-bar__tile-value">{{ $finance['client_remaining'] ?? '—' }}</div>
                    </div>
                </div>
            </div>

            @if (! empty($finance['calc_plan_hint']))
                <div class="mt-1.5 flex items-center gap-1 border-t border-gray-100 pt-1.5 text-[10px] text-amber-700 dark:border-gray-800 dark:text-amber-400">
                    <x-heroicon-m-information-circle class="h-3.5 w-3.5 shrink-0" />
                    <span class="leading-tight">{{ $finance['calc_plan_hint'] }}</span>
                </div>
            @endif
        @endif
    </div>
@endif
