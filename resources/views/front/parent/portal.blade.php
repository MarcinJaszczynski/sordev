<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Portal rodzica — {{ $event?->name ?? 'Wycieczka' }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/reservation.css') }}">
</head>
<body class="bg-light">
<div class="container py-4 py-md-5 reservation-page">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('info'))
                <div class="alert alert-info">{{ session('info') }}</div>
            @endif
            @if(isset($errors) && $errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0 ps-3">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4 p-lg-5">
                    <p class="text-muted text-uppercase small mb-1">Portal rodzica / opiekuna</p>
                    <h1 class="h3 mb-2">{{ $event?->name ?? 'Wycieczka' }}</h1>
                    <p class="mb-0">
                        Uczestnik: <strong>{{ $participant->fullName() ?: '—' }}</strong>
                        @if ($event?->start_date)
                            · Termin: {{ $event->start_date->format('d.m.Y') }}
                            @if ($event->end_date)
                                – {{ $event->end_date->format('d.m.Y') }}
                            @endif
                        @endif
                    </p>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4 p-lg-5">
                    <h2 class="h5">Status wpłat</h2>
                    <dl class="row mb-3">
                        <dt class="col-sm-4">Należność</dt>
                        <dd class="col-sm-8">{{ $dueLabel }}</dd>
                        <dt class="col-sm-4">Wpłacono</dt>
                        <dd class="col-sm-8">{{ $paidLabel }}</dd>
                        <dt class="col-sm-4">Pozostało</dt>
                        <dd class="col-sm-8"><strong>{{ $remainingLabel }}</strong></dd>
                    </dl>

                    @if ($canPay)
                        <p class="text-muted small">Następna rata: {{ $nextInstallmentLabel }}</p>
                        <form method="POST" action="{{ route('parent.portal.pay', ['token' => $token]) }}">
                            @csrf
                            <button type="submit" class="btn btn-primary">{{ \App\Support\PaymentCta::label() }}</button>
                        </form>
                    @elseif ($remaining <= 0.01)
                        <div class="alert alert-success mb-0">Wpłaty kompletne — dziękujemy.</div>
                    @else
                        <div class="alert alert-warning mb-0">Brak aktywnej raty online. Skontaktuj się z biurem.</div>
                    @endif
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-lg-5">
                    <h2 class="h5">Zgody</h2>
                    <form method="POST" action="{{ route('parent.portal.consents', ['token' => $token]) }}">
                        @csrf
                        @foreach ($labels as $key => $label)
                            <div class="form-check mb-2">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    name="consent_{{ $key }}"
                                    id="consent_{{ $key }}"
                                    value="1"
                                    @checked($checklist[$key] ?? false)
                                    @if (in_array($key, $requiredKeys, true)) required @endif
                                >
                                <label class="form-check-label" for="consent_{{ $key }}">
                                    {{ $label }}
                                    @if (in_array($key, $requiredKeys, true))
                                        <span class="text-danger">*</span>
                                    @endif
                                </label>
                            </div>
                        @endforeach
                        <button type="submit" class="btn btn-outline-primary mt-3">Zapisz zgody</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
