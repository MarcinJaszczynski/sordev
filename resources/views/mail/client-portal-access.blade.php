<x-mail::message>
# Dostęp do portalu klienta

Witaj {{ $user->name }},

Utworzono dla Ciebie konto w portalu klienta systemu **{{ config('app.name') }}**.

**Adres logowania:** [{{ $loginUrl }}]({{ $loginUrl }})

**Login (e-mail):** {{ $user->email }}

**Hasło:** {{ $plainPassword }}

Po zalogowaniu zobaczysz wycieczki udostępnione po podpisaniu umowy lub zaproszeniu z biura.

Pozdrawiamy,<br>
{{ config('app.name') }}
</x-mail::message>
