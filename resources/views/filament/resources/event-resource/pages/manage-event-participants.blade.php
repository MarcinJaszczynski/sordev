<x-filament-panels::page>
    @include('filament.resources.event-resource.components.participants-sub-navigation', ['record' => $record])

    @livewire('event-participant-list-editor', ['eventId' => $this->record->id], key('event-participants-'.$this->record->id))
</x-filament-panels::page>
