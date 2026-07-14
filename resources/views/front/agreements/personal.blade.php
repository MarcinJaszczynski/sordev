@php
    $address = data_get($flow ?? [], 'signer_address', []);
@endphp

<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Umowa online - Dane osobowe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/reservation.css') }}">
</head>
<body class="bg-light">
<div class="container py-4 py-md-5 reservation-page">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-9">
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

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-lg-5">
                    @include('front.agreements._stepper', ['agreement' => $agreement, 'active' => 3])

                    <h2>Dane zamawiającego / opiekuna</h2>

                    <form class="reservation-form" method="POST" action="{{ route('agreement.flow.personal.store', ['token' => $agreement->public_token]) }}">
                        @csrf

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <input type="text" name="signer_name" class="form-control" placeholder="Imię i nazwisko zamawiającego" value="{{ old('signer_name', $agreement->signer_name ?: $agreement->customer_name) }}" required>
                            </div>
                            <div class="form-group col-md-6">
                                <input type="email" name="signer_email" class="form-control" placeholder="Email zamawiającego" value="{{ old('signer_email', $agreement->signer_email ?: $agreement->customer_email) }}" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <input type="tel" name="signer_phone" class="form-control" placeholder="Numer telefonu zamawiającego" value="{{ old('signer_phone', $agreement->signer_phone ?: $agreement->customer_phone) }}" {{ $agreement->isIndividual() ? 'required' : '' }}>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-8">
                                <input type="text" name="signer_address_street" class="form-control" placeholder="Ulica" value="{{ old('signer_address_street', data_get($address, 'street')) }}" {{ $agreement->isIndividual() ? 'required' : '' }}>
                            </div>
                            <div class="form-group col-md-4">
                                <input type="text" name="signer_address_number" class="form-control" placeholder="Nr domu/mieszkania" value="{{ old('signer_address_number', data_get($address, 'number')) }}" {{ $agreement->isIndividual() ? 'required' : '' }}>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <input type="text" name="signer_postal_code" class="form-control" placeholder="Kod pocztowy" value="{{ old('signer_postal_code', data_get($address, 'postal_code')) }}" {{ $agreement->isIndividual() ? 'required' : '' }}>
                            </div>
                            <div class="form-group col-md-4">
                                <input type="text" name="signer_city" class="form-control" placeholder="Miejscowość" value="{{ old('signer_city', data_get($address, 'city')) }}" {{ $agreement->isIndividual() ? 'required' : '' }}>
                            </div>
                            <div class="form-group col-md-4">
                                <input type="text" name="signer_province" class="form-control" placeholder="Województwo" value="{{ old('signer_province', data_get($address, 'province')) }}">
                            </div>
                        </div>

                        <h2 class="mt-4">Dane uczestnika</h2>
                        @if($agreement->isIndividual())
                            <p class="small text-muted">Dla umowy indywidualnej pola uczestnika są wymagane.</p>
                        @endif

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <input type="text" name="participant_name" class="form-control" placeholder="Imię i nazwisko uczestnika" value="{{ old('participant_name', $agreement->participant_name ?: $agreement->participantPayment?->participant_name) }}" {{ $agreement->isIndividual() ? 'required' : '' }}>
                            </div>
                            <div class="form-group col-md-6">
                                <input type="date" name="participant_birth_date" class="form-control" value="{{ old('participant_birth_date', optional($agreement->participant_birth_date)->toDateString()) }}" {{ $agreement->isIndividual() ? 'required' : '' }}>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <input type="email" name="participant_email" class="form-control" placeholder="Email uczestnika (opcjonalnie)" value="{{ old('participant_email', $agreement->participant_email) }}">
                            </div>
                            <div class="form-group col-md-6">
                                <input type="tel" name="participant_phone" class="form-control" placeholder="Telefon uczestnika (opcjonalnie)" value="{{ old('participant_phone', $agreement->participant_phone) }}">
                            </div>
                        </div>

                        <div class="form-group mt-4 text-end">
                            <button type="submit" class="btn btn-primary">Dalej</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
