<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Informacje dla Hotelu — {{ $event->name }}</title>
    @include('pdf.packages._styles')
    <style>
        body {
            margin: 20mm 16mm 22mm 16mm;
            padding: 0;
            background-color: #ffffff;
            font-family: DejaVu Sans, sans-serif; /* Recommended for Dompdf Unicode support */
        }

        .hotel-doc {
            color: #111827;
            padding: 0;
            width: 100%;
            box-sizing: border-box;
        }

        .hotel-doc .logo-wrap { margin-bottom: 10px; }
        .hotel-doc .logo-wrap img { width: 72px; max-height: 72px; }
        .hotel-doc .doc-title {
            font-size: 22px;
            font-weight: 400;
            color: #111827;
            margin-bottom: 6px;
        }
        .hotel-doc .doc-sub {
            font-size: 13px;
            color: #4b5563;
            margin-bottom: 4px;
        }
        .hotel-doc .doc-meta {
            font-size: 11px;
            color: #6b7280;
            margin-bottom: 10px;
        }
        .hotel-doc .doc-meta strong { color: #111827; font-weight: 700; }
        .hotel-doc .pilot-banner {
            text-align: center;
            font-size: 12px;
            font-weight: 700;
            color: #111827;
            padding: 8px 0;
            margin: 0 0 14px;
            border-top: 1px solid #a5aab1;
            border-bottom: 1px solid #a5aab1;
        }

        /* Full grey section blocks; single dark border under the section header only */
        .hotel-doc .h-section {
            background: #f3f4f6;
            margin-bottom: 10px;
        }
        .hotel-doc .h-section-title {
            background: #f3f4f6;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: #6b7280;
            padding: 8px 12px 7px;
            margin: 0;
            border: none;
            border-bottom: 1px solid #a5aab1;
        }
        .hotel-doc .h-section-body {
            background: #f3f4f6;
            padding: 8px 12px 10px;
        }

        .hotel-doc table.h-kv {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            background: #f3f4f6;
        }
        .hotel-doc table.h-kv td {
            padding: 5px 0;
            vertical-align: top;
            border: none;
            background: #f3f4f6;
        }
        .hotel-doc table.h-kv .lbl {
            width: 32%;
            color: #6b7280;
            font-weight: 400;
            padding-right: 10px;
        }
        .hotel-doc table.h-kv .val {
            color: #111827;
            font-weight: 400;
        }
        .hotel-doc .diet-lines {
            font-size: 11px;
            font-weight: 400;
            color: #111827;
            line-height: 1.55;
        }

        .hotel-doc table.h-rooms {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 11px;
            background: #f3f4f6;
        }
        .hotel-doc table.h-rooms th {
            text-align: left;
            font-size: 10px;
            font-weight: 600;
            color: #9ca3af;
            padding: 4px 6px 6px 0;
            border: none;
            background: #f3f4f6;
            text-transform: none;
            letter-spacing: 0;
        }
        .hotel-doc table.h-rooms td {
            text-align: left;
            font-weight: 400;
            color: #111827;
            padding: 2px 8px 6px 0;
            border: none;
            background: #f3f4f6;
            vertical-align: top;
            line-height: 1.45;
        }
        .hotel-doc .night-label {
            font-size: 10px;
            font-weight: 700;
            color: #6b7280;
            margin: 8px 0 4px;
            line-height: 1.4;
        }

        .hotel-doc table.h-program {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 11px;
            background: #f3f4f6;
        }
        .hotel-doc table.h-program th {
            text-align: left;
            font-size: 10px;
            font-weight: 600;
            color: #9ca3af;
            padding: 4px 8px 6px 0;
            border: none;
            background: #f3f4f6;
            text-transform: none;
            letter-spacing: 0;
        }
        .hotel-doc table.h-program td {
            padding: 7px 8px 7px 0;
            vertical-align: top;
            border: none;
            background: #f3f4f6;
            color: #374151;
        }
        .hotel-doc table.h-program .col-date {
            width: 22%;
            color: #4b5563;
            line-height: 1.4;
        }
        .hotel-doc table.h-program .col-name { width: 34%; font-weight: 400; color: #111827; }
        .hotel-doc table.h-program .col-notes { width: 44%; color: #4b5563; font-weight: 400; }
        .hotel-doc table.h-program tr.row-date-break td {
            border-bottom: 1px solid #a5aab1;
            padding-bottom: 8px;
        }
        .hotel-doc .night-block {
            margin-bottom: 4px;
            padding-bottom: 4px;
        }
        .hotel-doc .night-block-date-break {
            border-bottom: 1px solid #a5aab1;
            margin-bottom: 8px;
            padding-bottom: 8px;
        }

        .hotel-doc .notes-box {
            border: 1px solid #a5aab1;
            min-height: 56px;
            padding: 10px 12px;
            font-size: 11px;
            color: #374151;
            line-height: 1.5;
            background: #fff;
        }
        .hotel-doc .page-footer {
            margin-top: 18px;
            padding-top: 10px;
            border-top: 1px solid #a5aab1;
        }
    </style>
</head>
<body>
@php
    $pilotName = trim((string) ($event->assignedUser?->name ?? ''));
    $pilotPhone = trim((string) ($event->assignedUser?->phone ?? ''));
    $pilotLine = $pilotName !== '' ? $pilotName : '—';
    if ($pilotName !== '' && $pilotPhone !== '') {
        $pilotLine = $pilotName.' ('.$pilotPhone.')';
    }

    $driverName = trim((string) ($event->driver_name ?? ''));
    $driverPhone = trim((string) ($event->driver_phone ?? ''));
    $driverLine = $driverName !== '' ? $driverName : '—';
    if ($driverName !== '' && $driverPhone !== '') {
        $driverLine = $driverName.', '.$driverPhone;
    }

    $companyName = trim((string) ($company['name'] ?? 'Biuro Podróży RAFA'));
    $companyPhone = trim((string) ($company['phone'] ?? ''));
    $zamawiajacy = $companyName;
    if ($companyPhone !== '') {
        $zamawiajacy .= ', '.$companyPhone;
    }

    $hotelPlanDays = collect($hotelPlan ?? []);
    $programPoints = $hotelProgramPoints ?? ($event->eventTemplate?->hotelDays ?? []);

    $hotelDateKeys = [];

    $pushHotelDate = function ($value) use (&$hotelDateKeys): void {
        if ($value instanceof \DateTimeInterface) {
            $hotelDateKeys[] = $value->format('Y-m-d');
            return;
        }
        if (empty($value)) {
            return;
        }
        try {
            $hotelDateKeys[] = \Illuminate\Support\Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            // ignore invalid
        }
    };

    foreach ($hotelPlanDays as $planDay) {
        $nightNumber = (int) ($planDay['day'] ?? 0);
        if ($nightNumber <= 0) {
            continue;
        }
        $nightDate = method_exists($event, 'dateForProgramDay')
            ? $event->dateForProgramDay($nightNumber)
            : null;
        $pushHotelDate($nightDate);
    }

    foreach (collect($programPoints) as $hp) {
        $hpDate = $hp->start_date ?? $hp->date ?? $hp['start_date'] ?? $hp['date'] ?? null;
        if (empty($hpDate) && ! empty($hp->templatePoint?->start_date)) {
            $hpDate = $hp->templatePoint->start_date;
        }
        if (empty($hpDate)) {
            $dayIdx = (int) ($hp->day ?? $hp['day'] ?? 0);
            if ($dayIdx > 0 && method_exists($event, 'dateForProgramDay')) {
                $hpDate = $event->dateForProgramDay($dayIdx);
            }
        }
        $pushHotelDate($hpDate);
    }

    $hotelDateKeys = array_values(array_unique(array_filter($hotelDateKeys)));
    sort($hotelDateKeys);

    if ($hotelDateKeys !== []) {
        $terminStart = \Illuminate\Support\Carbon::parse($hotelDateKeys[0])->startOfDay();
        $terminEnd = \Illuminate\Support\Carbon::parse($hotelDateKeys[array_key_last($hotelDateKeys)])->startOfDay();
        $termin = $terminStart->format('d.m.Y');
        if ($terminStart->format('Y-m-d') !== $terminEnd->format('Y-m-d')) {
            $termin .= ' – '.$terminEnd->format('d.m.Y');
        }
    } else {
        $termin = (optional($event->start_date)->format('d.m.Y') ?: '—');
        if ($event->end_date) {
            $termin .= ' – '.$event->end_date->format('d.m.Y');
        }
    }

    $participants = max(0, (int) ($participantCount ?? $event->participant_count ?? 0));
    $gratis = max(0, (int) ($gratisCount ?? 0));
    $staff = max(0, (int) ($staffCount ?? 0));
    $drivers = max(0, (int) ($driverCount ?? 0));
    $uczestnicyLine = $participants.' osób';
    if ($gratis > 0) {
        $uczestnicyLine .= ' (w tim '.$gratis.' '.($gratis === 1 ? 'opiekun' : 'opiekunów').')';
    }
    $extras = [];
    if ($staff > 0) {
        $extras[] = $staff.' '.($staff === 1 ? 'pilot' : 'pilotów');
    } elseif ($pilotName !== '') {
        $extras[] = '1 pilot';
    }
    if ($drivers > 0) {
        $extras[] = $drivers.' '.($drivers === 1 ? 'kierowca' : 'kierowców');
    }
    if ($extras !== []) {
        $uczestnicyLine .= ' + '.implode(' i ', $extras);
    }

    $dietText = trim((string) ($event->diet_info ?? ''));
    if ($dietText === '' && ! empty($dietInfoLines) && is_array($dietInfoLines)) {
        $dietText = implode("\n", array_map('strval', $dietInfoLines));
    }

    $formatRoomSummary = function ($rooms): string {
        $rooms = collect($rooms ?? []);
        if ($rooms->isEmpty()) {
            return '—';
        }

        $normalizeRoomName = function (string $name, ?int $people): string {
            $raw = trim($name);
            $key = mb_strtolower(str_replace(['[', ']'], ['p', ''], $raw));
            $key = preg_replace('/\s+/', '', $key) ?? $key;

            $map = [
                'trpl' => 'triple',
                'triple' => 'triple',
                'dbl' => 'twin',
                'double' => 'twin',
                'twin' => 'twin',
                'sgl' => 'single',
                'sngl' => 'single',
                'sngle' => 'single',
                'single' => 'single',
                'quadr' => 'quadruple',
                'quad' => 'quadruple',
                'quadruple' => 'quadruple',
            ];

            if (isset($map[$key])) {
                return $map[$key];
            }

            if (mb_strlen($key) <= 8 && $people !== null) {
                return match ($people) {
                    1 => 'single',
                    2 => 'twin',
                    3 => 'triple',
                    4 => 'quadruple',
                    default => $raw,
                };
            }

            return $raw;
        };

        return $rooms
            ->groupBy(function ($room) use ($normalizeRoomName) {
                $people = isset($room['people_count']) ? (int) $room['people_count'] : null;

                return $normalizeRoomName((string) ($room['name'] ?? 'Pokój'), $people);
            })
            ->map(function ($group, $name) {
                $qty = $group->sum(fn ($room) => max(1, (int) ($room['quantity'] ?? 1)));
                $people = $group->first()['people_count'] ?? null;
                $label = $qty.' x '.$name;
                if ($people) {
                    $label .= ' '.$people.' os.';
                }

                return $label;
            })
            ->values()
            ->implode(', ');
    };
@endphp

<div class="hotel-doc">
    @include('pdf.packages._logo')

    <div class="doc-header">
        <div class="doc-title">Informacje dla Hotelu</div>
        <div class="doc-sub">{{ $event->name }}</div>
        <div class="doc-meta">
            @if($event->code)
                Nr wycieczki: <strong>{{ $event->code }}</strong>
                &nbsp;·&nbsp;
            @endif
            Termin: <strong>{{ $termin }}</strong>
        </div>
    </div>

    <div class="pilot-banner">
        Pilot: {{ $pilotLine }}
    </div>

    @include('pdf.packages._package_overrides_intro')

    {{-- Skrócony harmonogram przyjazdu / wyjazdu --}}
    <div class="h-section">
        <div class="h-section-title">Przyjazd / wyjazd</div>
        <div class="h-section-body">
            <table class="h-kv">
                <tr>
                    <td class="lbl">Przyjazd grupy</td>
                    <td class="val">{{ $travelLegends['departure'] ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="lbl">Wyjazd grupy</td>
                    <td class="val">{{ $travelLegends['return'] ?? '—' }}</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- DANE GRUPY --}}
    <div class="h-section">
        <div class="h-section-title">Dane grupy</div>
        <div class="h-section-body">
            <table class="h-kv">
                <tr>
                    <td class="lbl">Termin</td>
                    <td class="val">{{ $termin }}</td>
                </tr>
                <tr>
                    <td class="lbl">Pilot</td>
                    <td class="val">
                        @if($pilotName !== '')
                            {{ $pilotName }}@if($pilotPhone !== ''), {{ $pilotPhone }}@endif
                        @else
                            —
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="lbl">Zamawiający</td>
                    <td class="val">{{ $zamawiajacy }}</td>
                </tr>
                <tr>
                    <td class="lbl">Kierowca</td>
                    <td class="val">{{ $driverLine }}</td>
                </tr>
                <tr>
                    <td class="lbl">Uczestnicy łącznie</td>
                    <td class="val">{{ $uczestnicyLine }}</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- PROGRAM HOTELOWY --}}
    <div class="h-section">
        <div class="h-section-title">Program hotelowy</div>
        <div class="h-section-body">
            <table class="h-program">
                <thead>
                    <tr>
                        <th class="col-date">Data</th>
                        <th class="col-name">Program</th>
                        <th class="col-notes">Uwagi</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $programPointsList = collect($programPoints)->values();
                        $resolveProgramDateKey = function ($hp) use ($event): string {
                            $hpDate = $hp->start_date ?? $hp->date ?? $hp['start_date'] ?? $hp['date'] ?? null;
                            if (empty($hpDate) && ! empty($hp->templatePoint?->start_date)) {
                                $hpDate = $hp->templatePoint->start_date;
                            }
                            $dayIdx = (int) ($hp->day ?? $hp['day'] ?? 0);
                            if (empty($hpDate)) {
                                if ($dayIdx > 0 && ! empty($event->start_date)) {
                                    $hpDate = $event->start_date instanceof \DateTimeInterface
                                        ? $event->start_date->copy()->addDays(max(0, $dayIdx - 1))
                                        : \Illuminate\Support\Carbon::parse($event->start_date)->addDays(max(0, $dayIdx - 1));
                                } elseif (! empty($event->start_date)) {
                                    $hpDate = $event->start_date instanceof \DateTimeInterface
                                        ? $event->start_date
                                        : \Illuminate\Support\Carbon::parse($event->start_date);
                                }
                            }
                            if ($hpDate instanceof \DateTimeInterface) {
                                return $hpDate->format('Y-m-d');
                            }
                            if (! empty($hpDate)) {
                                try {
                                    return \Illuminate\Support\Carbon::parse((string) $hpDate)->format('Y-m-d');
                                } catch (\Throwable) {
                                    return (string) $hpDate;
                                }
                            }

                            return $dayIdx > 0 ? 'day-'.$dayIdx : '';
                        };
                    @endphp
                    @forelse($programPointsList as $index => $hp)
                        @php
                            $formatTimeHi = function ($value): string {
                                if ($value === null || $value === '') {
                                    return '';
                                }
                                if ($value instanceof \DateTimeInterface) {
                                    return $value->format('H:i');
                                }
                                $raw = trim((string) $value);
                                if ($raw === '') {
                                    return '';
                                }
                                if (preg_match('/(\d{1,2}):(\d{2})/', $raw, $m)) {
                                    return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
                                }

                                try {
                                    return \Illuminate\Support\Carbon::parse($raw)->format('H:i');
                                } catch (\Throwable) {
                                    return '';
                                }
                            };

                            $hpDate = $hp->start_date ?? $hp->date ?? $hp['start_date'] ?? $hp['date'] ?? null;
                            $hpTime = $hp->start_time ?? $hp->time ?? $hp['start_time'] ?? $hp['time'] ?? null;
                            $hpEndTime = $hp->end_time ?? $hp['end_time'] ?? null;

                            if (empty($hpDate) && ! empty($hp->templatePoint?->start_date)) {
                                $hpDate = $hp->templatePoint->start_date;
                            }
                            if (blank($hpTime) && ! empty($hp->templatePoint?->start_time)) {
                                $hpTime = $hp->templatePoint->start_time;
                            }
                            if (blank($hpEndTime) && ! empty($hp->templatePoint?->end_time)) {
                                $hpEndTime = $hp->templatePoint->end_time;
                            }

                            $dayIdx = (int) ($hp->day ?? $hp['day'] ?? 0);
                            if (empty($hpDate)) {
                                if ($dayIdx > 0 && ! empty($event->start_date)) {
                                    $hpDate = $event->start_date instanceof \DateTimeInterface
                                        ? $event->start_date->copy()->addDays(max(0, $dayIdx - 1))
                                        : \Illuminate\Support\Carbon::parse($event->start_date)->addDays(max(0, $dayIdx - 1));
                                } elseif (! empty($event->start_date)) {
                                    $hpDate = $event->start_date instanceof \DateTimeInterface
                                        ? $event->start_date
                                        : \Illuminate\Support\Carbon::parse($event->start_date);
                                }
                            }

                            if (blank($hpTime) && $dayIdx > 0 && method_exists($event, 'programDayStartTimeLabel')) {
                                $hpTime = $event->programDayStartTimeLabel($dayIdx);
                            }

                            $startHi = $formatTimeHi($hpTime);
                            $endHi = $formatTimeHi($hpEndTime);

                            if ($startHi !== '' && $endHi === '') {
                                $durH = (int) ($hp->duration_hours ?? $hp['duration_hours'] ?? 0);
                                $durM = (int) ($hp->duration_minutes ?? $hp['duration_minutes'] ?? 0);
                                if ($durH > 0 || $durM > 0) {
                                    try {
                                        $endHi = \Illuminate\Support\Carbon::createFromFormat('H:i', $startHi)
                                            ->addHours($durH)
                                            ->addMinutes($durM)
                                            ->format('H:i');
                                    } catch (\Throwable) {
                                        // ignore
                                    }
                                }
                            }

                            $timeLabel = $startHi;
                            if ($startHi !== '' && $endHi !== '') {
                                $timeLabel = $startHi.'-'.$endHi;
                            }

                            $dateLabel = '';
                            if ($hpDate instanceof \DateTimeInterface) {
                                $dateLabel = $hpDate->format('d.m.Y');
                            } elseif (! empty($hpDate)) {
                                try {
                                    $dateLabel = \Illuminate\Support\Carbon::parse((string) $hpDate)->format('d.m.Y');
                                } catch (\Throwable) {
                                    $dateLabel = (string) $hpDate;
                                }
                            }

                            $dataCellHtml = '';
                            if ($dateLabel !== '') {
                                $dataCellHtml .= e($dateLabel);
                            }
                            if ($timeLabel !== '') {
                                $dataCellHtml .= ($dataCellHtml !== '' ? '<br>' : '').e($timeLabel);
                            }
                            if ($dataCellHtml === '') {
                                $dataCellHtml = e($dayIdx > 0 ? (string) $dayIdx : '—');
                            }

                            $pointName = $hp->name ?? ($hp['name'] ?? ($hp->templatePoint?->name ?? '—'));
                            $notes = method_exists($hp, 'resolvedPilotNotes')
                                ? ($hp->resolvedPilotNotes() ?? null)
                                : ($hp['pilot_notes'] ?? ($hp->templatePoint?->pilot_notes ?? null));
                            $notesStr = is_string($notes) ? $notes : (filled($notes) ? (string) $notes : '');

                            $currentDateKey = $resolveProgramDateKey($hp);
                            $nextPoint = $programPointsList->get($index + 1);
                            $nextDateKey = $nextPoint !== null ? $resolveProgramDateKey($nextPoint) : $currentDateKey;
                            $dateBreak = $nextPoint !== null && $currentDateKey !== $nextDateKey;
                        @endphp
                        <tr @class(['row-date-break' => $dateBreak])>
                            <td class="col-date">{!! $dataCellHtml !!}</td>
                            <td class="col-name">{{ $pointName }}</td>
                            <td class="col-notes">
                                @if($notesStr === '')
                                    —
                                @elseif(strip_tags($notesStr) !== $notesStr)
                                    {!! $notesStr !!}
                                @else
                                    {!! nl2br(e($notesStr)) !!}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="muted">Brak punktów programu hotelowego</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- POKOJE --}}
    <div class="h-section">
        <div class="h-section-title">Pokoje</div>
        <div class="h-section-body">
            @forelse($hotelPlanDays->values() as $index => $day)
                @php
                    $nightNumber = (int) ($day['day'] ?? ($index + 1));
                    $nightDate = null;
                    if (method_exists($event, 'dateForProgramDay')) {
                        $nightDate = $event->dateForProgramDay($nightNumber);
                    } elseif (! empty($event->start_date)) {
                        $nightDate = $event->start_date instanceof \DateTimeInterface
                            ? $event->start_date->copy()->addDays(max(0, $nightNumber - 1))
                            : \Illuminate\Support\Carbon::parse($event->start_date)->addDays(max(0, $nightNumber - 1));
                    }
                    $nightDateLabel = $nightDate instanceof \DateTimeInterface
                        ? $nightDate->format('d.m.Y')
                        : '';
                    $nightDateKey = $nightDate instanceof \DateTimeInterface
                        ? $nightDate->format('Y-m-d')
                        : ('night-'.$nightNumber);
                    $hotelName = trim((string) ($day['hotel_name'] ?? ''));

                    $nextDay = $hotelPlanDays->values()->get($index + 1);
                    $dateBreak = false;
                    if ($nextDay !== null) {
                        $nextNightNumber = (int) ($nextDay['day'] ?? ($index + 2));
                        $nextNightDate = method_exists($event, 'dateForProgramDay')
                            ? $event->dateForProgramDay($nextNightNumber)
                            : null;
                        $nextDateKey = $nextNightDate instanceof \DateTimeInterface
                            ? $nextNightDate->format('Y-m-d')
                            : ('night-'.$nextNightNumber);
                        $dateBreak = $nightDateKey !== $nextDateKey;
                    }
                @endphp
                <div @class(['night-block', 'night-block-date-break' => $dateBreak])>
                    <div class="night-label">
                        @if($nightDateLabel !== '')
                            {{ $nightDateLabel }}<br>
                        @endif
                        Noc {{ $nightNumber }}@if($hotelName !== '') — {{ $hotelName }}@endif
                    </div>
                    @if(!empty($day['offer_notes']))
                        <p class="muted" style="margin:2px 0 6px;"><strong>W cenie:</strong> {{ strip_tags((string) $day['offer_notes']) }}</p>
                    @endif
                    <table class="h-rooms">
                        <thead>
                            <tr>
                                <th>Uczestnicy</th>
                                <th>Opieka</th>
                                <th>Pilot</th>
                                <th>Kierowca</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>{{ $formatRoomSummary($day['qty'] ?? []) }}</td>
                                <td>{{ $formatRoomSummary($day['gratis'] ?? []) }}</td>
                                <td>{{ $formatRoomSummary($day['staff'] ?? []) }}</td>
                                <td>{{ $formatRoomSummary($day['driver'] ?? []) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @empty
                <p class="muted">Brak danych noclegowych.</p>
            @endforelse
        </div>
    </div>

    {{-- DIETY --}}
    <div class="h-section">
        <div class="h-section-title">Diety</div>
        <div class="h-section-body">
            <div class="diet-lines">
                @if($dietText !== '')
                    {!! nl2br(e($dietText)) !!}
                @else
                    —
                @endif
            </div>
        </div>
    </div>

    {{-- NOTATKI --}}
    <div class="h-section">
        <div class="h-section-title">Informacje o wycieczce / notatki dla hotelu</div>
        <div class="h-section-body">
            <div class="notes-box">
                @if(!empty($hotelNotes))
                    {!! nl2br(e($hotelNotes)) !!}
                @else
                    —
                @endif
            </div>
        </div>
    </div>

    @include('pdf.packages._package_overrides_extra')

    @include('pdf.packages._attachments')
    @include('pdf.packages._footer')
</div>
</body>
</html>
