<x-filament-panels::page>
    @include('filament.client.components.trip-hero', [
        'event' => $event,
        'kicker' => 'Checklista i czynności',
    ])

    @if(filled($archiveMessage) && $readOnly)
        <div class="portal-notice portal-notice--amber">
            {{ $archiveMessage }}
        </div>
    @endif

    <div class="portal-card !p-0 overflow-hidden mb-4">
        <div class="px-4 py-3 border-b border-[#E5E3DA]">
            <div class="portal-card-title !mb-0">
                <p>Dane z wycieczki</p>
            </div>
        </div>
        <div class="p-4">
            @livewire('pilot-trip-settlement-form', [
                'event' => $event,
                'showTripHeader' => false,
                'readOnly' => $readOnly,
                'showTripData' => true,
                'showFinanceSections' => false,
            ], key('pilot-checklist-trip-data-'.$event->id))
        </div>
    </div>

    <div class="portal-card !p-0 overflow-hidden">
        <div class="px-4 py-3 border-b border-[#E5E3DA]">
            <div class="portal-card-title !mb-0">
                <p>Checklista i czynności</p>
            </div>
        </div>
        <div class="p-4">
            @livewire('pilot-event-checklist', ['eventId' => $event->id, 'forcePilotView' => true], key('pilot-checklist-'.$event->id))
        </div>
    </div>
</x-filament-panels::page>
