<x-filament-panels::page>
    @include('filament.components.finance-module-nav', ['tabs' => $this->getNavigationTabs()])

    <x-filament::section class="mb-4" heading="Faktury bez przypisania">
        <p class="text-sm text-gray-600 dark:text-gray-300">
            Tutaj trafiają dokumenty z importu KSeF, których system nie powiązał automatycznie z imprezą.
            Użyj <strong>Podgląd</strong> (pozycje, notatki, hinty) albo <strong>Przypisz</strong>
            (impreza / punkt programu / kontrahent).
        </p>
    </x-filament::section>

    {{ $this->table }}
</x-filament-panels::page>
