<div class="fi-resource-relation-manager flex flex-col gap-y-6">
    @livewire(
        \App\Filament\Resources\EventResource\Widgets\EventPriceTable::class,
        ['record' => $record],
        key('event-price-table-tab-' . $record->getKey())
    )
</div>
