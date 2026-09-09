<x-mail::message>
# Umowa dla pilota

Witaj{{ $pilotName ? ', '.$pilotName : '' }},

w załączniku przesyłamy umowę ({{ $settlementFormLabel }}) dotyczącą imprezy:

**{{ $event->name }}**@if($event->code) ({{ $event->code }})@endif

@if($event->start_date)
Termin: {{ $event->start_date->format('d.m.Y') }}@if($event->end_date) – {{ $event->end_date->format('d.m.Y') }}@endif
@endif

Prosimy o zapoznanie się z dokumentem.

Dziękujemy,<br>
{{ config('company.name', config('app.name')) }}
</x-mail::message>
