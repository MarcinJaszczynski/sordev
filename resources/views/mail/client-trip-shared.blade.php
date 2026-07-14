<x-mail::message>
# Wycieczka w portalu klienta

Witaj {{ $user->name }},

Wycieczka **{{ $event->name }}** jest dostępna w Twoim portalu klienta.

@if($event->start_date)
**Termin:** {{ $event->start_date->format('d.m.Y') }}@if($event->end_date) – {{ $event->end_date->format('d.m.Y') }}@endif
@endif

**Zaloguj się:** [{{ $loginUrl }}]({{ $loginUrl }})

W portalu znajdziesz program, umowę, harmonogram płatności i — w zależności od roli — wpłaty grupy.

Pozdrawiamy,<br>
{{ config('app.name') }}
</x-mail::message>
