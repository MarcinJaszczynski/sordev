<x-filament-panels::page>
    @include('filament.client.components.trip-hero', [
        'event' => $this->record->loadMissing(['eventTemplate', 'startPlace']),
        'kicker' => 'Informacje',
    ])

    @include('filament.pilot.partials.trip-info-cards', [
        'event' => $this->record,
    ])
</x-filament-panels::page>
