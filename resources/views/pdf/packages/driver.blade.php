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
        </div>
    </div>

    <hr class="divider">

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
                    <td class="val val--departure">{{ $travelLegends['departure'] ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="lbl">Miejsce podstawienia</td>
                    <td class="val">{!! nl2br(e($event->pickup_place_details ? strip_tags($event->pickup_place_details) : ($event->startPlace?->name ?? '—'))) !!}</td>
                </tr>
                <tr>
                    <td class="lbl">Szkoła / zamawiający</td>
                    <td class="val">
                        @if($event->contractor)
                            {{ $event->contractor->name }}<br>
                            <small>{{ trim(implode(' ', array_filter([$event->contractor->street, $event->contractor->house_number, trim(($event->contractor->postal_code ?? '').' '.$event->contractor->city)]))) ?: '—' }}</small>
                        @else
                            {{ $event->client_name ?: '—' }}
                        @endif
                    </td>
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
                    <td class="val val--departure">{{ $travelLegends['departure'] ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="lbl">Docelowe miejsce</td>
                    <td @class([
                        'val',
                        'val--destination' => filled($travelLegends['destination'] ?? null) && ($travelLegends['destination'] ?? '—') !== '—',
                    ])>{!! nl2br(e($travelLegends['destination'] ?? '—')) !!}</td>
                </tr>
                <tr>
                    <td class="lbl">Powrót</td>
                    <td @class([
                        'val',
                        'val--return' => filled($travelLegends['return'] ?? null)
                            && str_contains((string) ($travelLegends['return'] ?? ''), 'godz.'),
                    ])>{{ $travelLegends['return'] ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="lbl">Miejsce powrotu</td>
                    <td @class([
                        'val',
                        'val--return-place-diff' => ($travelLegends['colors']['return_place'] ?? '') === '#7c3aed',
                        'val--return-place' => ($travelLegends['colors']['return_place'] ?? '') !== '#7c3aed',
                    ])>{!! nl2br(e($travelLegends['return_place'] ?? '—')) !!}</td>
                </tr>
            </table>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Uczestnicy</div>
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
            </table>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Hotel / nocleg</div>
        <div class="section-body">
            @php $hp0 = $hotelProgramPoints->first(); @endphp
            @if($hp0 && $hp0->contractor)
                @php
                    $hotelMeta = \App\Support\ContractorContactDetails::operationalMeta($hp0->contractor, $hp0->contractorLocation);
                @endphp
                <div class="hotel-name">{{ $hp0->contractor->name }}</div>
                @if(! empty($hotelMeta['branch_name']))
                    <div class="hotel-branch">{{ $hotelMeta['branch_name'] }}</div>
                @endif
                <div class="hotel-addr">{{ $hotelMeta['address'] ?? '—' }}</div>
                @if($hp0->contractorLocation?->region)
                    <div class="hotel-region">{{ $hp0->contractorLocation->region }}</div>
                @elseif($hp0->contractor->region)
                    <div class="hotel-region">{{ $hp0->contractor->region }}</div>
                @endif
                @if(! empty($hotelMeta['phone']))
                    <div class="hotel-tel">{{ $hotelMeta['phone'] }}</div>
                @endif
            @elseif($hp0)
                <div class="hotel-name">{{ $hp0->name ?: ($hp0->templatePoint?->name ?? '—') }}</div>
            @else
                <p class="muted">Brak punktu hotelowego w programie.</p>
            @endif
        </div>
    </div>

    <div class="section">
        <div class="section-title">Informacje o wycieczce / notatki</div>
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

    @include('pdf.packages._attachments')
    @include('pdf.packages._footer')
</div>
</body>
</html>
