<x-filament-panels::page>
    @if(filled($archiveMessage))
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            {{ $archiveMessage }}
        </div>
    @endif

    @include('pilot.partials.trip-program-timeline', [
        'event' => $event,
        'financeHintsByPointId' => $financeHintsByPointId ?? [],
        'pilotSetFinanceCards' => $pilotSetFinanceCards ?? [],
        'programPointIds' => $programPointIds ?? collect(),
    ])
</x-filament-panels::page>
