<x-filament-panels::page>
    @include('filament.client.components.trip-hero', [
        'event' => $this->record->loadMissing(['eventTemplate', 'startPlace']),
        'kicker' => 'Informacje',
    ])

    <div class="client-portal-section">
        {{ $this->infolist }}
    </div>
</x-filament-panels::page>
