<x-mail::message>
# Dostęp do panelu pilota

Witaj {{ $user->name }},

Utworzono dla Ciebie konto w panelu pilota systemu **{{ config('app.name') }}**.

**Adres logowania:** [{{ $loginUrl }}]({{ $loginUrl }})

**Login (e-mail):** {{ $user->email }}

**Hasło:** {{ $plainPassword }}

Po zalogowaniu zobaczysz wycieczki udostępnione przez biuro. Jeśli lista jest pusta, skontaktuj się z koordynatorem — wycieczka musi zostać jawnie udostępniona.

Pozdrawiamy,<br>
{{ config('app.name') }}
</x-mail::message>
