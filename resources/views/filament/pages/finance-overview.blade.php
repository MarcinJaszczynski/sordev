<x-filament-panels::page>
    @include('filament.components.finance-module-nav', ['activeTab' => 'overview'])

    @php($stats = $this->getOverviewStats())
    @php($urls = $stats['urls'])

    <div class="mb-4 rounded-lg border border-primary-200 bg-primary-50/60 px-4 py-3 text-sm text-gray-700 dark:border-primary-500/30 dark:bg-primary-500/10 dark:text-gray-200">
        <p class="font-medium text-gray-900 dark:text-white">Pulpit finansowy</p>
        <p class="mt-1 text-gray-600 dark:text-gray-300">
            Szybki podgląd otwartych rozliczeń, wypłat pilota i płatności wymagających uwagi. Kliknij kafelek, aby przejść do listy.
        </p>
    </div>

    <div class="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <a href="{{ $urls['open_settlements'] }}" class="block rounded-xl transition hover:ring-2 hover:ring-amber-300 focus:outline-none focus:ring-2 focus:ring-amber-400" title="Imprezy z otwartym rozliczeniem (szkic, aktywne, po pilocie).">
            <x-filament::section class="!p-4 h-full">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Otwarte rozliczenia</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ $stats['open_settlements'] }}</p>
                <p class="mt-1 text-xs text-gray-500">Szkice, aktywne i po pilocie — kliknij, aby otworzyć</p>
            </x-filament::section>
        </a>

        <a href="{{ $urls['unpaid_pilot_funds'] }}" class="block rounded-xl transition hover:ring-2 hover:ring-amber-300 focus:outline-none focus:ring-2 focus:ring-amber-400" title="Imprezy, w których pilot ma jeszcze wypłacić zaliczkę.">
            <x-filament::section class="!p-4 h-full {{ $stats['unpaid_pilot_funds'] > 0 ? 'ring-1 ring-danger-200' : '' }}">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Zaliczki pilota — do wypłaty</p>
                <p class="mt-1 text-2xl font-bold {{ $stats['unpaid_pilot_funds'] > 0 ? 'text-danger-600' : 'text-success-600' }}">
                    {{ $stats['unpaid_pilot_funds'] }}
                </p>
                <p class="mt-1 text-xs text-gray-500">
                    Plan: {{ $stats['planned_pilot_advances_label'] }} — lista imprez bez wypłaty
                </p>
            </x-filament::section>
        </a>

        <a href="{{ $urls['pending_payments'] }}" class="block rounded-xl transition hover:ring-2 hover:ring-amber-300 focus:outline-none focus:ring-2 focus:ring-amber-400" title="Terminy płatności kosztów biura i pilota wymagające reakcji.">
            <x-filament::section class="!p-4 h-full">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Skrzynka płatności</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">→</p>
                <p class="mt-1 text-xs text-gray-500">Terminy płatności biura i pilota</p>
            </x-filament::section>
        </a>

        <a href="{{ $urls['unmatched'] }}" class="block rounded-xl transition hover:ring-2 hover:ring-amber-300 focus:outline-none focus:ring-2 focus:ring-amber-400" title="Faktury kosztowe jeszcze bez przypisania do imprezy.">
            <x-filament::section class="!p-4 h-full">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Faktury do opracowania</p>
                <p class="mt-1 text-2xl font-bold text-warning-600">{{ $stats['unmatched'] }}</p>
                <p class="mt-1 text-xs text-gray-500">Bez przypisanej imprezy</p>
            </x-filament::section>
        </a>

        <a href="{{ $urls['due'] }}" class="block rounded-xl transition hover:ring-2 hover:ring-amber-300 focus:outline-none focus:ring-2 focus:ring-amber-400">
            <x-filament::section class="!p-4 h-full">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Faktury do zapłaty</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ $stats['due'] }}</p>
                <p class="mt-1 text-xs text-gray-500">Zaakceptowane, nieopłacone</p>
            </x-filament::section>
        </a>

        <a href="{{ $urls['overdue'] }}" class="block rounded-xl transition hover:ring-2 hover:ring-amber-300 focus:outline-none focus:ring-2 focus:ring-amber-400">
            <x-filament::section class="!p-4 h-full {{ $stats['overdue'] > 0 ? 'ring-1 ring-danger-200' : '' }}">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Przeterminowane</p>
                <p class="mt-1 text-2xl font-bold {{ $stats['overdue'] > 0 ? 'text-danger-600' : 'text-gray-900' }}">
                    {{ $stats['overdue'] }}
                </p>
                <p class="mt-1 text-xs text-gray-500">Po terminie płatności</p>
            </x-filament::section>
        </a>
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
                :href="$urls['unpaid_pilot_funds']"
                color="danger"
                icon="heroicon-o-user-circle"
            >
                Imprezy bez wypłaty pilota
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-panels::page>
