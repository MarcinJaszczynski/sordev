<x-mail::message>
# Przypomnienie o dopłacie

Witaj {{ $recipientName }},

Przypominamy o pozostałej płatności za wycieczkę **{{ $event->name ?: $event->code }}**.

| | Kwota |
| --- | ---: |
| Należne | {{ \App\Support\MoneyFormatter::format($amounts['due_pln'] ?? 0) }} |
| Wpłacone | {{ \App\Support\MoneyFormatter::format($amounts['paid_pln'] ?? 0) }} |
| **Pozostało** | **{{ \App\Support\MoneyFormatter::format($amounts['remaining_pln'] ?? 0) }}** |

@if (filled($portalUrl))
W szczegółach płatności możesz też zajrzeć do [portalu klienta]({{ $portalUrl }}).
@endif

W razie pytań prosimy o kontakt z biurem podróży.

Pozdrawiamy,<br>
{{ config('app.name') }}
</x-mail::message>
