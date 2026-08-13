<x-filament-panels::page>
    @include('filament.client.components.trip-hero', [
        'event' => $event,
        'kicker' => 'Program',
    ])

    @if(filled($archiveMessage))
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            {{ $archiveMessage }}
        </div>
    @endif

    <div class="client-portal-section !p-0 overflow-hidden bg-transparent border-0 shadow-none">
        @include('pilot.partials.trip-program-timeline', [
            'event' => $event,
            'financeHintsByPointId' => $financeHintsByPointId ?? [],
            'settlementUrl' => \App\Filament\Pilot\Pages\PilotSettlementPage::settleUrl($event),
        ])
    </div>
</x-filament-panels::page>
