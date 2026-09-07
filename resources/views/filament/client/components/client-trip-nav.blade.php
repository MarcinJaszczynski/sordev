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
                <span>{{ $tab['label'] }}</span>
            </a>
        @endforeach
    </div>
</nav>
