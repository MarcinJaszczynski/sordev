@php
    $record = $getRecord();
    $items = $record ? ($items ?? \App\Support\EventReadinessIndicators::forEventOverview($record)) : [];
@endphp

@if ($record)
<div class="mb-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 rounded-xl p-4 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div class="flex items-center gap-3">
        <div class="h-10 w-10 rounded-full bg-primary-100 dark:bg-primary-900/50 flex items-center justify-center text-primary-600 dark:text-primary-400">
            <x-heroicon-m-check-badge class="h-6 w-6" />
        </div>
        <div>
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Status operacyjny imprezy</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">Stan przygotowań kluczowych elementów wyjazdu</p>
        </div>
    </div>
    
    <div class="flex-1 sm:flex sm:justify-end">
        <div class="flex flex-wrap items-center gap-2">
            @foreach ($items as $item)
                @php
                    $colorClass = match ($item['tone']) {
                        'success', 'ok' => 'bg-green-600 text-white shadow-sm ring-1 ring-inset ring-green-700/20 dark:bg-green-500 dark:ring-green-400/20',
                        'warning', 'warn', 'danger' => 'bg-red-600 text-white shadow-sm ring-1 ring-inset ring-red-700/20 dark:bg-red-500 dark:ring-red-400/20',
                        default => 'bg-gray-200 text-gray-800 shadow-sm ring-1 ring-inset ring-gray-600/20 dark:bg-gray-700 dark:text-gray-100 dark:ring-white/20',
                    };
                @endphp
                <span 
                    class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-[11px] uppercase tracking-wider font-bold {{ $colorClass }}"
                    title="{{ $item['title'] }}"
                >
                    <x-filament::icon :icon="$item['icon']" class="h-4 w-4" />
                    {{ $item['label'] }}: {{ $item['status_label'] }}
                </span>
            @endforeach
        </div>
    </div>
</div>
@endif
