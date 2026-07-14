<x-filament-panels::page>
    @include('filament.components.finance-module-nav', ['tabs' => $this->getNavigationTabs()])

    @php($stats = $this->getOverviewStats())

    <div class="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-filament::section class="!p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Otwarte rozliczenia</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">{{ $stats['open_settlements'] }}</p>
            <p class="mt-1 text-xs text-gray-500">Szkice, aktywne i po pilocie</p>
        </x-filament::section>

        <x-filament::section class="!p-4 {{ $stats['unpaid_pilot_funds'] > 0 ? 'ring-1 ring-danger-200' : '' }}">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Zaliczki pilota — do wypłaty</p>
            <p class="mt-1 text-2xl font-bold {{ $stats['unpaid_pilot_funds'] > 0 ? 'text-danger-600' : 'text-success-600' }}">
                {{ $stats['unpaid_pilot_funds'] }}
            </p>
            <p class="mt-1 text-xs text-gray-500">Zaplanowane, bez zatwierdzonej wypłaty</p>
        </x-filament::section>

        <x-filament::section class="!p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Plan zaliczek (PLN)</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">
                {{ number_format((float) ($stats['planned_pilot_advances'] ?? 0), 2, ',', ' ') }}
            </p>
            <p class="mt-1 text-xs text-gray-500">Suma zaplanowanych, niewypłaconych zaliczek</p>
        </x-filament::section>

        <x-filament::section class="!p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Faktury do opracowania</p>
            <p class="mt-1 text-2xl font-bold text-warning-600">{{ $stats['unmatched'] }}</p>
            <p class="mt-1 text-xs text-gray-500">Bez przypisanej imprezy</p>
        </x-filament::section>

        <x-filament::section class="!p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Faktury do zapłaty</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">{{ $stats['due'] }}</p>
            <p class="mt-1 text-xs text-gray-500">Zaakceptowane, nieopłacone</p>
        </x-filament::section>

        <x-filament::section class="!p-4 {{ $stats['overdue'] > 0 ? 'ring-1 ring-danger-200' : '' }}">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Przeterminowane</p>
            <p class="mt-1 text-2xl font-bold {{ $stats['overdue'] > 0 ? 'text-danger-600' : 'text-gray-900' }}">
                {{ $stats['overdue'] }}
            </p>
            <p class="mt-1 text-xs text-gray-500">Po terminie płatności</p>
        </x-filament::section>
    </div>

    <x-filament::section heading="Typowy przepływ pracy" class="mb-8">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($this->getWorkflowSteps() as $step)
                <a
                    href="{{ $step['url'] }}"
                    class="group rounded-xl border border-gray-200 bg-white p-4 transition hover:border-amber-300 hover:shadow-sm"
                >
                    <div class="mb-2 flex h-7 w-7 items-center justify-center rounded-full bg-amber-100 text-sm font-bold text-amber-800">
                        {{ $step['step'] }}
                    </div>
                    <h3 class="text-sm font-semibold text-gray-900 group-hover:text-amber-800">{{ $step['title'] }}</h3>
                    <p class="mt-1 text-xs leading-relaxed text-gray-600">{{ $step['body'] }}</p>
                </a>
            @endforeach
        </div>
    </x-filament::section>

    <x-filament::section heading="Szybkie przejścia">
        <div class="flex flex-wrap gap-2">
            @foreach ($this->getNavigationTabs() as $tab)
                @continue($tab['key'] === 'overview')
                <x-filament::button tag="a" :href="$tab['url']" color="gray" icon="{{ $tab['icon'] }}">
                    {{ $tab['label'] }}
                </x-filament::button>
            @endforeach
            <x-filament::button
                tag="a"
                :href="route('filament.admin.resources.events.index', ['tableFilters' => ['pilot_funds_paid' => ['value' => '0']]])"
                color="danger"
                icon="heroicon-o-user-circle"
            >
                Imprezy bez wypłaty pilota
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-panels::page>
