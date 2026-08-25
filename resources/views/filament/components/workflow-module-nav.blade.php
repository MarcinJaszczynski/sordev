@php
    $tabs = $tabs ?? [];
    $ariaLabel = $ariaLabel ?? 'Nawigacja modułu';
    $hasGroups = collect($tabs)->contains(fn (array $tab): bool => filled($tab['group'] ?? null));
@endphp

<nav class="workflow-module-nav mb-3 overflow-x-auto" aria-label="{{ $ariaLabel }}">
    @if ($hasGroups)
        <div class="flex min-w-max gap-3 border-b border-gray-100 pb-1 dark:border-gray-800">
            @foreach (collect($tabs)->groupBy(fn (array $tab) => $tab['group'] ?? 'Inne') as $groupLabel => $groupTabs)
                <div class="flex flex-col gap-0.5">
                    <div class="px-1 text-[10px] font-semibold uppercase tracking-wide text-gray-400">
                        {{ $groupLabel }}
                    </div>
                    <div class="flex gap-1.5">
                        @foreach ($groupTabs as $tab)
                            <a
                                href="{{ $tab['url'] }}"
                                @class([
                                    'workflow-module-nav-item inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-[12px] font-semibold transition',
                                    'bg-primary-600 text-white shadow-sm' => ! empty($tab['active']),
                                    'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700' => empty($tab['active']),
                                ])
                            >
                                @if (! empty($tab['icon']))
                                    <x-filament::icon :icon="$tab['icon']" class="h-3.5 w-3.5 shrink-0 opacity-80" />
                                @endif
                                <span>{{ $tab['label'] }}</span>
                                @if (! empty($tab['badge']))
                                    <span class="rounded bg-white/20 px-1 py-0.5 text-[10px] font-bold">{{ $tab['badge'] }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="flex min-w-max gap-1.5 border-b border-gray-100 pb-1 dark:border-gray-800">
            @foreach ($tabs as $tab)
                <a
                    href="{{ $tab['url'] }}"
                    @class([
                        'workflow-module-nav-item inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-[12px] font-semibold transition',
                        'bg-primary-600 text-white shadow-sm' => ! empty($tab['active']),
                        'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700' => empty($tab['active']),
                    ])
                >
                    @if (! empty($tab['icon']))
                        <x-filament::icon :icon="$tab['icon']" class="h-3.5 w-3.5 shrink-0 opacity-80" />
                    @endif
                    <span>{{ $tab['label'] }}</span>
                    @if (! empty($tab['badge']))
                        <span class="rounded bg-white/20 px-1 py-0.5 text-[10px] font-bold">{{ $tab['badge'] }}</span>
                    @endif
                </a>
            @endforeach
        </div>
    @endif
</nav>
