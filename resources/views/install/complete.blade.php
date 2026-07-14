@extends('install.layout')

@section('content')
    <h1>Instalacja zakończona</h1>
    <p class="sub">Aplikacja jest gotowa do użycia.</p>

    <ul class="req-list">
        <li><span>Plik .env</span><span class="ok">Utworzony</span></li>
        <li><span>Migracje bazy</span><span class="ok">Wykonane</span></li>
        <li><span>Konto administratora</span><span class="ok">Utworzone</span></li>
    </ul>

    <p class="hint">
        Przy migracji z kopii zapasowej: zaloguj się do panelu → Narzędzia → Kopie zapasowe → Przywróć archiwum ZIP.
        Po przywróceniu kodu na serwerze uruchom: <code>composer install</code> oraz <code>npm run build</code>.
    </p>

    <div class="actions">
        <a href="{{ url('/admin') }}" class="btn">Przejdź do panelu administracyjnego</a>
        <a href="{{ url('/') }}" class="btn btn-secondary">Strona główna</a>
    </div>
@endsection
