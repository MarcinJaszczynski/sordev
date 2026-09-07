<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $audienceLabel }} - {{ $event->name }}{{ $event->code ? ' [' . $event->code . ']' : '' }}</title>
    @include('pdf.packages._styles')
    <style>
        .header {
            border: 2px solid #1e3a8a;
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 14px;
            background: #f8fafc;
        }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-left { width: 70%; vertical-align: top; }
        .header-right { width: 30%; text-align: right; vertical-align: top; }
        .logo { max-height: 50px; max-width: 170px; }
        .title { font-size: 20px; font-weight: bold; margin: 0 0 4px 0; color: #1e3a8a; }
        .subtitle { font-size: 13px; margin: 0; color: #334155; }
        .meta { font-size: 11px; color: #475569; margin-top: 6px; }

        .grid { width: 100%; border-collapse: separate; border-spacing: 8px; margin-bottom: 10px; }
        .card {
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 10px 12px;
            vertical-align: top;
            background: #ffffff;
        }
        .card h3 {
            margin: 0 0 8px 0;
            font-size: 13px;
            color: #1e3a8a;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 5px;
        }
        .value { font-weight: bold; color: #0f172a; }
        .muted { color: #64748b; }

        .list { margin: 0; padding-left: 16px; }
        .list li { margin-bottom: 4px; }

        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #1e3a8a;
            margin: 12px 0 8px 0;
            border-left: 4px solid #1e3a8a;
            padding-left: 8px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .table th, .table td {
            border: 1px solid #cbd5e1;
            padding: 6px 7px;
            text-align: left;
            vertical-align: top;
        }
        .table th {
            background: #eff6ff;
            color: #1e3a8a;
            font-size: 11px;
        }

        .badge {
            display: inline-block;
            border-radius: 999px;
            padding: 2px 7px;
            font-size: 10px;
            font-weight: bold;
        }
        .b-green { background: #dcfce7; color: #166534; }
        .b-blue { background: #dbeafe; color: #1d4ed8; }
        .b-orange { background: #ffedd5; color: #9a3412; }
        .b-red { background: #fee2e2; color: #991b1b; }

        .footer {
            margin-top: 10px;
            border-top: 1px solid #cbd5e1;
            padding-top: 8px;
            font-size: 10px;
            color: #64748b;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="a4">
    <div class="header">
        <table class="header-table">
            <tr>
                <td class="header-left">
                    <p class="title">{{ $audienceLabel }}</p>
                    <p class="subtitle">{{ $event->name }}
                        @if($event->code)
                            <span style="color:#888;font-size:12px">[{{ $event->code }}]</span>
                        @endif
                    </p>
                    <p class="meta">
                        Termin: <strong>{{ optional($event->start_date)->format('d.m.Y') ?: '—' }}</strong>
                        @if($event->end_date)
                            - <strong>{{ $event->end_date->format('d.m.Y') }}</strong>
                        @endif
                        &nbsp;|&nbsp; Wygenerowano: {{ $generatedAt->format('d.m.Y H:i') }}
                        @if($event->code)
                            &nbsp;|&nbsp; Kod imprezy: <strong>{{ $event->code }}</strong>
                        @endif
                    </p>
                </td>
                <td class="header-right">
                    @if($logoDataUri)
                        <img src="{{ $logoDataUri }}" class="logo" alt="Logo">
                    @endif
                    <div style="font-size:10px; margin-top:6px; text-align:right;">
                        <strong>{{ $company['name'] ?? 'Biuro podróży' }}</strong><br>
                        {{ $company['address_line_1'] ?? '' }}<br>
                        {{ $company['address_line_2'] ?? '' }}<br>
                        tel. {{ $company['phone'] ?? '' }}<br>
                        {{ $company['email'] ?? '' }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <table class="grid">
        <tr>
            <td class="card" style="width:50%;">
                <h3>Podsumowanie imprezy</h3>
                <div>ID imprezy: <span class="value">#{{ $event->id }}</span></div>
                <div>Status: <span class="value">{{ $event->status ?? '—' }}</span></div>
                <div>Klient: <span class="value">{{ $event->client_name ?: '—' }}</span></div>
                <div>Uczestnicy: <span class="value">{{ $participantCount }}</span> + {{ \App\Support\EventParticipantGroupLabels::GRATIS_GENITIVE }}: <span class="value">{{ $gratisCount }}</span></div>
                <div>Obsługa: <span class="value">{{ $staffCount }}</span> | Kierowcy: <span class="value">{{ $driverCount }}</span></div>
            </td>
            <td class="card" style="width:50%;">
                <h3>Fokus dokumentu</h3>
                <ul class="list">
                    @foreach($documentFocus as $focus)
                        <li>{{ $focus }}</li>
                    @endforeach
                </ul>
            </td>
        </tr>
    </table>

    @if(in_array($audience, ['pilot', 'driver', 'folder'], true))
        @php
            $programDayRoutes = $programDayRoutes ?? $event->programDayRoutes();
        @endphp
        <div class="section-title">Program imprezy</div>
        @foreach($programByDay as $day => $points)
            @php
                $dayRoute = $programDayRoutes[(string) $day] ?? $programDayRoutes[$day] ?? null;
            @endphp
            <table class="table">
                <thead>
                    <tr>
                        <th colspan="5">
                            {{ $event->programDayLabel((int) $day) }}
                            @if(filled($dayRoute))
                                — Trasa: {{ $dayRoute }}
                            @endif
                        </th>
                    </tr>
                    <tr>
                        <th style="width:12%;">Godziny</th>
                        <th style="width:24%;">Punkt</th>
                        <th style="width:22%;">Opis</th>
                        <th style="width:21%;">Notatki pilota</th>
                        <th style="width:21%;">Notatki biura</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($points as $point)
                        <tr>
                            <td>
                                @php
                                    $displayStart = $point->displayStartTime();
                                    $displayEnd = $point->displayEndTime();
                                @endphp
                                @if($displayStart || $displayEnd)
                                    {{ $displayStart ?: '—' }} - {{ $displayEnd ?: '—' }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $point->name ?: (optional($point->templatePoint)->name ?? 'Punkt programu') }}</td>
                            <td>{{ $point->description ?: '—' }}</td>
                            <td>{{ $point->pilot_notes ?: '—' }}</td>
                            <td>{{ $point->office_notes ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">Brak punktów programu.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @endforeach
    @endif

    @if(in_array($audience, ['hotel', 'folder'], true))
        <div class="section-title">Plan hotelowy</div>
        @if(!empty($hotelNotes))
            <table class="table" style="margin-bottom: 12px;">
                <tbody>
                    <tr>
                        <td><strong>Uwagi dla hotelu:</strong> {{ $hotelNotes }}</td>
                    </tr>
                </tbody>
            </table>
        @endif
        @if(isset($hotelProgramPoints) && $hotelProgramPoints->isNotEmpty())
            <div style="font-weight:bold;margin-bottom:6px;margin-top:8px;">Hotele / noclegi w programie imprezy</div>
            <table class="table" style="margin-bottom: 14px;">
                <thead>
                    <tr>
                        <th style="width:10%;">Dzień</th>
                        <th style="width:40%;">Punkt programu</th>
                        <th style="width:50%;">Kontrahent / Hotel</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($hotelProgramPoints as $hp)
                        <tr>
                            <td>{{ (int) ($hp->day ?? 1) }}</td>
                            <td>{{ $hp->name ?? $hp->templatePoint?->name ?? '—' }}</td>
                            <td>{{ $hp->contractor?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        @forelse($hotelPlan as $day)
            <table class="table">
                <thead>
                    <tr>
                        <th colspan="4">Dzień {{ $day['day'] }}</th>
                    </tr>
                    <tr>
                        <th style="width:25%;">Uczestnicy</th>
                        <th style="width:25%;">{{ \App\Support\EventParticipantGroupLabels::GRATIS }}</th>
                        <th style="width:25%;">Obsługa</th>
                        <th style="width:25%;">Kierowca</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            @forelse($day['qty'] as $room)
                                - {{ $room['name'] }}@if($room['people_count']) ({{ $room['people_count'] }} os.) @endif<br>
                            @empty
                                —
                            @endforelse
                        </td>
                        <td>
                            @forelse($day['gratis'] as $room)
                                - {{ $room['name'] }}@if($room['people_count']) ({{ $room['people_count'] }} os.) @endif<br>
                            @empty
                                —
                            @endforelse
                        </td>
                        <td>
                            @forelse($day['staff'] as $room)
                                - {{ $room['name'] }}@if($room['people_count']) ({{ $room['people_count'] }} os.) @endif<br>
                            @empty
                                —
                            @endforelse
                        </td>
                        <td>
                            @forelse($day['driver'] as $room)
                                - {{ $room['name'] }}@if($room['people_count']) ({{ $room['people_count'] }} os.) @endif<br>
                            @empty
                                —
                            @endforelse
                        </td>
                    </tr>
                    @if(!empty($day['notes']))
                        <tr>
                            <td colspan="4"><strong>Uwagi:</strong> {{ $day['notes'] }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        @empty
            <table class="table"><tbody><tr><td class="muted">Brak danych noclegowych dla tej imprezy.</td></tr></tbody></table>
        @endforelse
    @endif

    @if(in_array($audience, ['pilot', 'driver', 'folder'], true))
        <div class="section-title">Transport i kontakty operacyjne</div>
        <table class="grid">
            <tr>
                <td class="card" style="width:50%;">
                    <h3>Transport</h3>
                    <div>Autokar: <span class="value">{{ $event->bus?->name ?: '—' }}</span></div>
                    <div>Miejsce startu: <span class="value">{{ $event->startPlace?->name ?: '—' }}</span></div>
                    <div>Miejsce podstawienia (szczegóły): <span class="value">{{ $event->pickup_place_details ?: '—' }}</span></div>
                    <div>Godzina podstawienia: <span class="value">{{ $event->departure_time ?: '—' }}</span></div>
                    <div>Firma transportowa: <span class="value">{{ $event->transport_company_name ?: '—' }}</span></div>
                    <div>Kierowca: <span class="value">{{ $event->driver_name ?: '—' }}</span></div>
                    <div>Telefon kierowcy: <span class="value">{{ $event->driver_phone ?: '—' }}</span></div>
                    <div>Nr rejestracyjny: <span class="value">{{ $event->vehicle_registration ?: '—' }}</span></div>
                    <div>KM transferu: <span class="value">{{ (int) ($event->transfer_km ?? 0) }}</span></div>
                    <div>KM programu: <span class="value">{{ (int) ($event->program_km ?? 0) }}</span></div>
                    @if(!empty($programDayRoutes))
                        <div style="margin-top:8px;">
                            <strong>Trasy dzienne:</strong>
                            @foreach($programDayRoutes as $routeDay => $routeLabel)
                                <div>Dzień {{ $routeDay }}: <span class="value">{{ $routeLabel }}</span></div>
                            @endforeach
                        </div>
                    @endif
                </td>
                <td class="card" style="width:50%;">
                    <h3>Kontakty</h3>
                    <div>Biuro: <span class="value">{{ $company['phone'] ?? '—' }}</span> / {{ $company['email'] ?? '—' }}</div>
                    <div>Pilot/koordynator: <span class="value">{{ $event->assignedUser?->name ?: '—' }}</span></div>
                    @if(filled($event->assignedUser?->phone))
                        <div>Tel. pilota: <span class="value">{{ $event->assignedUser->phone }}</span></div>
                    @endif
                    @if($event->assignedUser?->birth_date)
                        <div>Data urodzenia pilota: <span class="value">{{ $event->assignedUser->birth_date->format('d.m.Y') }}</span></div>
                    @endif
                    @if(filled($event->assignedUser?->pesel))
                        <div>PESEL pilota: <span class="value">{{ $event->assignedUser->pesel }}</span></div>
                    @endif
                    <div>Klient: <span class="value">{{ $event->client_name ?: '—' }}</span></div>
                    <div>Telefon klienta: <span class="value">{{ $event->client_phone ?: '—' }}</span></div>
                </td>
            </tr>
        </table>
    @endif

    @if(in_array($audience, ['folder'], true))
        <div class="section-title">Zawarte umowy indywidualne i płatności uczestników</div>
        <table class="grid">
            <tr>
                <td class="card" style="width:20%;"><h3>Łącznie zawartych</h3><span class="value">{{ $agreementsSummary['total'] }}</span></td>
                <td class="card" style="width:20%;"><h3>Opłacone</h3><span class="value">{{ $agreementsSummary['payment_progress_label'] }}</span></td>
                <td class="card" style="width:20%;"><h3>Nieopłacone</h3><span class="value">{{ $agreementsSummary['unpaid'] }}</span></td>
                <td class="card" style="width:20%;"><h3>Wpłacono</h3><span class="value money-nowrap">{{ \App\Support\MoneyFormatter::format($agreementsSummary['amount_paid']) }}</span></td>
                <td class="card" style="width:20%;"><h3>Pozostało</h3><span class="value money-nowrap">{{ \App\Support\MoneyFormatter::format($agreementsSummary['amount_remaining']) }}</span></td>
            </tr>
        </table>

        <table class="table">
            <thead>
                <tr>
                    <th style="width:14%;">Nr umowy</th>
                    <th style="width:18%;">Uczestnik</th>
                    <th style="width:18%;">Płatnik</th>
                    <th style="width:12%;">Płatność</th>
                    <th style="width:12%;">Status</th>
                    <th style="width:13%;">Należność</th>
                    <th style="width:13%;">Wpłata</th>
                    <th style="width:13%;">Do zapłaty</th>
                </tr>
            </thead>
            <tbody>
                @forelse(($individualAgreementRows ?? []) as $row)
                    <tr>
                        <td>{{ $row['agreement_number'] }}</td>
                        <td>{{ $row['participant_name'] }}</td>
                        <td>{{ $row['payer_name'] }}</td>
                        <td>
                            <span class="badge {{ $row['payment_status'] === 'paid' ? 'b-green' : 'b-orange' }}">{{ $row['payment_status_label'] }}</span>
                        </td>
                        <td>
                            <span class="badge {{ in_array($row['status'], ['signed', 'completed']) ? 'b-green' : 'b-blue' }}">{{ $row['status_label'] }}</span>
                        </td>
                        <td class="money-nowrap">{{ \App\Support\MoneyFormatter::format($row['amount_due'], $row['currency']) }}</td>
                        <td class="money-nowrap">{{ \App\Support\MoneyFormatter::format($row['amount_paid'], $row['currency']) }}</td>
                        <td class="money-nowrap">{{ \App\Support\MoneyFormatter::format($row['amount_remaining'], $row['currency']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">Brak zawartych umów indywidualnych dla tej imprezy.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endif

    @if(collect($selectedSettlementDocuments ?? [])->isNotEmpty())
        <div class="section-title">Załączniki wybrane do tego pakietu</div>
        <table class="table">
            <thead>
                <tr>
                    <th style="width:20%;">Dokument</th>
                    <th style="width:20%;">Typ</th>
                    <th style="width:20%;">Kontrahent</th>
                    <th style="width:40%;">Pliki</th>
                </tr>
            </thead>
            <tbody>
                @foreach($selectedSettlementDocuments as $document)
                    <tr>
                        <td>{{ $document['label'] }}</td>
                        <td>{{ $document['type'] }}</td>
                        <td>{{ $document['vendor_name'] ?: '—' }}</td>
                        <td>
                            @foreach($document['files'] as $file)
                                - {{ $file['base_name'] }}<br>
                            @endforeach
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="muted" style="margin-top:-2px; margin-bottom:10px;">
            Jeżeli do dokumentu przypisano pliki na dysku, pobranie zwróci pakiet ZIP zawierający ten PDF oraz folder "zalaczniki".
        </p>
    @endif

    <div class="footer">
        {{ $company['name'] ?? config('app.name') }} • {{ $company['address_line_1'] ?? '' }} {{ $company['address_line_2'] ?? '' }} • {{ $company['phone'] ?? '' }} • {{ $company['email'] ?? '' }}
    </div>
    </div>
</body>
</html>
