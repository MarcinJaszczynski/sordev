<x-filament-panels::page
    @class([
        'fi-resource-edit-record-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
        'fi-resource-record-' . $record->getKey(),
    ])
>
    @include('filament.resources.event-resource.components.operations-sub-navigation', ['record' => $record])

    <div class="space-y-6">
        {{-- Panel finansów poza formularzem EditRecord — nested Livewire w Placeholderze psuje akcje Filament. --}}
        @livewire('settlement-aggregate-finance-panel', [
            'eventId' => $this->record->getKey(),
            'aggregateType' => 'transport',
            'heading' => 'Finanse transportu',
        ], key('transport-finance-'.$this->record->getKey()))

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
    </div>

    <x-filament-panels::page.unsaved-data-changes-alert />
</x-filament-panels::page>
