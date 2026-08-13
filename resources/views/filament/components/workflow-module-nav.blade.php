@php
    $tabs = $tabs ?? [];
    $ariaLabel = $ariaLabel ?? 'Nawigacja modułu';
    $hasGroups = collect($tabs)->contains(fn (array $tab): bool => filled($tab['group'] ?? null));
@endphp

<nav class="workflow-module-nav mb-6 overflow-x-auto" aria-label="{{ $ariaLabel }}">
    @if ($hasGroups)
        <div class="flex min-w-max gap-4 border-b border-gray-200 pb-1 dark:border-white/10">
            @foreach (collect($tabs)->groupBy(fn (array $tab) => $tab['group'] ?? 'Inne') as $groupLabel => $groupTabs)
                <div class="flex flex-col gap-1">
                    <div class="px-1 text-[0.65rem] font-semibold uppercase tracking-wide text-gray-400">
                        {{ $groupLabel }}
                    </div>
                    <div class="flex gap-2">
                        @foreach ($groupTabs as $tab)
                            <a
                                href="{{ $tab['url'] }}"
                                @class([
                                    'workflow-module-nav-item group flex min-w-[9.5rem] flex-col rounded-t-lg border px-3 py-2 transition',
                                    'border-amber-300 bg-amber-50 text-amber-900 shadow-sm dark:border-amber-600 dark:bg-amber-950/40 dark:text-amber-100' => ! empty($tab['active']),
                                    'border-transparent bg-gray-50 text-gray-700 hover:border-gray-200 hover:bg-white dark:bg-gray-900 dark:text-gray-300 dark:hover:border-white/10 dark:hover:bg-gray-800' => empty($tab['active']),
                                ])
                            >
                                <span class="flex items-center gap-1.5 text-sm font-semibold leading-tight">
                                    @if (! empty($tab['icon']))
                                        <x-filament::icon :icon="$tab['icon']" class="h-4 w-4 shrink-0" />
                                    @endif
                                    <span>{{ $tab['label'] }}</span>
                                    @if (! empty($tab['badge']))
                                        <span class="rounded-full bg-danger-100 px-1.5 py-0.5 text-[0.65rem] font-bold text-danger-700">{{ $tab['badge'] }}</span>
                                    @endif
                                </span>
                                @if (! empty($tab['description']))
                                    <span class="workflow-module-nav-desc mt-0.5 text-[0.72rem] leading-snug text-gray-500 group-hover:text-gray-600 dark:text-gray-400">{{ $tab['description'] }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="flex min-w-max gap-2 border-b border-gray-200 pb-1 dark:border-white/10">
            @foreach ($tabs as $tab)
                <a
                    href="{{ $tab['url'] }}"
                    @class([
                        'workflow-module-nav-item group flex min-w-[9.5rem] flex-col rounded-t-lg border px-3 py-2 transition',
                        'border-amber-300 bg-amber-50 text-amber-900 shadow-sm dark:border-amber-600 dark:bg-amber-950/40 dark:text-amber-100' => ! empty($tab['active']),
                        'border-transparent bg-gray-50 text-gray-700 hover:border-gray-200 hover:bg-white dark:bg-gray-900 dark:text-gray-300 dark:hover:border-white/10 dark:hover:bg-gray-800' => empty($tab['active']),
                    ])
                >
                    <span class="flex items-center gap-1.5 text-sm font-semibold leading-tight">
                        @if (! empty($tab['icon']))
                            <x-filament::icon :icon="$tab['icon']" class="h-4 w-4 shrink-0" />
                        @endif
                        <span>{{ $tab['label'] }}</span>
                        @if (! empty($tab['badge']))
                            <span class="rounded-full bg-danger-100 px-1.5 py-0.5 text-[0.65rem] font-bold text-danger-700">{{ $tab['badge'] }}</span>
                        @endif
                    </span>
                    @if (! empty($tab['description']))
                        <span class="workflow-module-nav-desc mt-0.5 text-[0.72rem] leading-snug text-gray-500 group-hover:text-gray-600 dark:text-gray-400">{{ $tab['description'] }}</span>
                    @endif
                </a>
            @endforeach
        </div>
    @endif
</nav>
