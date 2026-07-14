<x-filament-panels::page>
    @php
        $hotelServicesRm = \App\Filament\Resources\EventResource\RelationManagers\EventHotelServicesRelationManager::class;
    @endphp

    <div class="space-y-6">
        @livewire('settlement-aggregate-finance-panel', [
            'eventId' => $this->record->id,
            'aggregateType' => 'accommodation',
            'heading' => 'Finanse noclegu',
        ], key('accommodation-finance-'.$this->record->id))

        @livewire('event-hotel-plan-editor', ['eventId' => $this->record->id], key('event-hotel-plan-'.$this->record->id))

        @livewire($hotelServicesRm, [
            'ownerRecord' => $this->record,
            'pageClass' => \App\Filament\Resources\EventResource\Pages\EventHotelPlanning::class,
        ], key('event-hotel-services-'.$this->record->id))

        @livewire('event-hotel-documents-panel', ['eventId' => $this->record->id], key('event-hotel-documents-'.$this->record->id))

        @livewire('event-hotel-correspondence-panel', ['eventId' => $this->record->id], key('event-hotel-correspondence-'.$this->record->id))

        <x-filament::section>
            <x-slot name="heading">Karteczki — ustalenia hotelowe</x-slot>
            <x-slot name="description">Szybkie notatki operacyjne widoczne dla zespołu (stos z historią).</x-slot>
            @include('filament.components.sticky-notes-stack', [
                'notableType' => \App\Models\Event::class,
                'notableId' => $this->record->id,
                'title' => 'Karteczki hotelu / imprezy',
                'compact' => true,
                'filterCategory' => \App\Support\StickyNotes\StickyNoteCategory::HOTEL,
            ])
        </x-filament::section>
    </div>
</x-filament-panels::page>
