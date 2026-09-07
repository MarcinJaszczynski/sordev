@php
    /** @var \App\Models\EventProgramPoint|null $record */
    $record = $getRecord();
@endphp

@if (! $record)
    <span class="text-gray-400">—</span>
@else
    @php
        $isSetParent = (bool) $record->getAttribute('_is_set_parent');
        $isChild = filled($record->parent_id);
        $isSetExpanded = (bool) $record->getAttribute('_set_expanded');
        $childCount = (int) ($record->children_count ?? 0);
        $name = $record->name ?? $record->templatePoint?->name ?? '—';

        $start = $record->hide_times ? null : ($record->start_time ? substr((string) $record->start_time, 0, 5) : null);
        $end = $record->hide_times ? null : ($record->end_time ? substr((string) $record->end_time, 0, 5) : null);

        $descHtml = trim((string) ($record->resolvedDescription() ?? ''));
        $descPlain = trim(preg_replace('/\s+/u', ' ', strip_tags($descHtml)) ?? '');
        $hasDescription = $descPlain !== '';
        $hasTemplate = filled($record->event_template_program_point_id);
        $isReady = $hasDescription || $hasTemplate;

        $inProgram = (bool) $record->include_in_program;
        $inCalc = (bool) $record->include_in_calculation;

        $contractorName = $record->contractor?->name;
        if (! $contractorName) {
            $reservationForContractor = $record->relationLoaded('reservations')
                ? $record->reservations->sortByDesc('id')->first()
                : $record->latestVisibleReservation();
            $contractorName = $reservationForContractor?->contractor?->name;
        }

        $finance = method_exists($this, 'programPointFinanceViewData')
            ? $this->programPointFinanceViewData($record)
            : [];
        $hideFinance = ! empty($finance['hideSetParentFinance']);
        $planned = (string) ($finance['planned'] ?? '—');
        $paid = (string) ($finance['paid'] ?? '—');
        $calc = (string) ($finance['calc'] ?? '—');
        $paidStatus = (string) ($finance['paidStatus'] ?? 'none');
        $remainingLine = $finance['remainingLine'] ?? null;
        $dueDateLabel = $finance['dueDateLabel'] ?? null;

        $paidPercent = 0;
        if (! $hideFinance && $paidStatus === 'full') {
            $paidPercent = 100;
        } elseif (! $hideFinance && $paidStatus === 'partial') {
            $paidRaw = preg_replace('/[^\d,.]/', '', str_replace(' ', '', $paid)) ?: '0';
            $planRaw = preg_replace('/[^\d,.]/', '', str_replace(' ', '', $planned)) ?: '0';
            $paidNum = (float) str_replace(',', '.', $paidRaw);
            $planNum = (float) str_replace(',', '.', $planRaw);
            $paidPercent = $planNum > 0.009 ? (int) min(99, max(1, round(($paidNum / $planNum) * 100))) : 0;
        }

        $reservation = $record->relationLoaded('reservations')
            ? $record->reservations->sortByDesc('id')->first()
            : $record->latestVisibleReservation();
        $rezTone = 'neutral';
        $rezLabel = '—';
        $rezDeadline = null;
        if (! $isSetParent) {
            if (! $reservation) {
                $rezTone = 'danger';
                $rezLabel = 'Brak rez.';
            } else {
                $status = (string) $reservation->status;
                if ($status === 'not_required') {
                    $rezLabel = '—';
                } elseif ($status === 'cancelled') {
                    $rezTone = 'danger';
                    $rezLabel = 'Anulowana';
                } elseif (in_array($status, ['confirmed', 'completed'], true)) {
                    $rezTone = 'success';
                    $rezLabel = 'Potwierdzona';
                } elseif ($status === 'partially_confirmed') {
                    $rezTone = 'warning';
                    $rezLabel = 'Częściowo';
                } else {
                    $rezTone = 'warning';
                    $rezLabel = 'Oczekuje';
                }
                $isConfirmed = in_array($status, ['confirmed', 'completed', 'partially_confirmed'], true);
                if ($isConfirmed && filled($reservation->confirmed_at)) {
                    $rezDeadline = 'Potwierdzono '.$reservation->confirmed_at->format('d.m.Y');
                } elseif (! $isConfirmed && ! in_array($status, ['cancelled', 'not_required'], true)) {
                    $confirmBy = $reservation->confirm_by ?? $reservation->expires_at;
                    if (filled($confirmBy)) {
                        $date = $confirmBy instanceof \Carbon\Carbon ? $confirmBy : \Carbon\Carbon::parse($confirmBy);
                        $rezDeadline = 'Potwierdź do '.$date->format('d.m.Y');
                    }
                }
            }
        }

        $payTone = 'neutral';
        $payLabel = '—';
        if (! $isSetParent && ! $hideFinance) {
            if ($paidStatus === 'full') {
                $payTone = 'success';
                $payLabel = 'Zapłacone';
            } elseif ($paidStatus === 'partial') {
                $payTone = 'warning';
                $payLabel = 'Częściowo';
            } elseif (($finance['planned'] ?? '—') === '—' || ($finance['planned'] ?? '') === '0,00 PLN') {
                $event = method_exists($this, 'getOwnerRecord') ? $this->getOwnerRecord() : $record->event;
                $resolver = app(\App\Services\ProgramPointPaymentStatusResolver::class)->resolve($record, $event);
                $resolverCode = (string) ($resolver['code'] ?? '');
                if ($resolverCode === '' || $resolverCode === 'N/A' || ($resolver['color'] ?? '') === 'gray') {
                    $payTone = 'neutral';
                    $payLabel = '';
                } else {
                    $payTone = 'danger';
                    $payLabel = 'Do zapłaty';
                }
            } else {
                $payTone = 'danger';
                $payLabel = 'Do zapłaty';
            }
        }

        $amountLabel = $hideFinance ? '—' : (
            ($planned !== '' && $planned !== '—') ? $planned : ''
        );

        $doc = $hideFinance ? null : [
            'hint' => (string) ($finance['documentHint'] ?? 'Brak pliku'),
            'url' => (string) ($finance['documentFirstUrl'] ?? ''),
            'has' => ! empty($finance['hasUploadedFile']),
            'badge' => (string) ($finance['documentBadgeLabel'] ?? $finance['documentHint'] ?? 'Plik'),
            'title' => (string) ($finance['documentStatusLabel'] ?? $finance['documentHint'] ?? 'Brak pliku'),
        ];

        /** @var \Illuminate\Support\Collection<int, \App\Models\EventProgramPoint>|null $setChildren */
        $setChildren = $record->getAttribute('_set_children_preview');
    @endphp

    <div @class([
        'epp-card',
        'epp-card--child' => $isChild,
        'epp-card--set' => $isSetParent,
    ])>
        <div class="epp-card__time" aria-label="Godzina">
            @if ($start)
                <span class="epp-card__time-start">{{ $start }}</span>
                @if ($end)
                    <span class="epp-card__time-end">{{ $end }}</span>
                @endif
            @else
                <span class="epp-card__time-empty">—</span>
            @endif
        </div>

        <div class="epp-card__main">
            <div class="epp-card__title-row">
                @if ($isSetParent)
                    <button
                        type="button"
                        @class(['epp-set-badge', 'epp-set-badge--expanded' => $isSetExpanded])
                        x-on:click.stop="$wire.toggleSetExpanded({{ $record->id }})"
                        aria-expanded="{{ $isSetExpanded ? 'true' : 'false' }}"
                    >
                        <span class="epp-set-badge__label">Set</span>
                        <span class="epp-set-badge__count">{{ $childCount }}</span>
                    </button>
                @endif
                <span class="epp-card__title">{{ $name }}</span>
            </div>

            <div class="epp-card__badges">
                <span @class([
                    'epp-card-badge',
                    'epp-card-badge--success' => $isReady,
                    'epp-card-badge--danger' => ! $isReady,
                ])>
                    @if ($isReady)
                        <x-filament::icon icon="heroicon-m-check-circle" class="epp-card-badge__icon" />
                        gotowe
                    @else
                        brak opisu
                    @endif
                </span>

                <span @class([
                    'epp-card-badge',
                    'epp-card-badge--info' => $inCalc,
                    'epp-card-badge--warning' => ! $inCalc,
                ])>
                    {{ $inCalc ? 'w kosztach' : 'poza kosztami' }}
                </span>

                <span @class([
                    'epp-card-badge',
                    'epp-card-badge--program-on' => $inProgram,
                    'epp-card-badge--program-off' => ! $inProgram,
                ])>
                    {{ $inProgram ? 'program' : 'poza programem' }}
                </span>

                @if (! $isSetParent)
                    <span @class([
                        'epp-card-badge',
                        'epp-card-badge--success' => $rezTone === 'success',
                        'epp-card-badge--danger' => $rezTone === 'danger',
                        'epp-card-badge--warning' => $rezTone === 'warning',
                        'epp-card-badge--muted' => $rezTone === 'neutral',
                    ]) title="{{ $rezDeadline }}">{{ $rezLabel }}</span>
                @endif

                @if (filled($payLabel))
                    <span @class([
                        'epp-card-badge',
                        'epp-card-badge--success' => $payTone === 'success',
                        'epp-card-badge--danger' => $payTone === 'danger',
                        'epp-card-badge--warning' => $payTone === 'warning',
                        'epp-card-badge--muted' => $payTone === 'neutral',
                    ])>{{ $payLabel }}</span>
                @endif
            </div>

            @if ($hasDescription)
                <div class="epp-card__desc" title="{{ $descPlain }}">{{ \Illuminate\Support\Str::limit($descPlain, 100) }}</div>
            @endif

            @if ($contractorName)
                <div class="epp-card__contractor">{{ $contractorName }}</div>
            @endif

            @if ($rezDeadline)
                <div class="epp-card__meta-line">{{ $rezDeadline }}</div>
            @endif

            @if (is_array($remainingLine) && filled($remainingLine['text'] ?? null))
                <div @class([
                    'epp-card__meta-line',
                    'epp-card__meta-line--due' => ($remainingLine['tone'] ?? '') === 'due',
                    'epp-card__meta-line--pilot' => ($remainingLine['tone'] ?? '') === 'pilot',
                    'epp-card__meta-line--warn' => ($remainingLine['tone'] ?? '') === 'warn',
                ])>
                    {{ $remainingLine['text'] }}
                    @if (filled($dueDateLabel))
                        · do {{ $dueDateLabel }}
                    @endif
                </div>
            @endif

            @php
                $advanceLine = $finance['advanceLine'] ?? null;
            @endphp
            @if (is_array($advanceLine) && filled($advanceLine['text'] ?? null))
                <div @class([
                    'epp-card__meta-line',
                    'epp-card__meta-line--paid' => ($advanceLine['status'] ?? '') === 'paid',
                    'epp-card__meta-line--pending' => ($advanceLine['status'] ?? '') !== 'paid',
                ])>
                    {{ $advanceLine['text'] }}
                </div>
            @endif

            <div class="epp-card__actions-inline">
                <button
                    type="button"
                    wire:click="openCreateTaskForProgramPoint({{ $record->getKey() }})"
                    class="epp-task-btn"
                    x-on:click.stop
                >+ Zadanie</button>
            </div>
        </div>

        <div class="epp-card__side">
            <div class="epp-card__amount-block">
                <span class="epp-card__amount">{{ $amountLabel }}</span>
                @if (! $hideFinance)
                    <div class="epp-card__amounts-mini" title="Szablon / Plan / Zapłacono">
                        <span>S {{ $calc }}</span>
                        <span>P {{ $planned }}</span>
                        <span>Z {{ ($paid !== '' && $paid !== '—') ? $paid : '—' }}</span>
                    </div>
                @endif
            </div>

            <div class="epp-card__template" title="{{ $hasTemplate ? ($record->templatePoint?->name ?? 'Szablon') : 'Brak szablonu' }}">
                @if ($hasTemplate)
                    <x-filament::icon icon="heroicon-m-link" class="epp-card__template-icon" />
                    <span>szablon</span>
                @else
                    <span class="epp-card__template--empty">brak</span>
                @endif
            </div>

            @if (! $isSetParent && ! $hideFinance)
                <div class="epp-paid-progress" title="Postęp wpłat: {{ $paidPercent }}%">
                    <div class="epp-paid-progress__track">
                        <div
                            @class([
                                'epp-paid-progress__bar',
                                'epp-paid-progress__bar--full' => $paidPercent >= 100,
                                'epp-paid-progress__bar--partial' => $paidPercent > 0 && $paidPercent < 100,
                                'epp-paid-progress__bar--empty' => $paidPercent <= 0,
                            ])
                            style="width: {{ max(0, min(100, $paidPercent)) }}%"
                        ></div>
                    </div>
                    <span class="epp-paid-progress__label">{{ $paidPercent }}%</span>
                </div>
            @endif

            @if ($doc)
                @php
                    $hasFile = $doc['has'] && $doc['url'] !== '';
                    $badge = (string) ($doc['badge'] ?? $doc['hint'] ?? 'Plik');
                @endphp
                @if ($hasFile)
                    <a href="{{ $doc['url'] }}" target="_blank" rel="noopener noreferrer" class="epp-doc-badge epp-doc-badge--has" title="{{ $doc['title'] }}" x-on:click.stop>
                        {{ $badge }}
                    </a>
                @elseif (($doc['hint'] ?? '') !== '' && ($doc['hint'] ?? '') !== 'Brak pliku')
                    <span class="epp-doc-badge epp-doc-badge--warn" title="{{ $doc['title'] }}">Nr</span>
                @else
                    <span class="epp-doc-badge epp-doc-badge--empty" title="Brak pliku">—</span>
                @endif
            @endif
        </div>
    </div>
@endif
