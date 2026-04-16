<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Umowa {{ $agreement->agreement_number ?: ('#' . $agreement->id) }}{{ $agreement->event?->code ? ' [' . $agreement->event->code . ']' : '' }}</title>
    <style>
        @page { margin: 18mm 14mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #111;
            line-height: 1.45;
        }
        .header {
            margin-bottom: 14px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        .title {
            font-size: 18px;
            font-weight: 700;
            margin: 0 0 8px;
        }
        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }
        .meta td {
            padding: 3px 0;
            vertical-align: top;
            font-size: 11px;
        }
        .meta td:first-child {
            width: 180px;
            color: #555;
        }
        .body {
            white-space: pre-wrap;
            word-break: break-word;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="title">Umowa {{ $agreement->agreement_number ?: ('#' . $agreement->id) }}
            @if($agreement->event?->code)
                <span style="font-size:13px;font-weight:400;color:#888">[{{ $agreement->event->code }}]</span>
            @endif
        </h1>
        <table class="meta">
            <tr>
                <td>Typ umowy:</td>
                <td>{{ $agreement->agreement_type_label }}</td>
            </tr>
            <tr>
                <td>Impreza:</td>
                <td>
                    {{ $agreement->event_name ?: ($agreement->event?->name ?? '—') }}
                    @if($agreement->event?->code)
                        <span style="color:#888;font-size:10px">[{{ $agreement->event->code }}]</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td>Klient:</td>
                <td>{{ $agreement->customer_name ?: '—' }}</td>
            </tr>
            <tr>
                <td>Uczestnik:</td>
                <td>{{ $agreement->participant_name ?: '—' }}</td>
            </tr>
            <tr>
                <td>Kwota:</td>
                <td>{{ number_format((float) $agreement->amount_due, 2, ',', ' ') }} {{ strtoupper((string) ($agreement->currency ?: 'PLN')) }}</td>
            </tr>
        </table>
    </div>

    <div class="body">{{ $agreementBody }}</div>
</body>
</html>
