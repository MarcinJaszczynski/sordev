@php
    $address = data_get($flow ?? [], 'signer_address', []);
@endphp

<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Umowa online - Podsumowanie</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/reservation.css') }}">
    <link rel="stylesheet" href="{{ asset('css/reservation5.css') }}">
</head>
<body class="bg-light">
<div class="reservation-page">
    <div class="container narrow py-4 py-md-5">
        <div class="row justify-content-center mb-4">
            <div class="col-12 col-lg-10">
                @include('front.agreements._stepper', ['agreement' => $agreement, 'active' => 5])
            </div>
        </div>

        <header class="confirmation-banner">
            <h3>Potwierdzenie rezerwacji</h3>
            <p class="lead">Dziękujemy za poprawne opłacenie umowy!</p>
            <p class="small">Potwierdzenie zostało wysłane na adres {{ $agreement->signer_email ?: $agreement->customer_email ?: '—' }}.</p>
            @if(!empty($portalLoginUrl))
                <p class="small mt-2">
                    <a href="{{ $portalLoginUrl }}" class="btn btn-primary btn-sm">Przejdź do portalu klienta</a>
                    — program, umowa i harmonogram płatności.
                </p>
            @endif
        </header>

        <div class="row justify-content-center mt-4">
            <div class="col-12 col-lg-10">
                <section class="order-summary reservation-stepper">
                    <div class="order-summary-inner">
                        <h3>Podsumowanie</h3>
                        <div class="summary-grid">
                            <div class="summary-image">
                                <img src="{{ asset('uploads/reservation-info.jpg') }}" alt="Podsumowanie umowy">
                            </div>
                            <div class="summary-details">
                                <div class="product-title">{{ $agreement->event_name ?: ($agreement->event?->name ?? '—') }}</div>
                                <div class="price-block">
                                    <div class="price-row"><span>Numer umowy:</span><span class="price-value">{{ $agreement->agreement_number ?: ('#' . $agreement->id) }}</span></div>
                                    <div class="price-row"><span>Typ umowy:</span><span class="price-value">{{ $agreement->agreement_type_label }}</span></div>
                                    <div class="price-row"><span>Termin:</span><span class="price-value">{{ optional($agreement->event_start_date)->format('d.m.Y') ?: '—' }} - {{ optional($agreement->event_end_date)->format('d.m.Y') ?: '—' }}</span></div>
                                </div>

                                <div class="product-meta">
                                    <div class="meta-row"><span>Płatnik:</span><span>{{ $agreement->signer_name ?: $agreement->customer_name ?: '—' }}</span></div>
                                    <div class="meta-row"><span>Uczestnik:</span><span>{{ $agreement->participant_name ?: '—' }}</span></div>
                                    <div class="meta-row"><span>Data płatności:</span><span>{{ optional($agreement->paid_at)->format('d.m.Y H:i') ?: '—' }}</span></div>
                                    <div class="meta-row"><span>Status płatności:</span><span>{{ $agreement->payment_status_label }}</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <div class="row justify-content-center mt-4">
            <div class="col-12 col-lg-10">
                <section class="order-total reservation-stepper">
                    <div class="order-total-inner">
                        <h3>Koszty</h3>
                        <div class="total-table">
                            <div class="total-row"><span>Kwota umowy:</span><span>{{ number_format((float) $agreement->amount_due, 2, ',', ' ') }} {{ strtoupper((string) ($agreement->currency ?: 'PLN')) }}</span></div>
                            <div class="total-row"><span>Opłacono:</span><span>{{ number_format((float) $agreement->amount_paid, 2, ',', ' ') }} {{ strtoupper((string) ($agreement->currency ?: 'PLN')) }}</span></div>
                            <div class="total-line"><strong>Do dopłaty:</strong><strong class="total-amount">{{ number_format(max(0, (float) $agreement->amount_due - (float) $agreement->amount_paid), 2, ',', ' ') }} {{ strtoupper((string) ($agreement->currency ?: 'PLN')) }}</strong></div>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <div class="row justify-content-center mt-4">
            <div class="col-12 col-lg-10">
                <section class="billing-shipping reservation-stepper">
                    <div class="billing-shipping-inner">
                        <h3>Dane osobowe</h3>
                        <div class="billing-grid">
                            <div class="bill">
                                <h4>Dane rezerwującego:</h4>
                                <p>
                                    <strong>{{ $agreement->signer_name ?: $agreement->customer_name ?: '—' }}</strong><br>
                                    Numer telefonu: {{ $agreement->signer_phone ?: $agreement->customer_phone ?: '—' }}<br>
                                    Email: {{ $agreement->signer_email ?: $agreement->customer_email ?: '—' }}<br>
                                    Adres: {{ trim((string) (data_get($address, 'street') . ' ' . data_get($address, 'number') . ' ' . data_get($address, 'postal_code') . ' ' . data_get($address, 'city') . ' ' . data_get($address, 'province'))) ?: '—' }}
                                </p>
                            </div>
                            <div class="ship">
                                <h4>Dane uczestnika:</h4>
                                <p>
                                    <strong>{{ $agreement->participant_name ?: '—' }}</strong><br>
                                    Data urodzenia: {{ optional($agreement->participant_birth_date)->format('d.m.Y') ?: '—' }}<br>
                                    Email: {{ $agreement->participant_email ?: '—' }}<br>
                                    Telefon: {{ $agreement->participant_phone ?: '—' }}
                                </p>
                            </div>
                        </div>

                        @if(!empty($agreement->attachments))
                            <div class="mt-4">
                                <h4>Załączniki</h4>
                                <ul class="mb-0">
                                    @foreach((array) $agreement->attachments as $file)
                                        <li><a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($file) }}" target="_blank" rel="noopener">{{ basename($file) }}</a></li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>
</body>
</html>
