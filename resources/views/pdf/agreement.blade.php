<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Umowa {{ $agreement->agreement_number ?: ('#' . $agreement->id) }}{{ $agreement->event?->code ? ' [' . $agreement->event->code . ']' : '' }}</title>
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
        }
        .body-plain {
            white-space: pre-wrap;
        }
        .body p { margin: 0 0 8px; }
        .body ul, .body ol { margin: 0 0 8px 18px; padding: 0; }
        .body h1, .body h2, .body h3, .body h4 { margin: 12px 0 8px; font-weight: bold; }
        .body table { width: 100%; border-collapse: collapse; margin: 8px 0; }
        .body table td, .body table th { border: 1px solid #ddd; padding: 4px 6px; }
        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            font-size: 11px;
        }
        .schedule-table th,
        .schedule-table td {
            border: 1px solid #ddd;
            padding: 4px 6px;
            text-align: left;
        }
    </style>
</head>
<body>
    <div class="a4">
    @php
        $groupPricing = $groupPricing ?? app(\App\Services\ContractGroupPricingService::class)->presentationFor($agreement);
        $orderingPartyService = app(\App\Services\ContractOrderingPartyService::class);
        $orderingParties = $orderingPartyService->partiesForTemplatePayload($agreement);
    @endphp
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
                <td>Zamawiający:</td>
                <td>
                    @if(count($orderingParties) > 0)
                        @foreach($orderingParties as $party)
                            <div>{{ $party['name'] }}</div>
                            @if(filled($party['email']) || filled($party['phone']) || ($party['address'] ?? '—') !== '—' || filled($party['nip'] ?? null))
                                <div style="font-size:10px;color:#555;">
                                    {{ collect([
                                        filled($party['nip'] ?? null) ? 'NIP: '.$party['nip'] : null,
                                        filled($party['email'] ?? null) ? $party['email'] : null,
                                        filled($party['phone'] ?? null) ? $party['phone'] : null,
                                        ($party['address'] ?? '—') !== '—' ? $party['address'] : null,
                                    ])->filter()->implode(' • ') }}
                                </div>
                            @endif
                        @endforeach
                    @else
                        {{ $orderingPartyService->formattedPartyNames($agreement) }}
                    @endif
                    @if(filled($agreement->ordering_party_notes))
                        <div style="margin-top:6px;font-size:10px;color:#555;">Uwagi: {{ $agreement->ordering_party_notes }}</div>
                    @endif
                </td>
            </tr>
            @if($agreement->isIndividual())
                <tr>
                    <td>Uczestnik:</td>
                    <td>{{ $agreement->participant_name ?: '—' }}</td>
                </tr>
            @endif
            @if($groupPricing['is_group'] ?? false)
                <tr>
                    <td>Liczba uczestników:</td>
                    <td>{{ $groupPricing['participant_count'] ?? '—' }}</td>
                </tr>
                <tr>
                    <td>Cena za osobę:</td>
                    <td>{{ \App\Support\MoneyFormatter::format($groupPricing['unit_price'] ?? 0) }}</td>
                </tr>
                <tr>
                    <td>Schemat płatności:</td>
                    <td>{{ $groupPricing['payment_scheme_label'] ?? '—' }}</td>
                </tr>
            @endif
            <tr>
                <td>Kwota:</td>
                <td>{{ \App\Support\MoneyFormatter::format($groupPricing['total_amount'] ?? $agreement->amount_due, strtoupper((string) ($agreement->currency ?: 'PLN'))) }}</td>
            </tr>
        </table>

        @if(($groupPricing['is_group'] ?? false) && !empty($groupPricing['payment_schedules']))
            <table class="schedule-table">
                <thead>
                    <tr>
                        <th>Transza</th>
                        <th>Kwota</th>
                        <th>Termin</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($groupPricing['payment_schedules'] as $schedule)
                        <tr>
                            <td>{{ $schedule['label'] ?: '—' }}</td>
                            <td>{{ \App\Support\MoneyFormatter::format($schedule['amount']) }}</td>
                            <td>{{ $schedule['due_date'] ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @php
        $agreementBodyHtml = \App\Support\AgreementHtml::looksLikeHtml((string) $agreementBody);
    @endphp
    <div class="body {{ $agreementBodyHtml ? '' : 'body-plain' }}">
        @if($agreementBodyHtml)
            {!! \App\Support\AgreementHtml::sanitize((string) $agreementBody) !!}
        @else
            {{ $agreementBody }}
        @endif
    </div>
    </div>
</body>
</html>
