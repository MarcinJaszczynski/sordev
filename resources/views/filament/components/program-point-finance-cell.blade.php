@php
    /** @var \App\Models\EventProgramPoint|null $record */
    $record = $getRecord();
@endphp

@if (! $record)
    <span class="text-gray-400">—</span>
@else
    @php
        $finance = method_exists($this, 'programPointFinanceViewData')
            ? $this->programPointFinanceViewData($record)
            : [];
        $hide = ! empty($finance['hideSetParentFinance']);
        $reservation = $record->relationLoaded('reservations')
            ? $record->reservations->sortByDesc('id')->first()
            : $record->reservations()->withTrashed()->orderByDesc('id')->first();
        $reservation ??= $record->latestVisibleReservation();
        $reservationIsTrashed = $reservation?->trashed() ?? false;
        $reservationLine = $reservation
            ? \App\Support\Reservations\ReservationWorkflowDisplay::reservationLine($reservation)
            : null;
        $advanceLine = $finance['advanceLine'] ?? null;
        $remainingLine = $finance['remainingLine'] ?? null;
        $calc = (string) ($finance['calc'] ?? '—');
        $planned = (string) ($finance['planned'] ?? '—');
        $planDiffers = ! empty($finance['planDiffersFromCalc']);
    @endphp

    <div class="epp-finance-cell">
        @if ($hide)
            <span class="text-gray-400">—</span>
        @else
            <div class="epp-ops epp-ops--finance">
                <div class="epp-ops__row">
                    <span class="epp-ops__label">Szablon</span>
                    <span class="tabular-nums">{{ $calc }}</span>
                </div>
                <div @class([
                    'epp-ops__row',
                    'epp-ops__row--total',
                    'epp-ops__row--warn' => $planDiffers,
                ])>
                    <span class="epp-ops__label">Plan</span>
                    <span class="tabular-nums" @if ($planDiffers) title="Plan różni się od szablonu: {{ $calc }}" @endif>{{ $planned }}</span>
                </div>
                @if ($reservationLine)
                    <div @class([
                        'epp-ops__row',
                        'epp-ops__row--rez',
                        'epp-ops__row--muted' => $reservationIsTrashed,
                    ])>
                        <span class="epp-ops__label">Rezerwacja</span>
                        <span>{{ $reservationLine['text'] }}{{ $reservationIsTrashed ? ' · usunięta' : '' }}</span>
                    </div>
                @endif
                @if ($advanceLine)
                    <div @class([
                        'epp-ops__row',
                        'epp-ops__row--ok' => ($advanceLine['status'] ?? '') === 'paid',
                        'epp-ops__row--warn' => in_array($advanceLine['status'] ?? '', ['pending', 'overdue'], true),
                    ])>
                        <span class="epp-ops__label">Zaliczka</span>
                        <span>{{ $advanceLine['text'] }}</span>
                    </div>
                @endif
                @php
                    $paidLabel = trim((string) ($finance['paid'] ?? ''));
                    $paidStatus = (string) ($finance['paidStatus'] ?? 'none');
                    $showPaid = $paidLabel !== '' && $paidLabel !== '—' && $paidStatus !== 'none';
                @endphp
                @if ($showPaid)
                    <div class="epp-ops__row epp-ops__row--ok">
                        <span class="epp-ops__label">Zapłacono</span>
                        <span class="tabular-nums">{{ $paidLabel }}</span>
                    </div>
                @endif
                @if ($remainingLine)
                    <div @class([
                        'epp-ops__row',
                        'epp-ops__row--warn' => ($remainingLine['tone'] ?? '') === 'warn',
                        'epp-ops__row--due' => ($remainingLine['tone'] ?? '') === 'due',
                    ])>
                        <span class="epp-ops__label">Do zapłaty</span>
                        <span>{{ $remainingLine['text'] }}</span>
                    </div>
                @elseif ($paidStatus === 'full')
                    <div class="epp-ops__row epp-ops__row--ok">
                        <span class="epp-ops__label">Do zapłaty</span>
                        <span>opłacone</span>
                    </div>
                @endif
            </div>
        @endif
    </div>
@endif
