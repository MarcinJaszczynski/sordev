@extends('install.layout')

@section('content')
    @php $step = $step ?? 1; @endphp

    <div class="steps">
        @foreach ([1 => 'Wymagania', 2 => 'Baza danych', 3 => 'Aplikacja', 4 => 'Administrator'] as $n => $label)
            <span class="step {{ $step === $n ? 'active' : ($step > $n ? 'done' : '') }}">{{ $n }}. {{ $label }}</span>
        @endforeach
    </div>

    @if ($errors->any())
        <div class="errors">
            <ul style="margin:0;padding-left:1.2rem;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($step === 1)
        <h1>Instalacja systemu SOR</h1>
        <p class="sub">Kreator instalacji — jak w WordPressie. Sprawdź wymagania serwera przed kontynuacją.</p>

        <ul class="req-list">
            @foreach ($requirements as $check)
                <li>
                    <span>{{ $check['message'] }}</span>
                    <span class="{{ $check['ok'] ? 'ok' : 'fail' }}">{{ $check['ok'] ? 'OK' : 'Błąd' }}</span>
                </li>
            @endforeach
        </ul>

        <p class="hint">Na serwerze docelowym uruchom: <code>composer install --no-dev</code> oraz <code>npm ci && npm run build</code> (jeśli jeszcze nie zrobione).</p>

        <div class="actions">
            @if ($requirementsMet)
                <a href="{{ route('install.database') }}" class="btn">Dalej →</a>
            @else
                <span class="hint" style="margin:0;">Popraw wymagania i odśwież stronę.</span>
            @endif
        </div>
    @endif

    @if ($step === 2)
        <h1>Baza danych</h1>
        <p class="sub">Podaj dane połączenia MySQL. Konto musi mieć prawo tworzenia tabel.</p>

        <form method="post" action="{{ route('install.database.store') }}">
            @csrf
            <label for="db_host">Host</label>
            <input type="text" name="db_host" id="db_host" value="{{ old('db_host', $db['host'] ?? '127.0.0.1') }}" required>

            <label for="db_port">Port</label>
            <input type="text" name="db_port" id="db_port" value="{{ old('db_port', $db['port'] ?? '3306') }}" required>

            <label for="db_database">Nazwa bazy</label>
            <input type="text" name="db_database" id="db_database" value="{{ old('db_database', $db['database'] ?? '') }}" required>

            <label for="db_username">Użytkownik</label>
            <input type="text" name="db_username" id="db_username" value="{{ old('db_username', $db['username'] ?? '') }}" required>

            <label for="db_password">Hasło</label>
            <input type="password" name="db_password" id="db_password" value="{{ old('db_password') }}">

            <div class="actions">
                <a href="{{ route('install.index') }}" class="btn btn-secondary">← Wstecz</a>
                <button type="submit" class="btn">Dalej →</button>
            </div>
        </form>
    @endif

    @if ($step === 3)
        <h1>Ustawienia aplikacji</h1>
        <p class="sub">Nazwa witryny i adres URL (bez końcowego slasha).</p>

        <form method="post" action="{{ route('install.application.store') }}">
            @csrf
            <label for="app_name">Nazwa aplikacji</label>
            <input type="text" name="app_name" id="app_name" value="{{ old('app_name', $app['name'] ?? 'SOR') }}" required>

            <label for="app_url">Adres URL</label>
            <input type="url" name="app_url" id="app_url" value="{{ old('app_url', $app['url'] ?? url('/')) }}" required>

            <div class="actions">
                <a href="{{ route('install.database') }}" class="btn btn-secondary">← Wstecz</a>
                <button type="submit" class="btn">Dalej →</button>
            </div>
        </form>
    @endif

    @if ($step === 4)
        <h1>Konto administratora</h1>
        <p class="sub">To konto otrzyma role super_admin i admin (dostęp do panelu Filament).</p>

        <form method="post" action="{{ route('install.admin.store') }}">
            @csrf
            <label for="admin_name">Imię i nazwisko</label>
            <input type="text" name="admin_name" id="admin_name" value="{{ old('admin_name') }}" required>

            <label for="admin_email">E-mail</label>
            <input type="email" name="admin_email" id="admin_email" value="{{ old('admin_email') }}" required>

            <label for="admin_password">Hasło</label>
            <input type="password" name="admin_password" id="admin_password" required>

            <label for="admin_password_confirmation">Powtórz hasło</label>
            <input type="password" name="admin_password_confirmation" id="admin_password_confirmation" required>

            <div class="actions">
                <a href="{{ route('install.application') }}" class="btn btn-secondary">← Wstecz</a>
                <button type="submit" class="btn">Zainstaluj aplikację</button>
            </div>
        </form>
    @endif
@endsection
