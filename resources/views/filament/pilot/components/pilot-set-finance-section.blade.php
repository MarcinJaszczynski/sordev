@php
    /** @var \App\Models\Event $record */
    use App\Services\EventProgramPointOrderService;
    use App\Services\PilotSetFinanceDisplay;

    $record = $getRecord();

    $programPointIds = app(EventProgramPointOrderService::class)
        ->pilotProgramPoints($record)
        ->pluck('id')
        ->map(fn ($id): int => (int) $id);

    $cards = array_values(app(PilotSetFinanceDisplay::class)->cardsForEvent($record, $programPointIds));
@endphp

@if($cards === [])
    <p class="text-sm text-gray-500 dark:text-gray-400">Brak setów z kosztami rozliczenia.</p>
@else
    <div class="space-y-3">
        @foreach($cards as $card)
            @include('pilot.partials.pilot-set-finance-card', ['card' => $card])
        @endforeach
    </div>
@endif
