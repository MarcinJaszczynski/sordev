@php
    use App\Services\EventFolderPdfService;
    use App\Services\EventProgramPointOrderService;
    use App\Support\PilotProgramReservationDisplay;
    use Illuminate\Support\Facades\Schema;

    $service = app(EventProgramPointOrderService::class);
    $points = $service->pilotProgramPoints($event);
    $financeHintsByPointId = $financeHintsByPointId ?? [];
    $baseDate = $event->start_date?->copy()->startOfDay() ?? now()->startOfDay();
    $byDay = $points->groupBy(fn ($point) => max(1, (int) ($point->day ?? 1)));
    $eventPilotNotes = filled($event->pilot_notes ?? null) ? $event->pilot_notes : null;

    $event->loadMissing('startPlace');
    $travelLegends = app(EventFolderPdfService::class)->buildTravelLegends($event);
    $pickupPlace = trim(strip_tags((string) ($event->pickup_place_details ?? '')));
    if ($pickupPlace === '') {
        $pickupPlace = (string) ($event->startPlace?->name ?? '');
    }
    $formatClock = static function (mixed $time): ?string {
        if (! filled($time)) {
            return null;
        }

        return substr((string) $time, 0, 5);
    };
    $substitutionClock = Schema::hasColumn('events', 'substitution_time')
        ? $formatClock($event->substitution_time ?? null)
        : null;
    $departureClock = $formatClock($event->departure_time ?? null);
    $returnClock = Schema::hasColumn('events', 'return_time')
        ? $formatClock($event->return_time ?? null)
        : null;
    $startDateLabel = $event->start_date
        ? $event->start_date->copy()->locale('pl')->translatedFormat('l').', '.$event->start_date->format('d.m.Y')
        : null;
    $endDateLabel = $event->end_date
        ? $event->end_date->copy()->locale('pl')->translatedFormat('l').', '.$event->end_date->format('d.m.Y')
        : null;
    $returnPlace = filled($travelLegends['return_place'] ?? null) && ($travelLegends['return_place'] ?? '—') !== '—'
        ? (string) $travelLegends['return_place']
        : $pickupPlace;
@endphp

