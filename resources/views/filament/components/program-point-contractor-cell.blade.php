@php
    /** @var \App\Models\EventProgramPoint|null $record */
    $record = $getRecord();

    $contractor = null;
    $isSetParent = (bool) ($record?->getAttribute('_is_set_parent'));

    if ($record) {
        $contractor = $record->contractor;

        // Na secie nadrzędnym kontrahent = miejsce/punkt zborny — bez fallbacku z rezerwacji podpunktów.
        if (! $isSetParent && ! $contractor instanceof \App\Models\Contractor) {
            $reservation = $record->relationLoaded('reservations')
                ? $record->reservations->sortByDesc('id')->first()
                : $record->latestVisibleReservation();

            if ($reservation?->contractor instanceof \App\Models\Contractor) {
                $contractor = $reservation->contractor;
            } elseif ($record->relationLoaded('sharedReservation') && $record->sharedReservation?->contractor instanceof \App\Models\Contractor) {
                $contractor = $record->sharedReservation->contractor;
            }
        }
    }

    $meta = $contractor instanceof \App\Models\Contractor
        ? \App\Support\ContractorContactDetails::contractorMeta($contractor)
        : null;
    $phone = filled($meta['phone'] ?? null) ? (string) $meta['phone'] : null;
    $email = filled($meta['email'] ?? null) ? (string) $meta['email'] : null;
    $url = $contractor instanceof \App\Models\Contractor
        ? \App\Filament\Resources\ContractorResource::getUrl('edit', ['record' => $contractor])
        : null;
@endphp

@if ($contractor instanceof \App\Models\Contractor && filled($url))
    <a
        href="{{ $url }}"
        @class([
            'epp-contractor-cell',
            'epp-contractor-cell--link',
            'epp-contractor-cell--set-place' => $isSetParent,
        ])
        title="{{ $isSetParent ? 'Miejsce / punkt zborny setu' : 'Otwórz kartę kontrahenta' }}"
        x-on:click.stop
    >
        <span class="epp-contractor-cell__name">{{ $contractor->name }}</span>
        @if ($isSetParent)
            <span class="epp-contractor-cell__meta">miejsce</span>
        @elseif ($phone || $email)
            <span class="epp-contractor-cell__meta">
                @if ($phone)
                    <span>{{ $phone }}</span>
                @endif
                @if ($email)
                    <span>{{ $email }}</span>
                @endif
            </span>
        @endif
    </a>
@endif
