<x-filament-panels::page
    @class([
        'fi-resource-edit-record-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
        'fi-resource-record-' . $record->getKey(),
    ])
>
    @include('filament.resources.event-resource.components.operations-sub-navigation', ['record' => $record])

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

    <div class="mt-8 space-y-8">
        <x-filament::section
            icon="heroicon-o-clipboard-document-check"
            heading="Checklista"
            description="Biuro wybiera szablon; pilot tylko odznacza punkty."
        >
            <div class="mb-3">
                <a
                    href="{{ \App\Filament\Resources\ChecklistTemplateResource::getUrl('index') }}"
                    target="_blank"
                    rel="noopener"
                    class="text-sm font-medium text-primary-600 hover:underline"
                >Szablony checklisty →</a>
            </div>
            @livewire('pilot-event-checklist', ['eventId' => $record->id], key('admin-pilot-checklist-'.$record->id))
        </x-filament::section>

        <div id="pilot-cash-desk" tabindex="-1">
            <x-filament::section
                icon="heroicon-o-banknotes"
                heading="Rozliczenie — gotówka i wymiana walut"
                description="Tu zmieniasz wypłacone kwoty, robisz dopłatę lub dodajesz walutę (Edytuj / Usuń / Zapisz wypłatę). Te same formularze co w Finanse → Gotówka dla pilota. Przełączniki „Portal: wymiana / zbiórka” chowają odpowiednie sekcje tak jak w portalu pilota."
            >
                @livewire('pilot-cash-desk', [
                    'event' => $record,
                    'context' => 'admin',
                    'compact' => false,
                    'respectPortalVisibility' => true,
                ], key('admin-pilot-cash-'.$record->id))
            </x-filament::section>
        </div>

        @if ($record->showsPilotBusCollections())
            <div id="bus-collections">
                <x-filament::section
                    icon="heroicon-o-banknotes"
                    heading="Zbiórki w autokarze"
                >
                    @livewire('event-bus-collections', ['event' => $record], key('admin-bus-collections-'.$record->id))
                </x-filament::section>
            </div>
        @endif
    </div>

    <x-filament-panels::page.unsaved-data-changes-alert />
</x-filament-panels::page>
