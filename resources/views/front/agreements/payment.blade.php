@php
    $selectedMethod = old('payment_method', data_get($flow ?? [], 'payment_method', 'demo_transfer'));
    $checkout = $checkout ?? null;
    $next = is_array($checkout) ? ($checkout['next_pln'] ?? null) : null;
    $chargeAmount = is_array($next) ? (float) ($next['remaining'] ?? 0) : (float) $agreement->amount_due;
    $totalDue = is_array($checkout) ? (float) ($checkout['total_due_pln'] ?? $agreement->amount_due) : (float) $agreement->amount_due;
    $totalPaid = is_array($checkout) ? (float) ($checkout['total_paid_pln'] ?? $agreement->amount_paid) : (float) ($agreement->amount_paid ?? 0);
    $totalRemaining = is_array($checkout) ? (float) ($checkout['total_remaining_pln'] ?? max(0, $totalDue - $totalPaid)) : max(0, $totalDue - $totalPaid);
    $scheduleLines = is_array($checkout) ? ($checkout['schedule_lines'] ?? []) : [];
    $fxRows = is_array($checkout) ? ($checkout['fx_rows'] ?? []) : [];
    $aligned = (bool) ($checkout['aligned'] ?? false);
@endphp

<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Umowa online - Metody płatności</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/reservation.css') }}">
</head>
<body class="bg-light">
<div class="container py-4 py-md-5 reservation-page">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-9">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
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

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-lg-5">
                    @include('front.agreements._stepper', ['agreement' => $agreement, 'active' => 4])

                    <h2>Płatność</h2>

                    <div class="alert alert-info mb-4">
                        @if(!empty($checkout['has_installments']))
                            Płacisz <strong>bieżącą ratę</strong> zgodnie z harmonogramem — nie całą kwotę umowy naraz.
                        @else
                            To jest środowisko <strong>demo płatności</strong>.
                        @endif
                    </div>

                    @if($aligned)
                        <div class="alert alert-warning mb-4">
                            Kwota umowy zmieniła się względem wcześniejszych rat — <strong>przeliczono nieopłacone raty PLN</strong> do aktualnej kwoty umowy.
                        </div>
                    @endif

                    <div class="white_box mb-4">
                        <div><strong>Numer umowy:</strong> {{ $agreement->agreement_number ?: ('#' . $agreement->id) }}</div>
                        <div><strong>Płatnik:</strong> {{ $agreement->signer_name ?: $agreement->customer_name ?: '—' }}</div>
                        @if(filled($agreement->participant_name) || filled($agreement->participantPayment?->participant_name))
                            <div><strong>Uczestnik:</strong> {{ $agreement->participant_name ?: $agreement->participantPayment?->participant_name }}</div>
                        @endif
                        <div><strong>Impreza:</strong> {{ $agreement->event_name ?: ($agreement->event?->name ?? '—') }}</div>

                        <hr class="my-3">

                        <div><strong>Kwota umowy (łącznie):</strong> {{ number_format($totalDue, 2, ',', ' ') }} PLN</div>
                        <div><strong>Już opłacono:</strong> {{ number_format($totalPaid, 2, ',', ' ') }} PLN</div>
                        <div><strong>Pozostało łącznie:</strong> {{ number_format($totalRemaining, 2, ',', ' ') }} PLN</div>
                        <div class="mt-2 fs-5">
                            <strong>Do zapłaty teraz:</strong>
                            {{ number_format($chargeAmount, 2, ',', ' ') }} PLN
                            @if(is_array($next) && filled($next['label'] ?? null))
                                <span class="text-muted fs-6">({{ $next['label'] }}@if(filled($next['due_date'] ?? null)), termin {{ $next['due_date'] }}@endif)</span>
                            @endif
                        </div>

                        @if(!empty($scheduleLines))
                            <div class="mt-3">
                                <strong>Harmonogram wpłat:</strong>
                                <ul class="mb-0 mt-2">
                                    @foreach($scheduleLines as $line)
                                        <li class="{{ ($line['is_paid'] ?? false) ? 'text-muted' : '' }}">
                                            {{ $line['label'] }}:
                                            @if(($line['is_pln'] ?? false))
                                                {{ number_format((float) $line['amount'], 2, ',', ' ') }} PLN
                                                @if((float) ($line['paid_amount'] ?? 0) > 0)
                                                    <span class="text-muted">(wpłacono {{ number_format((float) $line['paid_amount'], 2, ',', ' ') }} PLN)</span>
                                                @endif
                                                @if(($line['is_paid'] ?? false))
                                                    — opłacona
                                                @elseif((float) ($line['remaining'] ?? 0) > 0 && ($next['id'] ?? null) === ($line['id'] ?? null))
                                                    — <strong>teraz</strong>
                                                @endif
                                            @endif
                                            @if(($line['is_fx'] ?? false))
                                                {{ number_format((float) $line['amount_foreign'], 2, ',', ' ') }}
                                                {{ strtoupper((string) ($line['currency_code'] ?? '')) }}
                                                <span class="text-muted">
                                                    ({{ ($line['paid_by'] ?? 'pilot') === 'office' ? 'biuro' : 'pilot / autokar' }})
                                                </span>
                                            @endif
                                            @if(filled($line['due_date'] ?? null))
                                                <span class="text-muted">· termin {{ $line['due_date'] }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>

                    @if($chargeAmount <= 0.009)
                        <div class="alert alert-success">
                            Brak kolejnej raty PLN do zapłaty.
                            @if(!empty($fxRows))
                                Pozostaje składowa walutowa — wskaż gdzie ją uregulujesz.
                            @endif
                        </div>

                        @if(!empty($fxRows))
                            <form class="reservation-form" method="POST" action="{{ route('agreement.flow.pay', ['token' => $agreement->public_token]) }}">
                                @csrf
                                <input type="hidden" name="payment_method" value="{{ $selectedMethod }}">
                                <input type="hidden" name="accept_demo" value="1">

                                <div class="white_box mb-4">
                                    <h3 class="h5">Waluta obca — gdzie zapłacisz?</h3>
                                    <p class="small text-muted mb-3">
                                        Najczęściej gotówka u pilota / w autokarze, ale możesz wskazać biuro.
                                    </p>
                                    @foreach($fxRows as $fx)
                                        <div class="mb-3">
                                            <div class="fw-semibold mb-1">
                                                {{ $fx['label'] ?: 'Waluta' }}:
                                                {{ number_format((float) $fx['amount_foreign'], 2, ',', ' ') }}
                                                {{ $fx['currency_code'] }}
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio"
                                                       name="fx_location[{{ $fx['id'] }}]"
                                                       id="fx_pilot_done_{{ $fx['id'] }}"
                                                       value="pilot"
                                                       {{ old('fx_location.'.$fx['id'], $fx['paid_by'] ?? 'pilot') === 'pilot' ? 'checked' : '' }}>
                                                <label class="form-check-label" for="fx_pilot_done_{{ $fx['id'] }}">
                                                    U pilota / w autokarze
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio"
                                                       name="fx_location[{{ $fx['id'] }}]"
                                                       id="fx_office_done_{{ $fx['id'] }}"
                                                       value="office"
                                                       {{ old('fx_location.'.$fx['id'], $fx['paid_by'] ?? 'pilot') === 'office' ? 'checked' : '' }}>
                                                <label class="form-check-label" for="fx_office_done_{{ $fx['id'] }}">
                                                    W biurze (przelew / kasa)
                                                </label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="mt-3 d-flex flex-wrap gap-2">
                                    <button class="btn btn-primary" type="submit">Zapisz wybór i dalej</button>
                                    <a href="{{ route('agreement.flow.success', ['token' => $agreement->public_token]) }}" class="btn btn-outline-secondary">Pomiń</a>
                                </div>
                            </form>
                        @else
                            <div class="mt-3">
                                <a href="{{ route('agreement.flow.success', ['token' => $agreement->public_token]) }}" class="btn btn-primary">Dalej</a>
                            </div>
                        @endif
                    @else
                        <form class="reservation-form" method="POST" action="{{ route('agreement.flow.pay', ['token' => $agreement->public_token]) }}">
                            @csrf

                            @if(!empty($fxRows))
                                <div class="white_box mb-4">
                                    <h3 class="h5">Waluta obca — gdzie zapłacisz?</h3>
                                    <p class="small text-muted mb-3">
                                        Najczęściej gotówka u pilota / w autokarze, ale możesz wskazać biuro.
                                        Ta część <strong>nie wchodzi</strong> do płatności PLN online.
                                    </p>
                                    @foreach($fxRows as $fx)
                                        <div class="mb-3">
                                            <div class="fw-semibold mb-1">
                                                {{ $fx['label'] ?: 'Waluta' }}:
                                                {{ number_format((float) $fx['amount_foreign'], 2, ',', ' ') }}
                                                {{ $fx['currency_code'] }}
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio"
                                                       name="fx_location[{{ $fx['id'] }}]"
                                                       id="fx_pilot_{{ $fx['id'] }}"
                                                       value="pilot"
                                                       {{ old('fx_location.'.$fx['id'], $fx['paid_by'] ?? 'pilot') === 'pilot' ? 'checked' : '' }}>
                                                <label class="form-check-label" for="fx_pilot_{{ $fx['id'] }}">
                                                    U pilota / w autokarze
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio"
                                                       name="fx_location[{{ $fx['id'] }}]"
                                                       id="fx_office_{{ $fx['id'] }}"
                                                       value="office"
                                                       {{ old('fx_location.'.$fx['id'], $fx['paid_by'] ?? 'pilot') === 'office' ? 'checked' : '' }}>
                                                <label class="form-check-label" for="fx_office_{{ $fx['id'] }}">
                                                    W biurze (przelew / kasa)
                                                </label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <div class="payment-methods-panel">
                                <p class="mb-2"><strong>Metoda płatności tej raty ({{ number_format($chargeAmount, 2, ',', ' ') }} PLN):</strong></p>
                                @foreach($methods as $methodKey => $methodLabel)
                                    <label class="payment-option {{ $selectedMethod === $methodKey ? 'is-selected' : '' }}">
                                        <span class="payment-radio">
                                            <input type="radio" name="payment_method" value="{{ $methodKey }}" {{ $selectedMethod === $methodKey ? 'checked' : '' }}>
                                            <span class="payment-option__label">{{ $methodLabel }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>

                            <div class="payment-alert payment-alert--info mt-4">
                                <div class="payment-alert__content">
                                    Po tej płatności wrócisz tu po kolejną ratę albo przejdziesz do podsumowania, jeśli PLN jest domknięte.
                                </div>
                            </div>

                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" value="1" id="accept_demo" name="accept_demo" required>
                                <label class="form-check-label" for="accept_demo">
                                    Potwierdzam płatność demo kwoty {{ number_format($chargeAmount, 2, ',', ' ') }} PLN.
                                </label>
                            </div>

                            <div class="mt-4 d-flex flex-wrap justify-content-between gap-2">
                                <a href="{{ route('agreement.flow.personal', ['token' => $agreement->public_token]) }}" class="btn btn-outline-secondary">Wróć</a>
                                <button class="btn btn-primary" type="submit">Zapłać {{ number_format($chargeAmount, 2, ',', ' ') }} PLN</button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
