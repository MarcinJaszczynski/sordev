<x-filament-panels::page
    @class([
        'fi-resource-edit-record-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
        'fi-resource-record-' . $record->getKey(),
    ])
>
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
            heading="Checklista pilota"
            description="Biuro wybiera szablon i dodaje punkty niestandardowe. Pilot je tylko odznacza."
        >
            @livewire('pilot-event-checklist', ['eventId' => $record->id], key('admin-pilot-checklist-'.$record->id))
        </x-filament::section>

        <x-filament::section
            icon="heroicon-o-banknotes"
            heading="Zbiórki w autokarze"
            description="Zaliczki pobrane przez pilota od uczestników w autokarze."
        >
            @livewire('event-bus-collections', ['event' => $record], key('admin-bus-collections-'.$record->id))
        </x-filament::section>
    </div>

    <x-filament-panels::page.unsaved-data-changes-alert />
</x-filament-panels::page>
