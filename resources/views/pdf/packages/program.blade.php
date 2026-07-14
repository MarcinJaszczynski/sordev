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

    <div class="section">
        <div class="section-title">Program imprezy</div>
        <div class="section-body">
            @forelse($programByDay as $day => $points)
                <div class="program-day">
                    <div class="program-day-title">DZIEŃ {{ $day }}</div>
                    <table class="program-table">
                        <colgroup>
                            @if($showTimes)
                                <col style="width: 14%;">
                                <col style="width: 32%;">
                                <col style="width: 54%;">
                            @else
                                <col style="width: 36%;">
                                <col style="width: 64%;">
                            @endif
                        </colgroup>
                        <thead>
                            <tr>
                                @if($showTimes)
                                    <th>Godziny</th>
                                @endif
                                <th>Punkt</th>
                                <th>Opis</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($points as $point)
                                <tr>
                                    @if($showTimes)
                                        <td>
                                            @if($point->hide_times ?? false)
                                                —
                                            @elseif($point->start_time || $point->end_time)
                                                {{ \Illuminate\Support\Str::of($point->start_time)->substr(0, 5) ?: '—' }}
                                                –
                                                {{ \Illuminate\Support\Str::of($point->end_time)->substr(0, 5) ?: '—' }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                    @endif
                                    <td>{{ $point->name ?: (optional($point->templatePoint)->name ?? '—') }}</td>
                                    <td>{{ $point->description ? strip_tags($point->description) : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @empty
                <p class="muted">Brak punktów programu.</p>
            @endforelse
        </div>
    </div>
</div>
</body>
</html>
