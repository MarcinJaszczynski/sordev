<x-filament-panels::page>
    @include('filament.resources.event-resource.components.finance-sub-navigation', ['record' => $record])

    <div class="mb-3 text-sm text-gray-600 dark:text-gray-300">
        Wypłata gotówki (kwota / waluta / data), saldo, wymiany, wydatki pilota i zwrot — ten sam ekran co w panelu pilota.
    </div>

    @livewire('pilot-cash-desk', [
        'event' => $record,
        'context' => 'admin',
        'compact' => false,
    ], key('pilot-cash-desk-'.$record->id.'-'.$settlement->id))
</x-filament-panels::page>
