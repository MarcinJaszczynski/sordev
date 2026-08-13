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
            &nbsp;·&nbsp; Status: <strong>{{ $event->status ?? '—' }}</strong>
            &nbsp;·&nbsp; Wygenerowano: {{ $generatedAt->format('d.m.Y H:i') }}
        </div>
    </div>

    <hr class="divider">

    @include('pdf.packages._package_overrides_intro')

    <div class="section">
        <div class="section-title">Podsumowanie</div>
        <div class="section-body">
            <table class="rows">
                <tr><td class="lbl">ID imprezy</td><td class="val">#{{ $event->id }}</td></tr>
                <tr><td class="lbl">Klient</td><td class="val">{{ $event->client_name ?: '—' }}</td></tr>
                <tr><td class="lbl">Uczestnicy</td><td class="val">{{ $participantSummaryLine ?? '—' }}</td></tr>
                <tr><td class="lbl">Pilot</td><td class="val">{{ $event->assignedUser?->name ?: '—' }}@if(filled($event->assignedUser?->phone))<br><small>Tel.: {{ $event->assignedUser->phone }}</small>@endif</td></tr>
                <tr><td class="lbl">Start</td><td class="val">{{ $event->startPlace?->name ?: '—' }}</td></tr>
            </table>
        </div>
    </div>

    @unless(in_array('program', $hide_sections ?? [], true))
        @include('pdf.packages._program_imprezy')
    @endunless

    @unless(in_array('pilot_set_finance', $hide_sections ?? [], true))
        @include('pdf.packages._pilot_set_finances', ['pilotSetFinanceCards' => $pilotSetFinanceCards ?? []])
    @endunless

    @if(! in_array('hotel_plan', $hide_sections ?? [], true) && (!empty($hotelNotes) || (isset($hotelProgramPoints) && $hotelProgramPoints->isNotEmpty()) || (isset($hotelPlan) && $hotelPlan->isNotEmpty())))
        <div class="section">
            <div class="section-title">Hotele</div>
            <div class="section-body">
                @if(!empty($hotelNotes))
                    <p><strong>Uwagi:</strong> {!! nl2br(e($hotelNotes)) !!}</p>
                @endif
                @if(isset($hotelProgramPoints) && $hotelProgramPoints->isNotEmpty())
                    <table class="program-table" style="margin-top:8px;">
                        <thead>
                            <tr>
                                <th>Dzień</th>
                                <th>Punkt</th>
                                <th>Hotel</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($hotelProgramPoints as $hp)
                                <tr>
                                    <td>{{ (int) ($hp->day ?? 1) }}</td>
                                    <td>{{ $hp->name ?: ($hp->templatePoint?->name ?? '—') }}</td>
                                    <td>{{ $hp->contractor?->name ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
                @foreach($hotelPlan as $day)
                    <div class="program-day-title" style="margin-top:8px;">Dzień {{ $day['day'] }} — pokoje</div>
                    <table class="program-table">
                        <thead>
                            <tr><th>Uczestnicy</th><th>{{ \App\Support\EventParticipantGroupLabels::GRATIS }}</th><th>Obsługa</th><th>Kierowca</th></tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>@forelse($day['qty'] as $room){{ $room['name'] }}@if($room['people_count']) ({{ $room['people_count'] }})@endif<br>@empty — @endforelse</td>
                                <td>@forelse($day['gratis'] as $room){{ $room['name'] }}<br>@empty — @endforelse</td>
                                <td>@forelse($day['staff'] as $room){{ $room['name'] }}<br>@empty — @endforelse</td>
                                <td>@forelse($day['driver'] as $room){{ $room['name'] }}<br>@empty — @endforelse</td>
                            </tr>
                        </tbody>
                    </table>
                @endforeach
            </div>
        </div>
    @endif

    <div class="section">
        <div class="section-title">Transport</div>
        <div class="section-body">
            <table class="rows">
                <tr><td class="lbl">Autokar</td><td class="val">{{ $event->bus?->name ?: '—' }}</td></tr>
                <tr><td class="lbl">Kierowca</td><td class="val">{{ $event->driver_name ?: '—' }} @if($event->driver_phone) / {{ $event->driver_phone }} @endif</td></tr>
                <tr><td class="lbl">Rejestracja</td><td class="val">{{ $event->vehicle_registration ?: '—' }}</td></tr>
                <tr><td class="lbl">Podstawienie</td><td class="val">{{ $travelLegends['departure'] ?? '—' }}</td></tr>
            </table>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Umowy indywidualne</div>
        <div class="section-body">
            <p class="muted" style="margin-bottom:6px;">
                Zawarto: {{ $agreementsSummary['total'] ?? 0 }} |
                Opłacone wg grupy: {{ $agreementsSummary['payment_progress_label'] ?? '—' }} |
                Wpłacono: {{ \App\Support\MoneyFormatter::format($agreementsSummary['amount_paid'] ?? 0) }}
            </p>
            <table class="ag-table">
                <thead>
                    <tr>
                        <th>Nr</th>
                        <th>Uczestnik</th>
                        <th>Płatność</th>
                        <th>Należność</th>
                        <th>Wpłata</th>
                        <th>Do zapłaty</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($individualAgreementRows ?? []) as $row)
                        <tr>
                            <td>{{ $row['agreement_number'] }}</td>
                            <td>{{ $row['participant_name'] }}</td>
                            <td>{{ $row['payment_status_label'] }}</td>
                            <td class="money-nowrap">{{ \App\Support\MoneyFormatter::format($row['amount_due']) }}</td>
                            <td class="money-nowrap">{{ \App\Support\MoneyFormatter::format($row['amount_paid']) }}</td>
                            <td class="money-nowrap">{{ \App\Support\MoneyFormatter::format($row['amount_remaining']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">Brak umów.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @include('pdf.packages._package_overrides_extra')

    @unless(in_array('attachments_list', $hide_sections ?? [], true))
        @include('pdf.packages._attachments')
    @endunless
    @include('pdf.packages._footer')
</div>
</body>
</html>
