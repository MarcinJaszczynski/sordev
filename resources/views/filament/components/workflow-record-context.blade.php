@php
    $context = $context ?? [];
@endphp

@if (! empty($context['title']))
    <div class="workflow-record-context mb-6 rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-white/10 dark:bg-gray-900">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    @if (! empty($context['type']))
                        <span class="workflow-record-context-type text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $context['type'] }}</span>
                    @endif
                    @if (! empty($context['status']))
                        <span @class([
                            'workflow-record-context-status admin-table-pill',
                            'bg-amber-100 text-amber-900' => ($context['statusColor'] ?? '') === 'warning',
                            'bg-green-100 text-green-800' => ($context['statusColor'] ?? '') === 'success',
                            'bg-gray-100 text-gray-700' => ($context['statusColor'] ?? '') === 'gray',
                            'bg-blue-100 text-blue-800' => ($context['statusColor'] ?? '') === 'info',
                        ])>{{ $context['status'] }}</span>
                    @endif
                </div>
                @if (! empty($context['title_url']))
                    <a
                        href="{{ $context['title_url'] }}"
                        class="workflow-record-context-title mt-1 block truncate text-lg font-bold text-gray-950 hover:text-primary-600 hover:underline dark:text-white dark:hover:text-primary-400"
                    >
                        {{ $context['title'] }}
                    </a>
                @else
                    <h2 class="workflow-record-context-title mt-1 truncate text-lg font-bold text-gray-950 dark:text-white">{{ $context['title'] }}</h2>
                @endif
                @if (! empty($context['subtitle']))
                    <p class="workflow-record-context-subtitle mt-0.5 text-sm text-gray-600 dark:text-gray-400">{{ $context['subtitle'] }}</p>
                @endif
                @if (! empty($context['meta']))
                    <dl class="mt-2 flex flex-wrap gap-x-4 gap-y-1">
                        @foreach ($context['meta'] as $item)
                            <div class="text-sm">
                                <dt class="inline text-gray-500">{{ $item['label'] }}:</dt>
                                <dd class="inline font-medium text-gray-900 dark:text-gray-100">{{ $item['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
                @if (! empty($context['finance']))
                    @php
                        $finance = $context['finance'];
                        $labels = $finance['labels'] ?? [];
                    @endphp
                    <div class="mt-3 space-y-2 rounded-lg border border-gray-100 bg-gray-50 px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-800/60">
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                            <div>
                                <span class="text-gray-500">{{ $labels['calculation'] ?? 'Koszty (kalkulacja)' }}:</span>
                                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $finance['calculation'] ?? '—' }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500">{{ $labels['planned'] ?? 'Koszty (plan)' }}:</span>
                                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $finance['planned'] ?? '—' }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500">{{ $labels['paid'] ?? 'Zapłacone dostawcom' }}:</span>
                                <span class="font-semibold text-emerald-700 dark:text-emerald-400">{{ $finance['paid'] ?? '—' }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500">{{ $labels['remaining'] ?? 'Do zapłaty dostawcom' }}:</span>
                                <span class="font-semibold text-rose-700 dark:text-rose-400">{{ $finance['remaining'] ?? '—' }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500">{{ $labels['pilot_cash'] ?? 'Gotówka dla pilota (plan)' }}:</span>
                                <span class="font-semibold text-indigo-800 dark:text-indigo-300" title="Plan gotówki z systemu (koszty / przygotowanie).">{{ $finance['pilot_cash'] ?? '—' }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500">{{ $labels['pilot_cash_paid'] ?? 'Wypłacono pilotowi' }}:</span>
                                <span class="font-semibold text-indigo-800 dark:text-indigo-300" title="Faktyczna gotówka wypłacona pilotowi z biura.">{{ $finance['pilot_cash_paid'] ?? '—' }}</span>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-gray-200/80 pt-2 dark:border-white/10">
                            <div>
                                <span class="text-gray-500">{{ $labels['price_per_person'] ?? 'Cena za osobę (umowa / kalkulacja)' }}:</span>
                                <span class="font-semibold text-primary-700 dark:text-primary-300" title="{{ $finance['price_per_person_hint'] ?? 'Lewa: umowa/aneks (lub ręczna). Prawa: kalkulacja programu.' }}">{{ $finance['price_per_person'] ?? '—' }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500">{{ $labels['client_due'] ?? 'Należne od klientów' }}:</span>
                                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $finance['client_due'] ?? '—' }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500">{{ $labels['client_paid'] ?? 'Wpłacono od klientów' }}:</span>
                                <span class="font-semibold text-sky-800 dark:text-sky-300">{{ $finance['client_paid'] ?? '—' }}</span>
                            </div>
                        </div>
                        @if (! empty($finance['calc_plan_hint']))
                            <p class="text-xs text-amber-800 dark:text-amber-200">{{ $finance['calc_plan_hint'] }}</p>
                        @endif
                    </div>
                @endif
            </div>
            @if (! empty($context['links']))
                <div class="flex flex-wrap gap-2">
                    @foreach ($context['links'] as $link)
                        @if (! empty($link['wire_click']))
                            <button
                                type="button"
                                wire:click="{{ $link['wire_click'] }}"
                                class="workflow-record-context-link inline-flex min-h-[var(--admin-touch-min)] items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm font-medium text-gray-800 hover:bg-white dark:border-white/10 dark:bg-gray-800 dark:text-gray-200"
                            >
                                @if (! empty($link['icon']))
                                    <x-filament::icon :icon="$link['icon']" class="h-4 w-4" />
                                @endif
                                {{ $link['label'] }}
                            </button>
                        @else
                            <a
                                href="{{ $link['url'] }}"
                                @if (! empty($link['external'])) target="_blank" rel="noopener" @endif
                                class="workflow-record-context-link inline-flex min-h-[var(--admin-touch-min)] items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm font-medium text-gray-800 hover:bg-white dark:border-white/10 dark:bg-gray-800 dark:text-gray-200"
                            >
                                @if (! empty($link['icon']))
                                    <x-filament::icon :icon="$link['icon']" class="h-4 w-4" />
                                @endif
                                {{ $link['label'] }}
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endif
