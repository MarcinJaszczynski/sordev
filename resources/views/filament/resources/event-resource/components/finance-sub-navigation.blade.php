@php
    $activeTab = $this::financeSubNavigationActiveTab();
    $tabs = $this::financeSubNavigationTabs($record->getKey());
@endphp

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
