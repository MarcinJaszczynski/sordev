@php
    /** @var \App\Models\EventProgramPoint|null $record */
    $record = $getRecord();

    $tone = 'neutral';
    $label = '—';
    $title = null;
    $metaLines = [];

    if ($record && ! $record->getAttribute('_is_set_parent')) {
        $event = method_exists($this, 'getOwnerRecord') ? $this->getOwnerRecord() : $record->event;
        $status = app(\App\Services\ProgramPointPaymentStatusResolver::class)->resolve($record, $event);
        $finance = method_exists($this, 'programPointFinanceViewData')
            ? $this->programPointFinanceViewData($record)
            : [];
        $paidStatus = (string) ($finance['paidStatus'] ?? 'none');
        $title = $status['tooltip'] ?? null;
        $color = (string) ($status['color'] ?? 'gray');
        $tooltip = mb_strtolower((string) ($status['tooltip'] ?? ''));

        if (($status['code'] ?? '') === 'N/A' || $color === 'gray') {
            $tone = 'danger';
            $label = 'Brak kwoty';
        } elseif ($color === 'green' || $paidStatus === 'full') {
            $tone = 'success';
            $label = 'Zapłacone';
        } elseif (str_contains($tooltip, 'przeterminowan')) {
            $tone = 'danger';
            $label = 'Przeterminowane';
        } elseif ($paidStatus === 'partial' || str_contains($tooltip, 'częściowo')) {
            $tone = 'warning';
            $label = 'Częściowo';
        } elseif ($paidStatus === 'none' || $color === 'red' || str_contains($tooltip, 'do zapłaty')) {
            $tone = 'danger';
            $label = 'Do zapłaty';
        } else {
            $tone = 'warning';
            $label = 'W toku';
        }

        // Jedna–dwie linie pod pigułką (bez osobnej kolumny terminów — ta nachodziła na wiersz).
        $metaLines = [];
        if (is_array($finance['advanceLine'] ?? null) && filled($finance['advanceLine']['text'] ?? null)) {
            $advanceText = (string) $finance['advanceLine']['text'];
            $metaLines[] = str_starts_with(mb_strtolower($advanceText), 'zaliczka')
                ? $advanceText
                : 'Zaliczka '.$advanceText;
        }
        if (is_array($finance['remainingLine'] ?? null) && filled($finance['remainingLine']['text'] ?? null)) {
            $remainingText = (string) $finance['remainingLine']['text'];
            // Płatnik jest w osobnej kolumnie — tu tylko kwota pozostała.
            if (preg_match('/^(Biuro|Pilot|Klient)\s*·\s*(.+)$/u', $remainingText, $m) === 1) {
                $remainingText = 'Pozostało '.$m[2];
            }
            if (filled($finance['dueDateLabel'] ?? null) && $paidStatus !== 'full') {
                $remainingText .= ' · do '.$finance['dueDateLabel'];
            }
            $metaLines[] = $remainingText;
        } elseif (filled($finance['dueDateLabel'] ?? null) && $paidStatus !== 'full') {
            $metaLines[] = 'Termin '.$finance['dueDateLabel'];
        }
    }
@endphp

<div class="epp-pay-status">
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

    @foreach ($metaLines as $metaLine)
        <span class="epp-pay-status__meta" title="{{ $metaLine }}">{{ $metaLine }}</span>
    @endforeach
</div>
