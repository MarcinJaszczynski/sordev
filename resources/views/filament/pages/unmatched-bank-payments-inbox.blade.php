<x-filament-panels::page>
    @include('filament.components.finance-module-nav', ['tabs' => $this->getNavigationTabs()])

    <x-filament::section class="mb-4" heading="Przelewy bez przypisania">
        <p class="text-sm text-gray-600 dark:text-gray-300">
            Tutaj trafiają wpływy z importu Millennium, których system nie powiązał automatycznie
            z umową, uczestnikiem ani zaliczką pilota. Użyj <strong>Przypisz</strong>, wskaż cel
            i opcjonalnie zaksięguj od razu. Możesz też wrócić do
            <a href="{{ \App\Filament\Pages\BankPaymentImportPage::getUrl() }}" class="text-primary-600 underline">Importu wpłat</a>
            i zmienić dopasowanie w podglądzie batcha.
        </p>
    </x-filament::section>

    {{ $this->table }}

    @include('filament.components.bank-payment-assign-modal')
</x-filament-panels::page>
