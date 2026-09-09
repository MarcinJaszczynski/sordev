<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    @include('pdf.packages._styles')
    <style>
        .header {
            margin-bottom: 14px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        .title {
            font-size: 18px;
            font-weight: bold;
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
            word-break: break-word;
            font-size: 12px;
            line-height: 1.45;
        }
        .body p { margin: 0 0 8px; }
        .body ul, .body ol { margin: 0 0 8px 18px; padding: 0; }
        .body h1, .body h2, .body h3, .body h4 { margin: 12px 0 8px; font-weight: bold; }
        .body table { width: 100%; border-collapse: collapse; margin: 8px 0; }
        .body table td, .body table th { border: 1px solid #ddd; padding: 4px 6px; }
    </style>
</head>
<body>
    <div class="a4">
        <div class="header">
            <h1 class="title">{{ $title }}</h1>
            <table class="meta">
                <tr>
                    <td>Numer:</td>
                    <td>{{ $agreement->contract_number ?: '—' }}</td>
                </tr>
                <tr>
                    <td>Forma rozliczenia:</td>
                    <td>{{ $agreement->settlementFormLabel() }}</td>
                </tr>
                <tr>
                    <td>Impreza:</td>
                    <td>
                        {{ $event?->name ?? '—' }}
                        @if($event?->code)
                            <span style="color:#888;font-size:10px">[{{ $event->code }}]</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td>Pilot:</td>
                    <td>{{ $contractor?->name ?? '—' }}</td>
                </tr>
            </table>
        </div>

        <div class="body">
            {!! $bodyHtml !!}
        </div>
    </div>
</body>
</html>
