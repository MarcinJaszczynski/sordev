<x-filament-panels::page>
    @include('filament.components.finance-module-nav', ['tabs' => $this->getNavigationTabs()])

    <x-filament::section class="mb-4" heading="Wnioski o fakturę z panelu klienta">
        <p class="text-sm text-gray-600">
            Klienci składają wnioski w portalu imprezy. Oznacz wniosek jako <strong>zrealizowany</strong>
            po wystawieniu faktury lub <strong>odrzuć</strong> z notatką dla zespołu.
        </p>
    </x-filament::section>

    {{ $this->table }}
</x-filament-panels::page>
