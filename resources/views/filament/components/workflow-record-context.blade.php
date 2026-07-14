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
                    @php $finance = $context['finance']; @endphp
                    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 rounded-lg border border-gray-100 bg-gray-50 px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-800/60">
                        <div>
                            <span class="text-gray-500">Plan:</span>
                            <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $finance['planned'] ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Wpłacono:</span>
                            <span class="font-semibold text-emerald-700 dark:text-emerald-400">{{ $finance['paid'] ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Zostało:</span>
                            <span class="font-semibold text-rose-700 dark:text-rose-400">{{ $finance['remaining'] ?? '—' }}</span>
                        </div>
                        @if (! empty($finance['pilot_cash']))
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span class="text-gray-500">Gotówka pilota:</span>
                                @foreach ($finance['pilot_cash'] as $cashLine)
                                    <span class="inline-flex items-center rounded-md bg-white px-2 py-0.5 text-xs font-medium text-gray-800 ring-1 ring-gray-200 dark:bg-gray-900 dark:text-gray-200 dark:ring-white/10">
                                        {{ $cashLine['label'] }} {{ $cashLine['value'] }}
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            </div>
            @if (! empty($context['links']))
                <div class="flex flex-wrap gap-2">
                    @foreach ($context['links'] as $link)
                        <a
                            href="{{ $link['url'] }}"
                            class="workflow-record-context-link inline-flex min-h-[var(--admin-touch-min)] items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm font-medium text-gray-800 hover:bg-white dark:border-white/10 dark:bg-gray-800 dark:text-gray-200"
                        >
                            @if (! empty($link['icon']))
                                <x-filament::icon :icon="$link['icon']" class="h-4 w-4" />
                            @endif
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endif
