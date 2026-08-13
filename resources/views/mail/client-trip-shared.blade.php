<x-mail::message>
# Wycieczka w portalu klienta

Witaj {{ $user->name }},

Wycieczka **{{ $event->name }}** jest dostępna w Twoim portalu klienta.

@if($event->start_date)
**Termin:** {{ $event->start_date->format('d.m.Y') }}@if($event->end_date) – {{ $event->end_date->format('d.m.Y') }}@endif
@endif

Masz już konto w portalu — **zaloguj się dotychczasowym hasłem**. Jeśli go nie pamiętasz, użyj „Nie pamiętam hasła” na stronie logowania.

**Zaloguj się:** [{{ $loginUrl }}]({{ $loginUrl }})

W portalu znajdziesz program, umowę, harmonogram płatności (PLN + ewentualna waluta) i dokumenty.

Pozdrawiamy,<br>
{{ config('app.name') }}
</x-mail::message>
