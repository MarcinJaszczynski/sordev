<x-filament-panels::page>
    @include('filament.resources.event-resource.components.operations-sub-navigation', ['record' => $this->record])

    @php
        $hotelServicesRm = \App\Filament\Resources\EventResource\RelationManagers\EventHotelServicesRelationManager::class;
        $occupancy = $this->occupancySummary();
    @endphp

    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Zajętość (occupancy)</x-slot>
            <x-slot name="description">Ile osób wymaga noclegu na każdą noc — oraz postęp wypełniania planu hotelowego.</x-slot>

            <div class="grid gap-4 grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Uczestnicy</div>
                    <div class="text-lg font-semibold">{{ $occupancy['participants'] }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ \App\Support\EventParticipantGroupLabels::GRATIS }}</div>
                    <div class="text-lg font-semibold">{{ $occupancy['gratis'] }}</div>
                </div>
                @if ($occupancy['staff'] > 0)
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Obsługa</div>
                        <div class="text-lg font-semibold">{{ $occupancy['staff'] }}</div>
                    </div>
                @endif
                @if ($occupancy['pilot'] > 0)
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Pilot</div>
                        <div class="text-lg font-semibold">{{ $occupancy['pilot'] }}</div>
                    </div>
                @endif
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Kierowcy</div>
                    <div class="text-lg font-semibold">{{ $occupancy['drivers'] }}</div>
                </div>
                <div class="col-span-2 sm:col-span-1">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Potrzebne miejsca (na 1 nocleg)</div>
                    <div class="text-lg font-semibold">{{ $occupancy['required_beds_per_night'] }}</div>
                </div>
            </div>

            @if (count($occupancy['stays']) > 0)
                <div class="mt-4 overflow-x-auto">
                    <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">Plan hotelowy — miejsca w pokojach vs przypisani (osobno dla każdej nocy).</p>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700 text-left">
                                <th class="py-2 pr-3 font-medium">Obiekt</th>
                                <th class="py-2 pr-3 font-medium">Dzień</th>
                                <th class="py-2 pr-3 font-medium">Miejsca w pokojach</th>
                                <th class="py-2 pr-3 font-medium">Przypisani</th>
                                <th class="py-2 pr-3 font-medium">Wolne</th>
                                <th class="py-2 font-medium">Zajętość</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($occupancy['stays'] as $stay)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="py-2 pr-3">{{ $stay['label'] }}</td>
                                    <td class="py-2 pr-3">
                                        {{ $stay['day'] ?? '—' }}
                                        @if (! empty($stay['date_from']))
                                            <span class="text-gray-500">({{ $stay['date_from'] }})</span>
                                        @endif
                                    </td>
                                    <td class="py-2 pr-3">{{ $stay['beds'] }}</td>
                                    <td class="py-2 pr-3">{{ $stay['assigned'] }}</td>
                                    <td class="py-2 pr-3">{{ $stay['free'] }}</td>
                                    <td class="py-2">{{ number_format($stay['percent'], 1) }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        @livewire('event-hotel-stays-finance-panel', [
            'eventId' => $this->record->id,
        ], key('hotel-stays-finance-'.$this->record->id))

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
