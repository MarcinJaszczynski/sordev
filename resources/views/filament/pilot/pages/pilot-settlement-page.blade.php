<x-filament-panels::page>
    @include('filament.client.components.trip-hero', [
        'event' => $this->event,
        'kicker' => 'Rozliczenie',
    ])

    @if(app(\App\Services\PilotAccessService::class)->isPreviewReadOnly())
        <div class="portal-notice portal-notice--accent">
            Podgląd tylko do odczytu — zapisy wydatków i dokumentów są wyłączone.
        </div>
    @endif

    <div class="portal-card !p-0 overflow-hidden">
        @livewire('pilot-trip-settlement-form', [
            'event' => $this->event,
            'showTripHeader' => false,
            'readOnly' => app(\App\Services\PilotAccessService::class)->isPreviewReadOnly(),
            'showTripData' => false,
            'showFinanceSections' => true,
        ], key('pilot-settlement-'.$this->event->id))
    </div>
</x-filament-panels::page>
