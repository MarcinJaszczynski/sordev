@php
    $tabs = \App\Support\ClientTripModuleNavigation::tabs($event, $activeTab ?? null);
@endphp

<nav class="client-portal-trip-nav" aria-label="Nawigacja wycieczki">
    <div class="client-portal-trip-nav__list">
        @foreach ($tabs as $tab)
            <a
                href="{{ $tab['url'] }}"
                @class([
                    'client-portal-trip-nav__item',
                    'is-active' => ! empty($tab['active']),
                ])
            >
                @if (! empty($tab['icon']))
                    <x-filament::icon :icon="$tab['icon']" class="h-4 w-4 shrink-0" />
                @endif
                <span>{{ $tab['label'] }}</span>
            </a>
        @endforeach
    </div>
</nav>
