<x-filament-panels::page>
    @include('filament.client.components.trip-hero', [
        'event' => $event,
        'kicker' => 'Program',
    ])

    @if(filled($archiveMessage))
        <div class="mb-4 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-950">
            {{ $archiveMessage }}
        </div>
    @endif

    <div class="mb-3">
        <p class="client-portal-kicker">Harmonogram</p>
        <h2 class="mt-1 text-lg font-semibold text-slate-900">Program wycieczki</h2>
    </div>

    @include('client.partials.trip-program-timeline', [
        'event' => $event,
        'programPoints' => $programPoints,
        'coverUrl' => null,
        'hideHero' => true,
        'hideTimes' => true,
    ])
</x-filament-panels::page>
