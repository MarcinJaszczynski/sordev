<x-filament-panels::page>
    <div class="sor-design-preview space-y-8">
        <x-filament::section>
            <x-slot name="heading">Brand i logo</x-slot>
            <x-slot name="description">Pliki SVG w <code class="text-xs">public/images/</code> — dokumentacja w <code class="text-xs">docs/DESIGN_SYSTEM.md</code></x-slot>

            <div class="grid gap-6 md:grid-cols-2">
                <div class="sor-design-preview__logo-card">
                    <p class="sor-design-preview__label">Panel admin</p>
                    <img src="{{ asset('images/bprafa-logo.svg') }}" alt="bprafa" class="h-10 w-auto" />
                </div>
                <div class="sor-design-preview__logo-card">
                    <p class="sor-design-preview__label">Portal pilota</p>
                    <img src="{{ asset('images/bprafa-pilot-logo.svg') }}" alt="Portal pilota bprafa" class="h-10 w-auto" />
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Tokeny kolorów</x-slot>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                @foreach ($this->getColorTokens() as $color)
                    <div class="sor-design-preview__swatch">
                        <div class="sor-design-preview__swatch-color" style="background: {{ $color['hex'] }};"></div>
                        <div class="sor-design-preview__swatch-meta">
                            <span class="sor-design-preview__swatch-label">{{ $color['label'] }}</span>
                            <span class="sor-design-preview__swatch-hex">{{ $color['hex'] }}</span>
                            <code class="sor-design-preview__swatch-token">{{ $color['token'] }}</code>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Typografia</x-slot>

            <div class="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
                <p class="text-2xl font-bold text-gray-900">Nagłówek strony — Inter 700</p>
                <p class="text-base text-gray-700">Tekst podstawowy — czytelny, line-height 1.5, skala responsywna <code>--admin-body-size</code>.</p>
                <p class="text-sm font-semibold uppercase tracking-wide text-gray-500">Etykieta tabeli / sekcji</p>
                <p class="text-sm text-gray-500">Tekst pomocniczy i metadane</p>
                <p class="money-nowrap text-base font-semibold text-gray-900">12 345,67 PLN</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Przyciski i badge</x-slot>

            <div class="flex flex-wrap items-center gap-3">
                <x-filament::button color="primary">Primary</x-filament::button>
                <x-filament::button color="gray">Secondary</x-filament::button>
                <x-filament::button color="success">Success</x-filament::button>
                <x-filament::button color="warning">Warning</x-filament::button>
                <x-filament::button color="danger">Danger</x-filament::button>
                <x-filament::button color="info">Info</x-filament::button>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <x-filament::badge color="success">Opłacone</x-filament::badge>
                <x-filament::badge color="warning">Zaliczka</x-filament::badge>
                <x-filament::badge color="danger">Przeterminowane</x-filament::badge>
                <x-filament::badge color="info">W trakcie</x-filament::badge>
                <x-filament::badge color="gray">Szkic</x-filament::badge>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Grupy nawigacji — ikony i akcenty</x-slot>

            <div class="grid gap-3 md:grid-cols-2">
                @foreach ($this->getNavigationGroups() as $nav)
                    <div class="sor-design-preview__nav-chip {{ $nav['class'] }}">
                        <x-filament::icon :icon="$nav['icon']" class="h-5 w-5 shrink-0" />
                        <span>{{ $nav['group'] }}</span>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Puste stany (empty state)</x-slot>
            <x-slot name="description">Komponent <code class="text-xs">&lt;x-filament.components.sor-empty-state&gt;</code></x-slot>

            <div class="grid gap-4 lg:grid-cols-2">
                <x-filament.components.sor-empty-state variant="events">
                    <x-slot name="actions">
                        <x-filament::button size="sm" color="primary">Nowa impreza</x-filament::button>
                    </x-slot>
                </x-filament.components.sor-empty-state>

                <x-filament.components.sor-empty-state variant="finance" />

                <x-filament.components.sor-empty-state variant="program" />

                <x-filament.components.sor-empty-state variant="contacts" />
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Finanse — klasy pomocnicze</x-slot>

            <div class="space-y-2 text-sm">
                <p class="admin-finance-ok">Opłacone w całości — admin-finance-ok</p>
                <p class="admin-finance-warn">Zaległość / zaliczka — admin-finance-warn</p>
                <p class="admin-finance-muted">Metadane i kwoty pomocnicze — admin-finance-muted</p>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
