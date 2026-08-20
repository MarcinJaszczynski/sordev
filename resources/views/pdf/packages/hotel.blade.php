<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>{{ $audienceLabel }} — {{ $event->name }}</title>
    @include('pdf.packages._styles')
</head>
<body>
<div class="a4">
    <div style="padding-top:12px;"></div>
    @include('pdf.packages._logo')

    <div class="doc-header">
        <div class="doc-title">{{ $audienceLabel }}</div>
        <div class="doc-sub">{{ $event->name }}</div>
        <div class="doc-meta">
            @if($event->code)
                Nr / kod: <strong>{{ $event->code }}</strong>
                &nbsp;·&nbsp;
            @endif
            Termin:
            <strong>{{ optional($event->start_date)->format('d.m.Y') ?: '—' }}</strong>
            @if($event->end_date)
                – <strong>{{ $event->end_date->format('d.m.Y') }}</strong>
            @endif
            &nbsp;·&nbsp; Wygenerowano {{ $generatedAt->format('d.m.Y H:i') }}
        </div>
    </div>

    <hr class="divider">

    <div class="section">
        <div class="section-title">Kontakt operacyjny</div>
        <div class="section-body">
            <table class="rows">
                <tr>
                    <td class="lbl">Pilot</td>
                    <td class="val">{{ $event->assignedUser?->name ?: '—' }}@if(filled($event->assignedUser?->phone))<br><small>Tel.: {{ $event->assignedUser->phone }}</small>@endif</td>
                </tr>
                <tr>
                    <td class="lbl">Biuro</td>
                    <td class="val">{{ $company['phone'] ?? '—' }} / {{ $company['email'] ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="lbl">Uczestnicy</td>
                    <td class="val">{{ $participantSummaryLine ?? '—' }}</td>
                </tr>
            </table>
        </div>
    </div>

    @if(!empty($hotelNotes))
        <div class="section">
            <div class="section-title">Uwagi dla hotelu — {{ $hotelName ?? ($event->hotelProgramPoints->first()?->contractor?->name ?? ($event->hotelProgramPoints->first()?->name ?? '—')) }}</div>
            <div class="section-body">
                <div class="notes-field">{!! nl2br(e($hotelNotes)) !!}</div>
            </div>
        </div>
    @endif

    {{-- Zawsze renderuj sekcję Hotele/Noclegi; pokaż informację gdy brak wpisów --}}

    @php
        // Preferuj bezpośrednio przekazane hotelProgramPoints; jeśli brak — użyj hotelDays z szablonu
        $programPoints = $hotelProgramPoints ?? ($event->eventTemplate?->hotelDays ?? []);
    @endphp

    <div class="section">
        <div class="section-title">Hotele / noclegi w programie</div>
        <div class="section-body">
            <div class="program-day-title" style="margin-top:6px;">Punkty programu — hotele</div>
                <table class="program-table">
                <thead>
                    <tr>
                        <th style="width:15%;">Dzień</th>
                        <th style="width:28%;">Punkt</th>
                        <th style="width:27%;">Szczegóły</th>
                        <th style="width:30%;">Kontrahent</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(collect($programPoints) as $hp)
                        <tr>
                            @php
                                // Preferowane pola w modelu EventProgramPoint: start_date, start_time
                                // Dodatkowe fallbacky: pola z templatePoint albo różne nazwy w snapshotach
                                $hpDate = $hp->start_date ?? $hp->date ?? $hp['start_date'] ?? $hp['date'] ?? null;
                                $hpTime = $hp->start_time ?? $hp->time ?? $hp['start_time'] ?? $hp['time'] ?? null;

                                // Fallback do powiązanego templatePoint (jeśli punkt pochodzi ze szablonu)
                                if (empty($hpDate) && !empty($hp->templatePoint?->start_date)) {
                                    $hpDate = $hp->templatePoint->start_date;
                                }
                                if (empty($hpTime) && !empty($hp->templatePoint?->start_time)) {
                                    $hpTime = $hp->templatePoint->start_time;
                                }

                                // Jeżeli nadal brak daty, spróbuj obliczyć ją z daty wydarzenia + numeru dnia (jeżeli day jest ustawione)
                                if (empty($hpDate)) {
                                    $dayIdx = (int) ($hp->day ?? $hp['day'] ?? 0);
                                    if ($dayIdx > 0 && !empty($event->start_date)) {
                                        // event->start_date może być Carbon/DateTime lub string
                                        if ($event->start_date instanceof \DateTimeInterface) {
                                            $hpDate = $event->start_date->copy()->addDays(max(0, $dayIdx - 1));
                                        } else {
                                            $hpDate = \Illuminate\Support\Carbon::parse($event->start_date)->addDays(max(0, $dayIdx - 1));
                                        }
                                    } elseif (!empty($event->start_date)) {
                                        // użyj daty eventu gdy day nie jest dostępne
                                        $hpDate = $event->start_date instanceof \DateTimeInterface
                                            ? $event->start_date
                                            : \Illuminate\Support\Carbon::parse($event->start_date);
                                    }
                                }
                            @endphp
                            <td>
                                @if($hpDate instanceof \DateTimeInterface)
                                    {{ $hpDate->format('d.m.Y') }}@if(!empty($hpTime)) {{ $hpTime }}@elseif($hpDate->format('H:i') !== '00:00') {{ $hpDate->format('H:i') }}@endif
                                @elseif(!empty($hpDate))
                                    {{ $hpDate }}@if(!empty($hpTime)) {{ $hpTime }}@endif
                                @else
                                    {{ (int) ($hp->day ?? ($hp['day'] ?? 1)) }}
                                @endif
                            </td>
                            <td>{{ $hp->name ?? ($hp['name'] ?? ($hp->templatePoint?->name ?? '—')) }}</td>
                            @php
                                $notes = $hp->resolvedPilotNotes() ?? $hp['pilot_notes'] ?? ($hp->templatePoint?->pilot_notes ?? null);
                                $notesStr = is_string($notes) ? $notes : (filled($notes) ? (string) $notes : null);
                            @endphp
                            <td>
                                @if(empty($notesStr))
                                    —
                                @elseif(strip_tags($notesStr) !== $notesStr)
                                    {{-- Zawiera HTML — renderuj jako HTML (PDF) --}}
                                    {!! $notesStr !!}
                                @else
                                    {{-- Zwykły tekst — escapuj i zachowaj nowe linie --}}
                                    {!! nl2br(e($notesStr)) !!}
                                @endif
                            </td>
                            <td>{{ $hp->contractor?->name ?? ($hp['contractor']?->name ?? '—') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">Brak punktów programu przypisanych do hotelu</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Plan pokoi{{ !empty($usesEventHotelPlan) ? ' (impreza)' : ' (wg szablonu)' }}</div>
        <div class="section-body">
            @forelse($hotelPlan as $day)
                <div class="program-day-title" style="margin-top:6px;">
                    Noc {{ $day['day'] }}
                    @if(!empty($day['hotel_name']))
                        — {{ $day['hotel_name'] }}
                    @endif
                </div>

                @if(!empty($day['offer_notes']))
                    <p class="muted" style="margin:4px 0;"><strong>W cenie:</strong> {{ strip_tags((string) $day['offer_notes']) }}</p>
                @endif

                @if(!empty($day['uses_event_plan']))
                    <table class="program-table" style="margin-bottom:8px;">
                        <thead>
                            <tr>
                                <th>Rola</th>
                                <th>Pokój</th>
                                <th>Ilość</th>
                                <th>Osoby</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (\App\Support\EventParticipantGroupLabels::hotelRoleLabels() as $roleKey => $roleLabel)
                                @foreach(($day[$roleKey] ?? collect()) as $room)
                                    <tr>
                                        <td>{{ $roleLabel }}</td>
                                        <td>{{ $room['name'] ?? '—' }}</td>
                                        <td>{{ $room['quantity'] ?? 1 }}</td>
                                        <td>{{ !empty($room['occupants']) ? implode(', ', $room['occupants']) : '—' }}</td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <table class="program-table" style="margin-bottom:8px;">
                        <thead>
                            <tr>
                                <th>Uczestnicy</th>
                                <th>{{ \App\Support\EventParticipantGroupLabels::GRATIS }}</th>
                                <th>Obsługa</th>
                                <th>Kierowca</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    @forelse($day['qty'] as $room)
                                        {{ $room['name'] }}@if($room['people_count'] ?? null) ({{ $room['people_count'] }} os.)@endif<br>
                                    @empty — @endforelse
                                </td>
                                <td>
                                    @forelse($day['gratis'] as $room)
                                        {{ $room['name'] }}@if($room['people_count'] ?? null) ({{ $room['people_count'] }} os.)@endif<br>
                                    @empty — @endforelse
                                </td>
                                <td>
                                    @forelse($day['staff'] as $room)
                                        {{ $room['name'] }}@if($room['people_count'] ?? null) ({{ $room['people_count'] }} os.)@endif<br>
                                    @empty — @endforelse
                                </td>
                                <td>
                                    @forelse($day['driver'] as $room)
                                        {{ $room['name'] }}@if($room['people_count'] ?? null) ({{ $room['people_count'] }} os.)@endif<br>
                                    @empty — @endforelse
                                </td>
                            </tr>
                        </tbody>
                    </table>
                @endif

                @if(!empty($day['notes']))
                    <p class="muted" style="margin-bottom:8px;"><small>Uwagi: {{ strip_tags((string) $day['notes']) }}</small></p>
                @endif
            @empty
                <p class="muted">Brak danych noclegowych.</p>
            @endforelse
        </div>
    </div>

                    @if(!empty($dietInfoLines) && count($dietInfoLines) > 0)
                        <div class="section">
                            <div class="section-title">Diety</div>
                            <div class="section-body">
                                <div class="program-day-title" style="margin-top:6px;">Diety</div>
                                <table class="program-table">
                                    <thead>
                                        <tr>
                                            <th>RODZAJ DIETY</th>
                                            <th style="width:90px; text-align:right;">ILOŚĆ OSÓB</th>
                                            <th>UWAGI</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($dietInfoLines as $line)
                                            @php
                                                // Parsowanie linii: obsługuje formaty typu "N — X os.", "2x Bezglutenowa", "Bezglutenowa: 2", "2 Bezglutenowa"
                                                $name = $line;
                                                $count = null;
                                                $remark = '—';

                                                if (preg_match('/^(.*?)\s*[—\-]\s*(\d+)\s*os\.?$/u', $line, $m)) {
                                                    $name = trim($m[1]);
                                                    $count = (int) $m[2];
                                                } elseif (preg_match('/^(\d+)\s*[x×]\s*(.+)$/u', $line, $m)) {
                                                    $count = (int) $m[1];
                                                    $name = trim($m[2]);
                                                } elseif (preg_match('/^(.+?)\s*[:\-–]\s*(\d+)$/u', $line, $m)) {
                                                    $name = trim($m[1]);
                                                    $count = (int) $m[2];
                                                } elseif (preg_match('/^(\d+)\s+(.+)$/u', $line, $m)) {
                                                    $count = (int) $m[1];
                                                    $name = trim($m[2]);
                                                }
                                            @endphp

                                            <tr>
                                                <td>{{ $name }}</td>
                                                <td style="text-align:right;">{{ $count !== null ? $count : '—' }}</td>
                                                <td>{{ $remark }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

    @include('pdf.packages._attachments')
    @include('pdf.packages._footer')
</div>
</body>
</html>
