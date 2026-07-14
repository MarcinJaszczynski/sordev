<x-filament-panels::page>
    @include('filament.components.finance-module-nav', ['tabs' => $this->getNavigationTabs()])

    <x-filament::section>
        <x-slot name="heading">Paczka dokumentów dla księgowej</x-slot>
        <x-slot name="description">
            Pobierz ZIP z manifestem CSV, fakturami kosztowymi (PDF), zestawieniem umów przychodowych
            oraz skanami dokumentów z rozliczenia imprezy.
        </x-slot>

        <form wire:submit.prevent="downloadArchive" class="max-w-xl space-y-4">
            {{ $this->form }}

            <div class="flex flex-wrap gap-3">
                <x-filament::button type="submit" icon="heroicon-o-arrow-down-tray">
                    Pobierz teczkę ZIP
                </x-filament::button>

                <x-filament::button
                    type="button"
                    color="gray"
                    icon="heroicon-o-document-duplicate"
                    wire:click="downloadInvoicesPdf"
                >
                    Wszystkie faktury (PDF)
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>
</x-filament-panels::page>
