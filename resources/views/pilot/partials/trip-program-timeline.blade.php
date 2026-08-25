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
    $formatDayLabel = static function (mixed $date): ?string {
        if (! $date) {
            return null;
        }

        $value = $date instanceof \Carbon\CarbonInterface
            ? $date->copy()
            : \Carbon\Carbon::parse($date);

        return $value->locale('pl')->translatedFormat('l').', '.$value->format('d.m.Y');
    };
    $startDateLabel = $formatDayLabel($event->start_date);
    $computedReturnDate = $event->dateForProgramDay($event->resolveCoreProgramDaysCount());
    $returnDate = $event->end_date?->copy();
    if ($computedReturnDate && (! $returnDate || $computedReturnDate->gt($returnDate))) {
        $returnDate = $computedReturnDate;
    }
    $endDateLabel = $formatDayLabel($returnDate);
    $returnPlace = filled($travelLegends['return_place'] ?? null) && ($travelLegends['return_place'] ?? '—') !== '—'
        ? (string) $travelLegends['return_place']
        : $pickupPlace;

    $today = now()->startOfDay();
    $openDay = null;
    $sortedDays = $byDay->sortKeys();
    foreach ($sortedDays as $dayKey => $_) {
        $candidate = $baseDate->copy()->addDays(((int) $dayKey) - 1)->startOfDay();
        if ($candidate->equalTo($today)) {
            $openDay = (int) $dayKey;
            break;
        }
        if ($candidate->greaterThanOrEqualTo($today) && $openDay === null) {
            $openDay = (int) $dayKey;
        }
    }
    if ($openDay === null && $sortedDays->isNotEmpty()) {
        $openDay = (int) $sortedDays->keys()->last();
    }
@endphp

