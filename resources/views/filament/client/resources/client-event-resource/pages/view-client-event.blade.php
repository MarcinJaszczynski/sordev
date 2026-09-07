<x-filament-panels::page>
    @include('filament.client.components.trip-hero', [
        'event' => $this->record->loadMissing(['eventTemplate', 'startPlace']),
        'kicker' => 'Informacje',
    ])

    @include('filament.client.components.trip-readiness', [
        'items' => $readiness ?? [],
        'contactUrl' => $contactUrl ?? null,
    ])

    @include('filament.client.partials.trip-info-cards', [
        'event' => $this->record,
    ])
</x-filament-panels::page>
