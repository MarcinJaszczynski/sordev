@php
    /** @var \App\Models\EventProgramPoint|null $record */
    $record = $getRecord();
    $start = null;
    $end = null;

    if ($record && ! $record->hide_times) {
        $start = $record->start_time ? substr((string) $record->start_time, 0, 5) : null;
        $end = $record->end_time ? substr((string) $record->end_time, 0, 5) : null;
    }
@endphp

@if ($start && $end)
    <div class="epp-time-cell">
        <span class="epp-time-cell__start">{{ $start }}</span>
        <span class="epp-time-cell__end">{{ $end }}</span>
    </div>
@elseif ($start)
    <div class="epp-time-cell">
        <span class="epp-time-cell__start">{{ $start }}</span>
    </div>
@else
    <span class="epp-time-cell epp-time-cell--muted">—</span>
@endif
