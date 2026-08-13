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
                    <td class="lbl">Klient</td>
                    <td class="val">{{ $event->client_name ?: '—' }} @if($event->client_phone) — {{ $event->client_phone }} @endif</td>
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
            <div class="section-title">Uwagi dla hotelu</div>
            <div class="section-body">
                <div class="notes-field">{!! nl2br(e($hotelNotes)) !!}</div>
            </div>
        </div>
    @endif

    @if(isset($hotelProgramPoints) && $hotelProgramPoints->isNotEmpty())
        <div class="section">
            <div class="section-title">Hotele / noclegi w programie</div>
            <div class="section-body">
                <table class="program-table">
                    <thead>
                        <tr>
                            <th style="width:10%;">Dzień</th>
                            <th style="width:40%;">Punkt</th>
                            <th style="width:50%;">Kontrahent</th>
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
            </div>
        </div>
    @endif

    @unless(in_array('hotel_plan', $hide_sections ?? [], true))
        <div class="section">
            <div class="section-title">Plan pokoi{{ !empty($usesEventHotelPlan) ? ' (impreza)' : ' (wg szablonu)' }}</div>
            <div class="section-body">
                @forelse($hotelPlan as $day)
                <div class="program-day-title" style="margin-top:6px;">
                    Noc {{ $day['day'] }}
                    @if(!empty($day['hotel_name']))
                        — {{ $day['hotel_name'] }}
                    @endif
                    @if(!empty($day['day_total_pln']))
                        <span class="muted">({{ \App\Support\MoneyFormatter::format($day['day_total_pln'] ?? 0) }})</span>
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
                                <th>Cena/szt.</th>
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
                                        <td class="money-nowrap">{{ \App\Support\MoneyFormatter::format($room['unit_price'] ?? 0) }}</td>
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
    @endunless

    @include('pdf.packages._package_overrides_extra')

    @unless(in_array('attachments_list', $hide_sections ?? [], true))
        @include('pdf.packages._attachments')
    @endunless
    @include('pdf.packages._footer')
</div>
</body>
</html>
