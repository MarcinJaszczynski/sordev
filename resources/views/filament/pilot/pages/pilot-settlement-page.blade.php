<x-filament-panels::page>
    @include('filament.client.components.trip-hero', [
        'event' => $this->event,
        'kicker' => 'Rozliczenie',
    ])

    @if(app(\App\Services\PilotAccessService::class)->isPreviewReadOnly())
        <div class="mb-4 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-950">
            Podgląd tylko do odczytu — zapisy wydatków i dokumentów są wyłączone.
        </div>
    @endif

    <div class="client-portal-section !p-0 overflow-hidden">
        @livewire('pilot-trip-settlement-form', [
            'event' => $this->event,
            'showTripHeader' => false,
            'readOnly' => app(\App\Services\PilotAccessService::class)->isPreviewReadOnly(),
        ], key('pilot-settlement-'.$this->event->id))
    </div>
</x-filament-panels::page>
