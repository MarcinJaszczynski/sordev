@php
    $activeTab = $this::participantsSubNavigationActiveTab();
    $tabs = $this::participantsSubNavigationTabs($record->getKey());
@endphp

@if ($tabs !== [])
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
