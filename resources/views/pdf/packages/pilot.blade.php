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

    <div class="section">
        <div class="section-title">Podstawowe informacje</div>
        <div class="section-body">
            <table class="rows">
                <tr>
                    <td class="lbl">Kierowca</td>
                    <td class="val">
                        {{ $event->driver_name ?: '—' }}
                        @if($event->driver_phone)
                            , {{ $event->driver_phone }}
                        @endif
                        @if($event->transport_company_name || $event->transportContractor)
                            <br><small>{{ $event->transportContractor?->name ?? $event->transport_company_name }}</small>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="lbl">Klient</td>
                    <td class="val">
                        {{ $event->client_name ?: '—' }}
                        @if($event->client_phone)
                            <br>{{ $event->client_phone }}
                        @endif
                        @if($event->client_email)
                            <br><small>{{ $event->client_email }}</small>
                        @endif
                        @if($event->contractor)
                            <br><small>{{ $event->contractor->name }}
                                @php
                                    $oc = $event->contractor;
                                    $oaddr = trim(implode(' ', array_filter([$oc->street, $oc->house_number, trim(($oc->postal_code ?? '').' '.$oc->city)])));
                                @endphp
                                @if($oaddr !== '')
                                    <br>{{ $oaddr }}
                                @endif
                            </small>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="lbl">Hotel / nocleg</td>
                    <td class="val">
                        @if(isset($hotelPlan) && $hotelPlan->isNotEmpty())
                            @foreach($hotelPlan as $day)
                                <div style="margin-bottom:6px;">
                                    <strong>Noc {{ $day['day'] }}</strong>
                                    @include('pdf.packages._hotel_night_contact', ['day' => $day])
                                </div>
                            @endforeach
                        @else
                            @php $hp0 = $hotelProgramPoints->first(); @endphp
                            @if($hp0 && $hp0->contractor)
                                @php
                                    $hotelMeta = \App\Support\ContractorContactDetails::operationalMeta($hp0->contractor, $hp0->contractorLocation);
                                @endphp
                                {{ $hp0->contractor->name }}
                                @if(! empty($hotelMeta['branch_name']))
                                    , {{ $hotelMeta['branch_name'] }}
                                @endif
                                @if(! empty($hotelMeta['phone']))
                                    , {{ $hotelMeta['phone'] }}
                                @endif
                                @if(! empty($hotelMeta['email']))
                                    , {{ $hotelMeta['email'] }}
                                @endif
                                @if(! empty($hotelMeta['address']))
                                    <br><small>{{ $hotelMeta['address'] }}</small>
                                @endif
                            @elseif($hp0)
                                {{ $hp0->name ?: ($hp0->templatePoint?->name ?? '—') }}
                            @else
                                —
                            @endif
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="lbl">Uczestnicy</td>
                    <td class="val">{{ $participantSummaryLine ?? '' }}</td>
                </tr>
                <tr>
                    <td class="lbl">Pilot</td>
                    <td class="val">
                        {{ $event->assignedUser?->name ?: '—' }}
                        @if($event->assignedUser?->birth_date)
                            <br><small>Data ur.: {{ $event->assignedUser->birth_date->format('d.m.Y') }}</small>
                        @endif
                        @if(filled($event->assignedUser?->pesel))
                            <br><small>PESEL: {{ $event->assignedUser->pesel }}</small>
                        @endif
                        @if(filled($event->assignedUser?->phone))
                            <br><small>Tel.: {{ $event->assignedUser->phone }}</small>
                        @endif
                    </td>
                </tr>
            </table>
        </div>
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
                    <td class="val">{{ $travelLegends['departure'] ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="lbl">Docelowe miejsce (pierwszy nocleg)</td>
                    <td class="val">{!! nl2br(e($travelLegends['destination'] ?? '—')) !!}</td>
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

    @if(isset($hotelPlan) && $hotelPlan->isNotEmpty() && ! in_array('hotel_plan', $hide_sections ?? [], true))
        <div class="section">
            <div class="section-title">Pokoje (szablon noclegów)</div>
            <div class="section-body">
                <table class="rows" style="width:100%;">
                    <thead>
                    <tr>
                        <th style="width:25%; text-align:center; font-size:9px; color:#6b7280;">Uczestnicy</th>
                        <th style="width:25%; text-align:center; font-size:9px; color:#6b7280;">{{ \App\Support\EventParticipantGroupLabels::GRATIS }}</th>
                        <th style="width:25%; text-align:center; font-size:9px; color:#6b7280;">Obsługa</th>
                        <th style="width:25%; text-align:center; font-size:9px; color:#6b7280;">Kierowca</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($hotelPlan as $day)
                        <tr>
                            <td colspan="4" style="font-weight: bold; padding-top:8px;">
                                Noc {{ $day['day'] }}
                                @if(!empty($day['hotel_name']))
                                    — {{ $day['hotel_name'] }}
                                    @if(!empty($day['hotel_branch']))
                                        ({{ $day['hotel_branch'] }})
                                    @endif
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td colspan="4">
                                @include('pdf.packages._hotel_night_contact', ['day' => $day])
                            </td>
                        </tr>
                        <tr>
                            <td style="text-align:center; vertical-align:top;">
                                @forelse($day['qty'] as $room)
                                    {{ $room['name'] }}@if($room['people_count']) ({{ $room['people_count'] }} os.)@endif<br>
                                @empty
                                    —
                                @endforelse
                            </td>
                            <td style="text-align:center; vertical-align:top;">
                                @forelse($day['gratis'] as $room)
                                    {{ $room['name'] }}@if($room['people_count']) ({{ $room['people_count'] }} os.)@endif<br>
                                @empty
                                    —
                                @endforelse
                            </td>
                            <td style="text-align:center; vertical-align:top;">
                                @forelse($day['staff'] as $room)
                                    {{ $room['name'] }}@if($room['people_count']) ({{ $room['people_count'] }} os.)@endif<br>
                                @empty
                                    —
                                @endforelse
                            </td>
                            <td style="text-align:center; vertical-align:top;">
                                @forelse($day['driver'] as $room)
                                    {{ $room['name'] }}@if($room['people_count']) ({{ $room['people_count'] }} os.)@endif<br>
                                @empty
                                    —
                                @endforelse
                            </td>
                        </tr>
                        @if(!empty($day['notes']))
                            <tr>
                                <td colspan="4"><small>Uwagi: {{ strip_tags((string) $day['notes']) }}</small></td>
                            </tr>
                        @endif
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @unless(in_array('program', $hide_sections ?? [], true))
        @include('pdf.packages._program_imprezy')
    @endunless

    @unless(in_array('pilot_set_finance', $hide_sections ?? [], true))
        @include('pdf.packages._pilot_set_finances', ['pilotSetFinanceCards' => $pilotSetFinanceCards ?? []])
    @endunless

    @unless(in_array('notes', $hide_sections ?? [], true))
        <div class="section">
            <div class="section-title">Notatki dla pilota</div>
            <div class="section-body">
                <div class="notes-field">
                    @php
                        $pn = trim(strip_tags((string) ($event->pilot_notes ?? '')));
                        $on = trim(strip_tags((string) ($event->office_notes ?? '')));
                    @endphp
                    @if($pn === '' && $on === '')
                        —
                    @else
                        @if($pn !== '')
                            <strong>Pilot:</strong> {!! nl2br(e($pn)) !!}
                        @endif
                        @if($on !== '')
                            @if($pn !== '')<br><br>@endif
                            <strong>Biuro:</strong> {!! nl2br(e($on)) !!}
                        @endif
                    @endif
                </div>
            </div>
        </div>
    @endunless

    @include('pdf.packages._package_overrides_extra')

    @unless(in_array('attachments_list', $hide_sections ?? [], true))
        @include('pdf.packages._attachments')
    @endunless
    @include('pdf.packages._footer')
</div>
</body>
</html>
