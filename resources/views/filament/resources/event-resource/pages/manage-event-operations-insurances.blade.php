<x-filament-panels::page
    @class([
        'fi-resource-edit-record-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
        'fi-resource-record-' . $record->getKey(),
    ])
>
    <div class="mb-4 text-sm text-gray-600 dark:text-gray-300">
        Każda pozycja to produkt na dzień (koszty NNW/KL). „Dodaj ubezpieczenia” tworzy polisę
        (nr, pliki, kwota) razem z produktami — impreza może mieć kilka polis.
        Klik w wiersz lub „Płatności” otwiera panel wpłat (jak w Finanse → Koszty).
        Gotowość operacyjna = wszystkie pozycje opłacone w kolumnie „Płatność”.
    </div>

    <div class="mt-2">
        @php
            $relationManagers = $this->getRelationManagers();
        @endphp

        @if (count($relationManagers))
            <x-filament-panels::resources.relation-managers
                :active-manager="array_key_first($relationManagers)"
                :managers="$relationManagers"
                :owner-record="$record"
                :page-class="static::class"
            />
        @endif
    </div>
</x-filament-panels::page>
