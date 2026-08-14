<x-filament-panels::page
    @class([
        'fi-resource-edit-record-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
        'fi-resource-record-' . $record->getKey(),
    ])
>
    @include('filament.resources.event-resource.components.operations-sub-navigation', ['record' => $record])

    <div class="mb-4 text-sm text-gray-600 dark:text-gray-300">
        Przypisanie dnia = pozycja planu w kosztorysie (NNW / KL), domyślnie status „Planowane”.
        Klik w wiersz lub „Płatności” otwiera ten sam boczny panel co w Impreza → Finanse → Koszty (wpłaty, plan, dokumenty).
        Plik polisy i oryginalna lista ubezpieczonych z bloku powyżej są widoczne dla pilota
        (panel Dokumenty + pakiet PDF). Polisa synchronizuje się też do Finansów (kolumna Dok. / dokumenty pozycji ubezpieczenia).
        Gotowość imprezy ustawiasz w bloku polisy powyżej (status „Gotowe” lub przełącznik).
    </div>

    @capture($form)
        <x-filament-panels::form
            id="form"
            :wire:key="$this->getId() . '.forms.' . $this->getFormStatePath()"
            wire:submit="save"
        >
            {{ $this->form }}

            <x-filament-panels::form.actions
                :actions="$this->getCachedFormActions()"
                :full-width="$this->hasFullWidthFormActions()"
            />
        </x-filament-panels::form>
    @endcapture

    {{ $form() }}

    <div class="mt-8">
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