<div class="space-y-4">
    @if($eventPilotNotes)
        <section class="sor-lw-card">
            <h3 class="sor-lw-title mb-2">Uwagi biura dla pilota</h3>
            <div class="prose prose-sm max-w-none text-gray-700 dark:prose-invert">{!! $eventPilotNotes !!}</div>
        </section>
    @endif

    <section class="sor-lw-card overflow-hidden !p-0">
        <header class="border-b border-blue-100 bg-blue-50 px-4 py-3 dark:border-blue-900/40 dark:bg-blue-950/30">
            <h3 class="sor-lw-title text-blue-900 dark:text-blue-100">Podstawienie i wyjazd</h3>
            <p class="sor-lw-muted">Start programu</p>
        </header>
        <dl class="divide-y divide-gray-100 px-4 dark:divide-gray-700">
            <div class="flex flex-col gap-0.5 py-3 sm:flex-row sm:justify-between sm:gap-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Data</dt>
                <dd class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $startDateLabel ?: '—' }}</dd>
            </div>
            <div class="flex flex-col gap-0.5 py-3 sm:flex-row sm:justify-between sm:gap-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Godzina podstawienia</dt>
                <dd class="text-sm font-semibold text-[#0663fc]">{{ $substitutionClock ?: '—' }}</dd>
            </div>
            <div class="flex flex-col gap-0.5 py-3 sm:flex-row sm:justify-between sm:gap-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Godzina wyjazdu</dt>
                <dd class="text-sm font-semibold text-[#0663fc]">{{ $departureClock ?: '—' }}</dd>
            </div>
            <div class="flex flex-col gap-0.5 py-3 sm:flex-row sm:justify-between sm:gap-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Miejsce podstawienia</dt>
                <dd class="text-sm text-gray-900 dark:text-gray-100 sm:text-right whitespace-pre-line">{{ $pickupPlace !== '' ? $pickupPlace : '—' }}</dd>
            </div>
        </dl>
    </section>

    @forelse($byDay->sortKeys() as $day => $dayPoints)
        @php
            $dayDate = $baseDate->copy()->addDays($day - 1);
        @endphp
        <section class="sor-lw-card overflow-hidden !p-0">
            <header class="border-b border-gray-100 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800">
                <h3 class="sor-lw-title">Dzień {{ $day }}</h3>
                <p class="sor-lw-muted">{{ $dayDate->format('d.m.Y (l)') }}</p>
            </header>
            <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach($dayPoints as $point)
                    @php
                        $name = $point->templatePoint->name ?? $point->name ?? ('Punkt #'.$point->id);
                        $start = $point->start_time ? substr((string) $point->start_time, 0, 5) : null;
                        $end = $point->end_time ? substr((string) $point->end_time, 0, 5) : null;
                        $description = $point->resolvedDescription();
                        $pilotNotes = $point->resolvedPilotNotes();
                        $financeHint = $financeHintsByPointId[$point->id] ?? null;
                        $isSetParent = ($point->children_count ?? 0) > 0;
                        $isSetChild = filled($point->parent_id);
                        $showPay = $financeHint && ! empty($financeHint['has_pilot_obligation']);
                        $reservationLines = PilotProgramReservationDisplay::linesForPoint($point);
                    @endphp
                    <li @class([
                        'px-4 py-3',
                        'pl-8' => $isSetChild,
                    ])>
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    @if($isSetChild)
                                        <span class="mr-1 text-gray-400" aria-hidden="true">↳</span>
                                    @endif
                                    @if($point->is_transport) 🚌 @elseif($point->is_hotel) 🏨 @endif
                                    {{ $name }}
                                    @if($point->is_hotel)
                                        <span class="ml-2 inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">Hotel</span>
                                    @endif
                                    @if($isSetParent)
                                        <span class="ml-2 inline-flex items-center rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-200">Set</span>
                                    @endif
                                    @if($showPay)
                                        <span class="ml-2 inline-flex items-center rounded-full bg-sky-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-sky-800 dark:bg-sky-900/40 dark:text-sky-200">👤 Pilot płaci</span>
                                    @endif
                                </p>
                                @if($start || $end)
                                    <p class="mt-1 text-xs font-medium text-[#0663fc]">
                                        {{ $start ?? '—' }}@if($end) – {{ $end }} @endif
                                    </p>
                                @endif
                                <div class="mt-2">
                                    @if($reservationLines !== [])
                                        <ul class="space-y-1">
                                            @foreach($reservationLines as $reservationLine)
                                                <li class="flex flex-wrap items-center gap-2 text-xs">
                                                    <span @class([
                                                        'inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide',
                                                        $reservationLine['badge_classes'],
                                                    ])>
                                                        {{ $reservationLine['status_label'] }}
                                                    </span>
                                                    @if(filled($reservationLine['time']))
                                                        <span class="font-medium text-gray-800 dark:text-gray-200">
                                                            godz. {{ $reservationLine['time'] }}
                                                        </span>
                                                    @endif
                                                    @if(filled($reservationLine['reference']))
                                                        <span class="text-gray-500 dark:text-gray-400">
                                                            nr {{ $reservationLine['reference'] }}
                                                        </span>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <p class="text-xs italic text-gray-500 dark:text-gray-400">Brak rezerwacji</p>
                                    @endif
                                </div>
                                @if(filled($description))
                                    <div class="prose prose-sm mt-2 max-w-none text-gray-700 dark:prose-invert dark:text-gray-300">
                                        {!! \App\Support\AgreementHtml::sanitize((string) $description) !!}
                                    </div>
                                @endif
                                @if(filled($pilotNotes))
                                    <div class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 dark:border-amber-800 dark:bg-amber-950/30">
                                        <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-800 dark:text-amber-200">Uwagi dla pilota</p>
                                        <div class="prose prose-sm mt-1 max-w-none text-amber-900 dark:prose-invert dark:text-amber-100">{!! \App\Support\AgreementHtml::sanitize((string) $pilotNotes) !!}</div>
                                    </div>
                                @endif
                                @if($showPay)
                                    <div class="mt-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-950 dark:border-blue-800 dark:bg-blue-950/30 dark:text-blue-100">
                                        <p class="text-[11px] font-semibold uppercase tracking-wide text-blue-800 dark:text-blue-200">Do zapłaty</p>
                                        @if(filled($financeHint['planned_label'] ?? null))
                                            <p class="mt-1 font-semibold">{{ $financeHint['planned_label'] }}</p>
                                        @endif
                                        <ul class="mt-1 space-y-0.5 text-xs">
                                            @foreach(($financeHint['lines'] ?? []) as $line)
                                                <li>{{ $line }}</li>
                                            @endforeach
                                        </ul>
                                        @if(! empty($settlementUrl))
                                            <p class="mt-2">
                                                <a href="{{ $settlementUrl }}" class="text-xs font-semibold text-[#0663fc] hover:underline">
                                                    Przejdź do rozliczenia →
                                                </a>
                                            </p>
                                        @endif
                                    </div>
                                @elseif($isSetChild && $financeHint && empty($financeHint['has_pilot_obligation']))
                                    <p class="mt-2 text-xs italic text-gray-500 dark:text-gray-400">wchodzi w set</p>
                                @endif
                            </div>
                            @if($point->contractor)
                                <div class="shrink-0 max-w-[14rem] rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-right dark:border-gray-700 dark:bg-gray-800/80">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Wykonawca</p>
                                    <p class="mt-0.5 text-xs font-semibold text-gray-900 dark:text-gray-100">{{ $point->contractor->displayLabel() }}</p>
                                    <x-contractor-contact-details
                                        :contractor="$point->contractor"
                                        :location="$point->contractorLocation"
                                        address-label="Adres prowadzenia / podjazdu"
                                        class="mt-1 text-left text-[11px] leading-snug text-gray-600 dark:text-gray-300"
                                    />
                                </div>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <div class="sor-lw-empty">
            Brak punktów programu dla tej wycieczki.
        </div>
    @endforelse

    <section class="sor-lw-card overflow-hidden !p-0">
        <header class="border-b border-orange-100 bg-orange-50 px-4 py-3 dark:border-orange-900/40 dark:bg-orange-950/30">
            <h3 class="sor-lw-title text-orange-900 dark:text-orange-100">Powrót / podstawienie</h3>
            <p class="sor-lw-muted">Koniec programu</p>
        </header>
        <dl class="divide-y divide-gray-100 px-4 dark:divide-gray-700">
            <div class="flex flex-col gap-0.5 py-3 sm:flex-row sm:justify-between sm:gap-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Data</dt>
                <dd class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $endDateLabel ?: '—' }}</dd>
            </div>
            <div class="flex flex-col gap-0.5 py-3 sm:flex-row sm:justify-between sm:gap-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Godzina podstawienia / powrotu</dt>
                <dd class="text-sm font-semibold text-[#0663fc]">{{ $returnClock ?: '—' }}</dd>
            </div>
            <div class="flex flex-col gap-0.5 py-3 sm:flex-row sm:justify-between sm:gap-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Miejsce podstawienia</dt>
                <dd class="text-sm text-gray-900 dark:text-gray-100 sm:text-right whitespace-pre-line">{{ $returnPlace !== '' ? $returnPlace : '—' }}</dd>
            </div>
        </dl>
    </section>
</div>
