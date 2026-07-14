<x-filament-panels::page>
    @include('filament.components.finance-module-nav', ['tabs' => $this->getNavigationTabs()])

    <p class="mb-4 text-sm text-gray-600">
        Wpłaty od pojedynczych uczestników powiązane z umowami grupowymi i rozliczeniami imprez.
        Nowe wpłaty możesz też importować z wyciągu bankowego.
    </p>

    {{ $this->table }}
</x-filament-panels::page>
