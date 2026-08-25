<x-filament-panels::page>
    @include('filament.client.components.trip-hero', [
        'event' => $event,
        'kicker' => 'Program',
    ])

    @if(filled($archiveMessage))
        <div class="portal-notice portal-notice--accent">
            {{ $archiveMessage }}
        </div>
    @endif

    @include('client.partials.trip-program-timeline', [
        'event' => $event,
        'programPoints' => $programPoints,
        'coverUrl' => null,
        'hideHero' => true,
        'hideTimes' => true,
    ])
</x-filament-panels::page>
