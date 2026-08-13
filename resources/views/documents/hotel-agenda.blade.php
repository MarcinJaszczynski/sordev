<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Agenda dla hotelu — {{ $hotel['name'] ?? 'Hotel' }}</title>
    @include('pdf.packages._styles')
</head>
<body>
<div class="a4">
    @include('pdf.packages._logo')

    <div class="doc-header">
        <div class="doc-title agenda-title">AGENDA DLA HOTELU</div>
        <div class="doc-sub">{{ $event->name ?? '' }}@if(!empty($termLine)): {{ $termLine }}@endif</div>
        <div class="doc-meta">
            @if(!empty($termLine))
                Termin: <strong>{{ $termLine }}</strong>
            @endif
            @if(!empty($event->code))
                &nbsp;·&nbsp; Nr: <strong>{{ $event->code }}</strong>
            @endif
        </div>
    </div>

    <hr class="divider">

    <table class="meta-grid">
        <tr>
            <td class="col-left">
                <div class="kv-label">Pilot</div>
                <div class="kv-value">
                    {{ $pilot['name'] ?? '—' }}
                    @if(!empty($pilot['phone']))
                        <span class="sub">tel.: {{ $pilot['phone'] }}</span>
                    @endif
                </div>
            </td>
            <td class="col-right">
                <div class="kv-label">Kierowca</div>
                <div class="kv-value">
                    {{ $driver['name'] ?? '—' }}
                    @if(!empty($driver['phone']))
                        <span class="sub">tel.: {{ $driver['phone'] }}</span>
                    @endif
                </div>
            </td>
        </tr>
        <tr>
            <td class="col-left">
                <div class="kv-label">Zamawiający</div>
                <div class="kv-value">
                    {{ $company['name'] ?? 'Biuro Podróży RAFA' }}
                    @if(!empty($company['phone']))
                        <span class="sub">tel.: {{ $company['phone'] }}</span>
                    @endif
                    @if(!empty($company['email']))
                        <span class="sub">{{ $company['email'] }}</span>
                    @endif
                </div>
            </td>
            <td class="col-right">
                <div class="kv-label">Uczestnicy łącznie</div>
                <div class="kv-value">{{ $participantSummary ?? '—' }}</div>
                <div class="kv-label" style="margin-top:8px;">Dieta</div>
                <div class="kv-value" style="font-weight:normal; font-size:11px;">
                    {!! nl2br(e($dietSummary ?: 'Brak zgłoszonych diet.')) !!}
                </div>
            </td>
        </tr>
    </table>

    <div class="hotel-program-label">
        Program: {{ $hotel['name'] ?? '—' }}
        @if(!empty($hotel['branch']))
            — {{ $hotel['branch'] }}
        @endif
    </div>
    @if(!empty($hotel['address']) || !empty($hotel['phone']))
        <p class="muted" style="margin-bottom:8px;">
            @if(!empty($hotel['address'])){{ $hotel['address'] }}@endif
            @if(!empty($hotel['phone']))
                @if(!empty($hotel['address'])) · @endif
                tel. {{ $hotel['phone'] }}
            @endif
        </p>
    @endif

    @if(!empty($hotelNotes))
        <div class="section" style="margin-bottom:8px;">
            <div class="section-title">Uwagi dla hotelu</div>
            <div class="section-body">
                <div class="notes-field">{!! nl2br(e($hotelNotes)) !!}</div>
            </div>
        </div>
    @endif

    <table class="program-table agenda-table">
        <thead>
            <tr>
                <th class="cell-date">Data</th>
                <th style="width:38%;">Program</th>
                <th style="width:40%;">Uwagi</th>
            </tr>
        </thead>
        <tbody>
            @forelse($scheduleRows ?? [] as $row)
                <tr>
                    <td class="cell-date">
                        @if(!empty($row['time']))
                            <span class="time">{{ $row['time'] }}</span>
                        @endif
                        <span class="day">{{ $row['date'] ?? ($row['datetime'] ?? '—') }}</span>
                    </td>
                    <td>{{ $row['program'] ?? '—' }}</td>
                    <td>{!! nl2br(e($row['notes'] ?? '—')) !!}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="muted">Brak pozycji hotelowych / posiłków przypisanych do tego obiektu.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if(!empty($attachedFiles))
        <div class="files" style="margin-top:12px;">
            <div class="files-title">Pliki</div>
            <ul class="files-list">
                @foreach($attachedFiles as $file)
                    <li>{{ $file['label'] ?? '—' }}@if(!empty($file['type'])) <span class="muted">({{ $file['type'] }})</span>@endif</li>
                @endforeach
            </ul>
            <div class="files-note">Pełne pliki dołączane są do paczki ZIP przy pobieraniu pakietu hotelowego.</div>
        </div>
    @endif

    @include('pdf.packages._footer')
</div>
</body>
</html>
