@php
    /** @var \App\Models\EventProgramPoint|null $record */
    $record = $getRecord();

    $tone = 'neutral';
    $label = '—';
    $title = null;
    $deadlineLine = null;
    $deadlineTone = 'muted';

    if ($record && ! $record->getAttribute('_is_set_parent')) {
        $reservation = $record->relationLoaded('reservations')
            ? $record->reservations->sortByDesc('id')->first()
            : $record->latestVisibleReservation();

        if (! $reservation) {
            $tone = 'danger';
            $label = 'Brak';
            $title = 'Brak rezerwacji';
        } else {
            $status = (string) $reservation->status;
            $fullLabel = \App\Models\Reservation::$statuses[$status] ?? $status;
            $title = $fullLabel;
            $isConfirmed = in_array($status, ['confirmed', 'completed', 'partially_confirmed'], true);

            if ($status === 'not_required') {
                $tone = 'neutral';
                $label = '—';
            } elseif ($status === 'cancelled') {
                $tone = 'danger';
                $label = 'Anulowana';
            } elseif ($status === 'confirmed' || $status === 'completed') {
                $tone = 'success';
                $label = 'Potwierdzona';
            } elseif ($status === 'partially_confirmed') {
                $tone = 'warning';
                $label = 'Częściowo';
            } else {
                $tone = 'warning';
                $label = 'Oczekuje';
            }

            if ($isConfirmed && filled($reservation->confirmed_at)) {
                $deadlineLine = 'Potwierdzono '.$reservation->confirmed_at->format('d.m.Y');
                $deadlineTone = 'ok';
            } elseif (! $isConfirmed && $status !== 'cancelled' && $status !== 'not_required') {
                $confirmBy = $reservation->confirm_by ?? $reservation->expires_at;
                if (filled($confirmBy)) {
                    $date = $confirmBy instanceof \Carbon\Carbon
                        ? $confirmBy
                        : \Carbon\Carbon::parse($confirmBy);
                    $deadlineLine = 'Potwierdź do '.$date->format('d.m.Y');
                    $isOverdue = (bool) ($reservation->is_expired ?? false)
                        || ($date->isPast() && ! $date->isToday());
                    $deadlineTone = $isOverdue ? 'due' : 'muted';
                }
            }
        }
    }
@endphp

<div class="epp-rez-status">
    <span
        @class([
            'epp-status-pill',
            'epp-status-pill--danger' => $tone === 'danger',
            'epp-status-pill--success' => $tone === 'success',
            'epp-status-pill--warning' => $tone === 'warning',
            'epp-status-pill--neutral' => $tone === 'neutral',
        ])
        @if ($title) title="{{ $title }}" @endif
    >{{ $label }}</span>

    @if ($deadlineLine)
        <span @class([
            'epp-rez-status__deadline',
            'epp-rez-status__deadline--due' => $deadlineTone === 'due',
            'epp-rez-status__deadline--ok' => $deadlineTone === 'ok',
        ])>{{ $deadlineLine }}</span>
    @endif
</div>
