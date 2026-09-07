@php
    /** @var \App\Models\EventProgramPoint|null $record */
    $record = $getRecord();

    $tone = 'neutral';
    $label = '';
    $title = null;
    /** @var list<array{text: string, tone: string}> $metaLines */
    $metaLines = [];
    $hasPlan = false;

    if ($record && ! $record->getAttribute('_is_set_parent')) {
        $finance = method_exists($this, 'programPointFinanceViewData')
            ? $this->programPointFinanceViewData($record)
            : [];
        $paidStatus = (string) ($finance['paidStatus'] ?? 'none');
        $plannedLabel = (string) ($finance['planned'] ?? '—');
        $paidLabel = (string) ($finance['paid'] ?? '—');
        $hasPlan = $plannedLabel !== '—' && $plannedLabel !== '';

        if ($hasPlan) {
            // Pill: jedno źródło prawdy z kolumny Kwoty (paidStatus).
            if ($paidStatus === 'full') {
                $tone = 'success';
                $label = 'OK';
                $title = 'Opłacone: '.$paidLabel.' / '.$plannedLabel;
            } elseif ($paidStatus === 'partial') {
                $tone = 'warning';
                $label = 'Część';
                $title = 'Częściowo: '.$paidLabel.' / '.$plannedLabel;
            } else {
                $tone = 'danger';
                $label = 'Do zapł.';
                $title = 'Do zapłaty: '.$paidLabel.' / '.$plannedLabel;
            }

            $paymentLines = $finance['paymentLines'] ?? null;
            if (is_array($paymentLines) && $paymentLines !== []) {
                foreach ($paymentLines as $line) {
                    if (! is_array($line) || blank($line['text'] ?? null)) {
                        continue;
                    }
                    $metaLines[] = [
                        'text' => (string) $line['text'],
                        'tone' => (string) ($line['tone'] ?? 'pending'),
                    ];
                }
            } else {
                // Fallback dla starszego payloadu bez paymentLines.
                if (is_array($finance['advanceLine'] ?? null) && filled($finance['advanceLine']['text'] ?? null)) {
                    $advanceStatus = (string) ($finance['advanceLine']['status'] ?? 'pending');
                    $metaLines[] = [
                        'text' => (string) $finance['advanceLine']['text'],
                        'tone' => match ($advanceStatus) {
                            'paid' => 'paid',
                            'overdue' => 'due',
                            default => 'pending',
                        },
                    ];
                }

                if (is_array($finance['remainingLine'] ?? null) && filled($finance['remainingLine']['text'] ?? null)) {
                    $metaLines[] = [
                        'text' => (string) $finance['remainingLine']['text'],
                        'tone' => (string) ($finance['remainingLine']['tone'] ?? 'due'),
                    ];
                }
            }

            if (collect($metaLines)->contains(fn (array $line): bool => ($line['tone'] ?? '') === 'due')
                && $paidStatus !== 'full') {
                $tone = 'danger';
                $label = 'Po term.';
            }
        }
    }
@endphp

<div class="epp-pay-status">
    @if ($hasPlan && $label !== '')
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
    @endif

    @foreach ($metaLines as $metaLine)
        <span
            @class([
                'epp-pay-status__meta',
                'epp-pay-status__meta--paid' => ($metaLine['tone'] ?? '') === 'paid',
                'epp-pay-status__meta--pending' => ($metaLine['tone'] ?? '') === 'pending',
                'epp-pay-status__meta--pilot' => ($metaLine['tone'] ?? '') === 'pilot',
                'epp-pay-status__meta--warn' => ($metaLine['tone'] ?? '') === 'warn',
                'epp-pay-status__meta--due' => ($metaLine['tone'] ?? '') === 'due',
            ])
            title="{{ $metaLine['text'] }}"
        >{{ $metaLine['text'] }}</span>
    @endforeach
</div>
