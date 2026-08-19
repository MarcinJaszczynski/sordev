@php
    /** @var \App\Models\EventProgramPoint $record */
    $inProgram = (bool) $record->include_in_program;
    $inCalc = (bool) $record->include_in_calculation;
    $reservation = $record->relationLoaded('reservations')
        ? $record->reservations->sortByDesc('id')->first()
        : $record->latestVisibleReservation();
    $reservationStatus = $reservation?->status;
    $reservationLabel = $reservation
        ? (\App\Models\Reservation::$statuses[$reservationStatus] ?? $reservationStatus)
        : null;
@endphp

<div class="epp-scope" title="Program = oferta/PDF. Kalkulacja = kosztorys i rozliczenie.">
    <span @class([
        'epp-scope-chip',
        'epp-scope-chip--program-on' => $inProgram,
        'epp-scope-chip--program-off' => ! $inProgram,
    ])>{{ $inProgram ? 'Program' : 'Poza programem' }}</span>
    <span @class([
        'epp-scope-chip',
        'epp-scope-chip--calc-on' => $inCalc,
        'epp-scope-chip--calc-off' => ! $inCalc,
    ])>{{ $inCalc ? 'Kalkulacja' : 'Poza kalk.' }}</span>
    @if ($reservation && ! in_array((string) $reservationStatus, ['cancelled', 'not_required'], true))
        <span @class([
            'epp-scope-chip',
            'epp-scope-chip--rez-ok' => in_array((string) $reservationStatus, ['confirmed', 'completed', 'partially_confirmed'], true),
            'epp-scope-chip--rez-pending' => ! in_array((string) $reservationStatus, ['confirmed', 'completed', 'partially_confirmed'], true),
        ]) title="{{ $reservationLabel }}">{{ $reservationLabel }}</span>
    @endif
</div>
