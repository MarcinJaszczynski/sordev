<x-mail::message>
# Wycieczka udostępniona

Witaj {{ $pilot->name }},

Biuro udostępniło Ci wycieczkę w panelu pilota **{{ config('app.name') }}**.

**Impreza:** {{ $event->code ? $event->code.' — ' : '' }}{{ $event->name }}

@if($event->start_date)
**Termin:** {{ $event->start_date->format('d.m.Y') }}@if($event->end_date) – {{ $event->end_date->format('d.m.Y') }}@endif
@endif

**Panel pilota:** [{{ $loginUrl }}]({{ $loginUrl }})

Zaloguj się i sprawdź zakładki Informacje, Rozliczenie oraz Dokumenty.

Pozdrawiamy,<br>
{{ config('app.name') }}
</x-mail::message>
