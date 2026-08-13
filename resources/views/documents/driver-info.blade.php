<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Informacje dla kierowcy — {{ $event->name ?? '' }}</title>
    @include('pdf.packages._styles')
</head>
<body>
<div class="a4">
    @include('pdf.packages._logo')

    <div class="doc-header">
        <div class="doc-title">INFORMACJE DLA KIEROWCY</div>
        <div class="doc-sub">{{ $eventTitleLine ?? ($event->name ?? '') }}</div>
        <div class="doc-pilot">
            <strong>Pilot:</strong>
            {{ $pilot['name'] ?? '—' }}
            @if(!empty($pilot['phone']))
                (tel.: {{ $pilot['phone'] }})
            @endif
        </div>
    </div>

    <hr class="divider">

    @include('pdf.packages._package_overrides_intro')

    <div class="driver-box">
        <div class="driver-box-title">Podstawienie / przejazd</div>
        <table class="rows">
            <tr>
                <td class="lbl">Podstawienie</td>
                <td class="val">
                    <span class="driver-time">
                        @if(!empty($pickup['time']))godz. {{ $pickup['time'] }}@endif
                        @if(!empty($pickup['date'])) {{ $pickup['date'] }}@endif
                        @if(empty($pickup['time']) && empty($pickup['date']))—@endif
                    </span>
                    <br>
                    <span style="font-weight:normal; font-size:11px; font-family: DejaVu Sans, sans-serif;">
                        {!! nl2br(e($pickup['place'] ?? '—')) !!}
                    </span>
                </td>
            </tr>
            <tr>
                <td class="lbl">Odjazd</td>
                <td class="val">
                    <span class="driver-time">
                        @if(!empty($departure['time']))godz. {{ $departure['time'] }}@endif
                        @if(!empty($departure['date'])) {{ $departure['date'] }}@endif
                        @if(empty($departure['time']) && empty($departure['date']))—@endif
                    </span>
                </td>
            </tr>
            <tr>
                <td class="lbl">Powrót</td>
                <td class="val">
                    <span class="driver-time">
                        @if(!empty($return['time']))godz. {{ $return['time'] }}@endif
                        @if(!empty($return['date'])) {{ $return['date'] }}@endif
                        @if(empty($return['time']) && empty($return['date']))—@endif
                    </span>
                    @if(!empty($travelLegends['return_place']) && ($travelLegends['return_place'] ?? '—') !== '—')
                        <br>
                        <span style="font-weight:normal; font-size:11px; font-family: DejaVu Sans, sans-serif;">
                            {!! nl2br(e($travelLegends['return_place'])) !!}
                        </span>
                    @endif
                </td>
            </tr>
            <tr>
                <td class="lbl">Ilość uczestników</td>
                <td class="val">
                    <span class="driver-hl">{{ $passengerSummary ?? ($participantSummaryLine ?? '—') }}</span>
                </td>
            </tr>
        </table>
    </div>

    @unless(in_array('notes', $hide_sections ?? [], true))
        <div class="section">
            <div class="section-title">Informacje o wycieczce</div>
            <div class="section-body">
                <div class="notes-field">
                    @php
                        $dn = trim((string) ($driverNotes ?? ''));
                        $bn = trim((string) ($busInfo ?? ''));
                    @endphp
                    @if($dn === '' && $bn === '')
                        —
                    @else
                        @if($dn !== ''){!! nl2br(e($dn)) !!}@endif
                        @if($bn !== '')@if($dn !== '')<br><br>@endif<strong>Autokar:</strong> {!! nl2br(e($bn)) !!}@endif
                    @endif
                </div>
            </div>
        </div>
    @endunless

    @unless(in_array('hotel_plan', $hide_sections ?? [], true))
        <div class="section">
            <div class="section-title">Hotele</div>
            <div class="section-body">
                @forelse($hotels ?? [] as $hotel)
                    <div class="hotel-block">
                        <div class="hotel-name">{{ $hotel['name'] }}</div>
                        @if(!empty($hotel['branch']))
                            <div class="hotel-branch">{{ $hotel['branch'] }}</div>
                        @endif
                        <div class="hotel-addr">{{ $hotel['address'] ?? '—' }}</div>
                        @if(!empty($hotel['phone']))
                            <div class="hotel-tel">{{ $hotel['phone'] }}</div>
                        @endif
                        @if(!empty($hotel['days']))
                            <div class="muted">{{ $hotel['days'] }}</div>
                        @endif
                    </div>
                @empty
                    <p class="muted">Brak hoteli w planie imprezy.</p>
                @endforelse
            </div>
        </div>
    @endunless

    @include('pdf.packages._package_overrides_extra')

    @if(! in_array('attachments_list', $hide_sections ?? [], true) && !empty($attachedFiles) && (is_countable($attachedFiles) ? count($attachedFiles) > 0 : $attachedFiles->isNotEmpty()))
        <div class="files">
            <div class="files-title">Powiązane pliki</div>
            <ul class="files-list">
                @foreach($attachedFiles as $file)
                    <li>{{ $file['label'] ?? ($file['document_label'] ?? '—') }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @include('pdf.packages._footer')
</div>
</body>
</html>
