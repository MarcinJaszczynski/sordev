@php
    $selectedMethod = old('payment_method', data_get($flow ?? [], 'payment_method', 'demo_transfer'));
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

                    <h2>Metody płatności</h2>

                    <div class="alert alert-info mb-4">
                        To jest środowisko <strong>demo płatności</strong>. Zatwierdzenie formularza oznacza testowe opłacenie umowy.
                    </div>

                    <div class="white_box mb-4">
                        <div><strong>Numer umowy:</strong> {{ $agreement->agreement_number ?: ('#' . $agreement->id) }}</div>
                        <div><strong>Płatnik:</strong> {{ $agreement->signer_name ?: $agreement->customer_name ?: '—' }}</div>
                        <div><strong>Impreza:</strong> {{ $agreement->event_name ?: ($agreement->event?->name ?? '—') }}</div>
                        <div><strong>Kwota do zapłaty:</strong> {{ number_format((float) $agreement->amount_due, 2, ',', ' ') }} {{ strtoupper((string) ($agreement->currency ?: 'PLN')) }}</div>

                        @if(!empty($agreement->attachments))
                            <div class="mt-3">
                                <strong>Załączniki:</strong>
                                <ul class="mb-0 mt-2">
                                    @foreach((array) $agreement->attachments as $file)
                                        <li>
                                            <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($file) }}" target="_blank" rel="noopener">
                                                {{ basename($file) }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>

                    <form method="POST" action="{{ route('agreement.flow.pay', ['token' => $agreement->public_token]) }}">
                        @csrf

                        <div class="payment-methods-panel">
                            @foreach($methods as $methodKey => $methodLabel)
                                <label class="payment-option d-block mb-3">
                                    <div class="payment-option-header">
                                        <div class="payment-radio">
                                            <input type="radio" name="payment_method" value="{{ $methodKey }}" {{ $selectedMethod === $methodKey ? 'checked' : '' }}>
                                            <span>{{ $methodLabel }}</span>
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>

                        <div class="payment-alert payment-alert--info mt-4">
                            <div class="payment-alert__content">
                                Jeżeli nie możesz zrealizować płatności lub wystąpił problem, skontaktuj się z biurem.
                            </div>
                        </div>

                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" value="1" id="accept_demo" name="accept_demo" required>
                            <label class="form-check-label" for="accept_demo">
                                Potwierdzam wykonanie płatności demo.
                            </label>
                        </div>

                        <div class="mt-4 d-flex flex-wrap justify-content-between gap-2">
                            <a href="{{ route('agreement.flow.personal', ['token' => $agreement->public_token]) }}" class="btn btn-outline-secondary">Wróć</a>
                            <button class="btn btn-primary" type="submit">Dalej</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
