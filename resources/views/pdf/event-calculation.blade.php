<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <title>Kalkulacja — {{ $event->name }}</title>
    @include('pdf.packages._styles')
    <style>
        h1 { font-size: 16px; margin-bottom: 4px; }
        h2 { font-size: 13px; margin-top: 18px; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 11px; }
        table.data th, table.data td { border: 1px solid #d1d5db; padding: 4px 6px; text-align: left; }
        table.data th { background: #f3f4f6; }
        .meta { color: #555; margin-bottom: 12px; }
        .totals { margin-top: 12px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="a4">
    <h1>Kalkulacja imprezy</h1>
    <div class="meta">
        <div><strong>{{ $event->name }}</strong> @if($event->code)({{ $event->code }})@endif</div>
        <div>Klient: {{ $event->client_name ?? '—' }}</div>
        <div>Termin: {{ $event->start_date?->format('d.m.Y') ?? '—' }} — {{ $event->end_date?->format('d.m.Y') ?? '—' }}</div>
        <div>Uczestnicy: {{ (int) ($event->participant_count ?? 0) }}
            @php $gratis = $event->resolveGratisCountForParticipantCount((int) ($event->participant_count ?? 0)); @endphp
            @if($gratis > 0) + {{ $gratis }} opiek. @endif
        </div>
        <div>Wygenerowano: {{ now()->format('d.m.Y H:i') }}</div>
    </div>

    @php
        $calc = $state['calculations'] ?? [];
    @endphp

    <h2>Podsumowanie</h2>
    <table class="data">
        <tr><th>Pozycja</th><th>Kwota PLN</th></tr>
        <tr><td>Koszt programu</td><td class="money-nowrap">{{ \App\Support\MoneyFormatter::format($calc['total_program_cost'] ?? 0) }}</td></tr>
        <tr><td>Koszt transportu</td><td class="money-nowrap">{{ \App\Support\MoneyFormatter::format($state['transport_cost'] ?? 0) }}</td></tr>
        <tr><td><strong>Razem</strong></td><td class="money-nowrap"><strong>{{ \App\Support\MoneyFormatter::format($calc['total_cost'] ?? 0) }}</strong></td></tr>
        <tr><td>Plan kalkulacji (szczegółowa)</td><td class="money-nowrap">{{ \App\Support\MoneyFormatter::format($plannedTotal) }}</td></tr>
        <tr><td>Plan rozliczenia</td><td class="money-nowrap">{{ \App\Support\MoneyFormatter::format($settlementPlanned) }}</td></tr>
    </table>

    @if(!empty($state['price_rows']) && count($state['price_rows']))
        <h2>Pozycje cenowe</h2>
        <table class="data">
            <tr>
                <th>Ucz.</th><th>Cena/os</th><th>Transport</th><th>Z VAT</th>
            </tr>
            @foreach($state['price_rows'] as $row)
                <tr>
                    <td>{{ $row->eventTemplateQty?->qty ?? '—' }}</td>
                    <td class="money-nowrap">{{ \App\Support\MoneyFormatter::format($row->price_per_person ?? 0) }}</td>
                    <td class="money-nowrap">{{ $row->transport_cost !== null ? \App\Support\MoneyFormatter::format($row->transport_cost) : '—' }}</td>
                    <td class="money-nowrap">{{ $row->price_with_tax !== null ? \App\Support\MoneyFormatter::format($row->price_with_tax) : '—' }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @php
        $qty = max(1, (int) ($event->participant_count ?? 1));
        $plnBlock = $state['detailed_calculations'][$qty]['PLN'] ?? null;
    @endphp

    @if(is_array($plnBlock) && !empty($plnBlock['points']))
        <h2>Szczegóły PLN (grupa {{ $qty }})</h2>
        <table>
            <tr><th>Pozycja</th><th>Koszt PLN</th></tr>
            @foreach($plnBlock['points'] as $point)
                <tr>
                    <td>{{ $point['name'] ?? '—' }}</td>
                    <td class="money-nowrap">{{ \App\Support\MoneyFormatter::format($point['cost'] ?? 0) }}</td>
                </tr>
            @endforeach
            <tr>
                <td><strong>Suma</strong></td>
                <td class="money-nowrap"><strong>{{ \App\Support\MoneyFormatter::format($plnBlock['total'] ?? 0) }}</strong></td>
            </tr>
        </table>
    @endif
    </div>
</body>
</html>
