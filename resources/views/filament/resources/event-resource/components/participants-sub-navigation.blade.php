@php
    $activeTab = $this::participantsSubNavigationActiveTab();
    $tabs = $this::participantsSubNavigationTabs($record->getKey());
@endphp

@if ($tabs !== [])
    <p class="mb-3 text-sm text-gray-600 dark:text-gray-400">
        Sekcja dotyczy wyłącznie uczestników imprezy
        <span class="font-medium text-gray-900 dark:text-white">{{ $record->code ?: '#'.$record->getKey() }}</span>
        @if (filled($record->name))
            — {{ $record->name }}
        @endif
    </p>

    <x-filament::tabs class="mb-6">
        @foreach ($tabs as $tab)
            <x-filament::tabs.item
                :active="$activeTab === $tab['key']"
                :href="$tab['url']"
                :icon="$tab['icon']"
                tag="a"
            >
                {{ $tab['label'] }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>
@endif
