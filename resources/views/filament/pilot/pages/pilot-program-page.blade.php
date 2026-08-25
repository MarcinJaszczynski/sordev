<x-filament-panels::page>
    @include('filament.client.components.trip-hero', [
        'event' => $event,
        'kicker' => 'Program',
    ])

    @if(filled($archiveMessage))
        <div class="portal-notice portal-notice--amber">
            {{ $archiveMessage }}
        </div>
    @endif

    @include('pilot.partials.trip-program-timeline', [
        'event' => $event,
        'financeHintsByPointId' => $financeHintsByPointId ?? [],
        'settlementUrl' => \App\Filament\Pilot\Pages\PilotSettlementPage::settleUrl($event),
    ])
</x-filament-panels::page>