<div class="space-y-3">
    @if($eventPilotNotes)
        <section class="portal-card">
            <div class="portal-card-title"><p>Uwagi biura dla pilota</p></div>
            <div class="prose prose-sm max-w-none text-[#2C2C2A]">{!! $eventPilotNotes !!}</div>
        </section>
    @endif

    <section class="portal-card">
        <div class="portal-card-title"><p>Podstawienie i wyjazd</p></div>
        <div class="portal-info-grid">
            <div>
                <p class="label">Data</p>
                <p class="value">{{ $startDateLabel ?: '—' }}</p>
            </div>
            <div>
                <p class="label">Godzina podstawienia</p>
                <p class="value">{{ $substitutionClock ?: '—' }}</p>
            </div>
            <div>
                <p class="label">Godzina wyjazdu</p>
                <p class="value">{{ $departureClock ?: '—' }}</p>
            </div>
            <div>
                <p class="label">Miejsce podstawienia</p>
                <p class="value" style="white-space:pre-line;">{{ $pickupPlace !== '' ? $pickupPlace : '—' }}</p>
            </div>
        </div>
    </section>

    <section class="portal-card">
        <div class="portal-card-title">
            <p>Program — {{ $event->name }}</p>
        </div>

        @forelse($sortedDays as $day => $dayPoints)
            @php
                $dayDate = $baseDate->copy()->addDays($day - 1);
                $dayRoute = $event->programDayRoute((int) $day);
                $pointCount = $dayPoints->count();
                $dayLabel = 'Dzień '.$day.' · '.$dayDate->locale('pl')->translatedFormat('d.m (l)');
                $countLabel = $pointCount === 1
                    ? '1 punkt'
                    : ($pointCount.' punktów');
                $isOpen = $openDay !== null && (int) $day === (int) $openDay;
            @endphp
            <details class="portal-day-accordion" @if($isOpen) open @endif>
                <summary>
                    <p>{{ $dayLabel }}</p>
                    <p>{{ $countLabel }}</p>
                </summary>

                @if(filled($dayRoute))
                    <p class="portal-muted" style="margin:8px 0 0;">Trasa: {{ $dayRoute }}</p>
                @endif

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
                        $showOfficePay = $financeHint && ! empty($financeHint['has_office_obligation']);
                        $reservationLines = PilotProgramReservationDisplay::linesForPoint($point);
                    @endphp
                    <div @class(['portal-day-item', 'pl-4' => $isSetChild])>
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p>
                                    @if($isSetChild)
                                        <span class="mr-1 text-[#888780]" aria-hidden="true">↳</span>
                                    @endif
                                    @if($point->is_transport) 🚌 @elseif($point->is_hotel) 🏨 @endif
                                    {{ $name }}
                                    @if($point->is_hotel)
                                        <span class="portal-status-pill" style="background:#EAF3DE;color:#27500A;">Hotel</span>
                                    @endif
                                    @if($isSetParent)
                                        <span class="portal-status-pill">Set</span>
                                    @endif
                                    @if($showPay)
                                        <span class="portal-status-pill">Pilot płaci</span>
                                    @elseif($showOfficePay)
                                        <span class="portal-status-pill" style="background:#EAF3DE;color:#27500A;">Płaci biuro</span>
                                    @endif
                                </p>
                                @if($start || $end)
                                    <p class="portal-day-time">
                                        {{ $start ?? '—' }}@if($end) – {{ $end }}@endif
                                    </p>
                                @endif
                                @if($reservationLines !== [])
                                    <ul class="mt-2 space-y-1">
                                        @foreach($reservationLines as $reservationLine)
                                            <li class="flex flex-wrap items-center gap-2 text-xs">
                                                <span @class([
                                                    'inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide',
                                                    $reservationLine['badge_classes'],
                                                ])>
                                                    {{ $reservationLine['status_label'] }}
                                                </span>
                                                @if(filled($reservationLine['time']))
                                                    <span class="font-medium text-[#2C2C2A]">
                                                        godz. {{ $reservationLine['time'] }}
                                                    </span>
                                                @endif
                                                @if(filled($reservationLine['reference']))
                                                    <span class="text-[#5F5E5A]">
                                                        nr {{ $reservationLine['reference'] }}
                                                    </span>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                                @if(filled($description))
                                    <div class="prose prose-sm mt-2 max-w-none text-[#5F5E5A]">
                                        {!! \App\Support\AgreementHtml::sanitize((string) $description) !!}
                                    </div>
                                @endif
                                @if(filled($pilotNotes))
                                    <div class="portal-notice portal-notice--amber mt-2 !mb-0">
                                        <p class="text-[11px] font-semibold uppercase tracking-wide">Uwagi dla pilota</p>
                                        <div class="prose prose-sm mt-1 max-w-none">{!! \App\Support\AgreementHtml::sanitize((string) $pilotNotes) !!}</div>
                                    </div>
                                @endif
                                @if($showPay)
                                    <div class="portal-notice portal-notice--accent mt-2 !mb-0">
                                        <p class="text-[11px] font-semibold uppercase tracking-wide">Do zapłaty</p>
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
                                                <a href="{{ $settlementUrl }}" class="portal-link text-xs">
                                                    Przejdź do rozliczenia →
                                                </a>
                                            </p>
                                        @endif
                                    </div>
                                @elseif($isSetChild && $financeHint && empty($financeHint['has_pilot_obligation']))
                                    <p class="mt-2 text-xs italic text-[#888780]">wchodzi w set</p>
                                @endif
                            </div>
                            @if($point->contractor)
                                <div class="shrink-0 max-w-[14rem] rounded-lg border border-[#E5E3DA] bg-[#F1EFE8] px-3 py-2 text-right">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-[#888780]">Wykonawca</p>
                                    <p class="mt-0.5 text-xs font-semibold text-[#2C2C2A]">{{ $point->contractor->displayLabel() }}</p>
                                    <x-contractor-contact-details
                                        :contractor="$point->contractor"
                                        :location="$point->contractorLocation"
                                        address-label="Adres prowadzenia / podjazdu"
                                        class="mt-1 text-left text-[11px] leading-snug text-[#5F5E5A]"
                                    />
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </details>
        @empty
            <p class="portal-muted" style="margin:0;">Brak punktów programu dla tej wycieczki.</p>
        @endforelse
    </section>

    <section class="portal-card">
        <div class="portal-card-title"><p>Powrót / podstawienie</p></div>
        <div class="portal-info-grid">
            <div>
                <p class="label">Data</p>
                <p class="value">{{ $endDateLabel ?: '—' }}</p>
            </div>
            <div>
                <p class="label">Godzina podstawienia / powrotu</p>
                <p class="value">{{ $returnClock ?: '—' }}</p>
            </div>
            <div style="grid-column: 1 / -1;">
                <p class="label">Miejsce podstawienia</p>
                <p class="value" style="white-space:pre-line;">{{ $returnPlace !== '' ? $returnPlace : '—' }}</p>
            </div>
        </div>
    </section>
</div>
