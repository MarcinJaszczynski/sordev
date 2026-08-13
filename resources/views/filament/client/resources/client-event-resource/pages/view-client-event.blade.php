<x-filament-panels::page>
    @include('filament.client.components.trip-hero', [
        'event' => $this->record->loadMissing(['eventTemplate', 'startPlace']),
        'kicker' => 'Informacje',
    ])

    @include('filament.client.components.trip-readiness', [
        'items' => $readiness ?? [],
        'contactUrl' => $contactUrl ?? null,
    ])

    <div class="client-portal-section">
        {{ $this->infolist }}
    </div>
</x-filament-panels::page>
