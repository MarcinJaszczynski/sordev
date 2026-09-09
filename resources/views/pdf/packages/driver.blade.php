<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>{{ $audienceLabel }} — {{ $event->name }}</title>
    @include('pdf.packages._styles')
</head>
<body>
<div class="a4">
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
            &nbsp;·&nbsp; Wygenerowano: {{ $generatedAt->format('d.m.Y H:i') }}
        </div>
    </div>

    <hr class="divider">

    @include('pdf.packages._package_overrides_intro')

    <div class="doc-pilot">
        <strong>Pilot:</strong> {{ $event->assignedUser?->name ?: '—' }}@if(filled($event->assignedUser?->phone)) · tel. {{ $event->assignedUser->phone }}@endif
        @if($company['phone'] ?? false)
            &nbsp;|&nbsp; <strong>Biuro:</strong> {{ $company['phone'] }}
        @endif
    </div>

    <div class="section">
        <div class="section-title">Podstawienie autokaru</div>
        <div class="section-body">
            <table class="rows">
                <tr>
                    <td class="lbl">Data i godzina podstawienia</td>
                    <td class="val">{{ $travelLegends['departure'] ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="lbl">Miejsce podstawienia</td>
                    <td class="val">{!! nl2br(e($event->adress_transport_start ? strip_tags($event->adress_transport_start) : ($event->pickup_place_details ? strip_tags($event->pickup_place_details) : ($event->startPlace?->name ?? '—')))) !!}</td>
                </tr>
            </table>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Harmonogram przejazdu</div>
        <div class="section-body">
            <table class="rows">
                <tr>
                    <td class="lbl">Odjazd / start</td>
                    <td class="val">{{ $travelLegends['departure'] ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="lbl">Docelowe miejsce</td>
                    <td class="val">{!! nl2br(e($event->adress_transport_end ? strip_tags($event->adress_transport_end) : ($travelLegends['destination'] ?? '—'))) !!}</td>
                </tr>
                <tr>
                    <td class="lbl">Powrót</td>
                    <td class="val">{{ $travelLegends['return'] ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="lbl">Miejsce powrotu</td>
                    <td class="val">{!! nl2br(e($travelLegends['return_place'] ?? '—')) !!}</td>
                </tr>
            </table>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Trasa dzień po dniu</div>
        <div class="section-body">
            <table class="program-table">
                <colgroup>
                    <col style="width: 8%;">
                    <col style="width: 14%;">
                    <col style="width: 12%;">
                    <col style="width: 33%;">
                    <col style="width: 33%;">
                </colgroup>
                <thead>
                    <tr>
                        <th>Dzień</th>
                        <th>Data</th>
                        <th>Godz.</th>
                        <th>Skąd</th>
                        <th>Dokąd</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($driverDayRoutes ?? []) as $leg)
                        <tr>
                            <td>{{ $leg['day'] }}</td>
                            <td>{{ $leg['date'] }}</td>
                            <td>{{ $leg['time'] }}</td>
                            <td>{{ $leg['from'] }}</td>
                            <td>{{ $leg['to'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="muted">Brak trasy dzień po dniu — uzupełnij program / trasy dni.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Uczestnicy / autokar</div>
        <div class="section-body">
            <table class="rows">
                <tr>
                    <td class="lbl">Liczba uczestników</td>
                    <td class="val">{{ $participantSummaryLine ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="lbl">Autokar</td>
                    <td class="val">{{ $event->bus?->name ?: '—' }} @if($event->vehicle_registration) (rej. {{ $event->vehicle_registration }}) @endif</td>
                </tr>
                <tr>
                    <td class="lbl">Stan licznika — początek</td>
                    <td class="val"><span class="handwrite-tall"></span></td>
                </tr>
                <tr>
                    <td class="lbl">Stan licznika — koniec</td>
                    <td class="val"><span class="handwrite-tall"></span></td>
                </tr>
            </table>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Noclegi / parkingi</div>
        <div class="section-body">
            @php
                $hotelNights = collect($hotelPlan ?? [])->filter(fn ($day) => filled($day['hotel_name'] ?? null) || filled($day['hotel_address'] ?? null));
                $hp0 = $hotelProgramPoints->first();
            @endphp
            @if($hotelNights->isNotEmpty())
                @foreach($hotelNights as $day)
                    <div style="margin-bottom:8px;">
                        <div class="hotel-name">Noc {{ $day['day'] ?? '' }} — {{ $day['hotel_name'] ?? 'Hotel' }}</div>
                        @include('pdf.packages._hotel_night_contact', ['day' => $day])
                    </div>
                @endforeach
            @elseif($hp0 && $hp0->contractor)
                @php
                    $hotelMeta = \App\Support\ContractorContactDetails::operationalMeta($hp0->contractor, $hp0->contractorLocation);
                @endphp
                <div class="hotel-name">{{ $hp0->contractor->name }}</div>
                @if(! empty($hotelMeta['branch_name']))
                    <div class="hotel-branch">{{ $hotelMeta['branch_name'] }}</div>
                @endif
                <div class="hotel-addr">{{ $hotelMeta['address'] ?? '—' }}</div>
                @if(! empty($hotelMeta['phone']))
                    <div class="hotel-tel">{{ $hotelMeta['phone'] }}</div>
                @endif
            @elseif($hp0)
                <div class="hotel-name">{{ $hp0->name ?: ($hp0->templatePoint?->name ?? '—') }}</div>
            @else
                <p class="muted">Brak danych noclegowych.</p>
            @endif
        </div>
    </div>

    <div class="section">
        <div class="section-title">Notatki dla kierowcy</div>
        <div class="section-body">
            <div class="notes-field">
                @php
                    $dn = trim(strip_tags((string) ($event->driver_notes ?? '')));
                    $bn = trim(strip_tags((string) ($event->bus_info ?? '')));
                @endphp
                @if($dn === '' && $bn === '')
                    —
                @else
                    @if($dn !== '')<strong>Kierowca:</strong> {!! nl2br(e($dn)) !!}@endif
                    @if($bn !== '')@if($dn !== '')<br><br>@endif<strong>Autokar:</strong> {!! nl2br(e($bn)) !!}@endif
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
