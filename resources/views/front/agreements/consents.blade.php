@php
    $consents = data_get($flow ?? [], 'consents', []);
@endphp

<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Umowa online - Wymagane zgody</title>
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
                    @include('front.agreements._stepper', ['agreement' => $agreement, 'active' => 2])

                    <h2>Zgody i oświadczenia</h2>

                    <form id="consent-form" method="POST" action="{{ route('agreement.flow.consents.store', ['token' => $agreement->public_token]) }}" novalidate>
                        @csrf

                        <div class="form-group">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="consent_terms" name="consent_terms" {{ old('consent_terms', data_get($consents, 'terms') ? '1' : null) ? 'checked' : '' }}>
                                <label class="form-check-label" for="consent_terms">
                                    <strong>1. Warunki uczestnictwa</strong>
                                    <span class="small text-muted d-block">
                                        Oświadczam, że zapoznałem(-am) się i akceptuję Warunki uczestnictwa dostępne
                                        <a href="#" class="consent-link" data-bs-toggle="modal" data-bs-target="#termsModal">pod tym linkiem</a>.
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="consent_insurance" name="consent_insurance" {{ old('consent_insurance', data_get($consents, 'insurance') ? '1' : null) ? 'checked' : '' }}>
                                <label class="form-check-label" for="consent_insurance">
                                    <strong>2. Warunki ubezpieczenia</strong>
                                    <span class="small text-muted d-block">Akceptuję warunki ubezpieczenia oferowanego w ramach rezerwacji.</span>
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="consent_data" name="consent_data" {{ old('consent_data', data_get($consents, 'data') ? '1' : null) ? 'checked' : '' }}>
                                <label class="form-check-label" for="consent_data">
                                    <strong>3. Przetwarzanie danych osobowych</strong>
                                    <span class="small text-muted d-block">
                                        Wyrażam zgodę na przetwarzanie danych osobowych w celu realizacji rezerwacji. Zobacz
                                        <a href="#" class="consent-link" data-bs-toggle="modal" data-bs-target="#privacyModal">Politykę RODO</a>.
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="consent_comm" name="consent_comm" {{ old('consent_comm', data_get($consents, 'communication') ? '1' : null) ? 'checked' : '' }}>
                                <label class="form-check-label" for="consent_comm">
                                    <strong>4. Zgoda na komunikację elektroniczną</strong>
                                    <span class="small text-muted d-block">Wyrażam zgodę na otrzymywanie informacji dotyczących umowy drogą elektroniczną (e-mail / SMS).</span>
                                </label>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <button type="button" id="check-all" class="btn btn-link">Zaznacz wszystko</button>
                            <button id="consent-next" type="submit" class="btn btn-primary">Dalej</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="termsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Warunki uczestnictwa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
            </div>
            <div class="modal-body terms-scrollable">
                {!! view('front._terms')->render() !!}
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="privacyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Polityka RODO</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
            </div>
            <div class="modal-body">
                {!! view('front._privacy')->render() !!}
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('consent-form');
        var checkboxes = Array.prototype.slice.call(form.querySelectorAll('input[type="checkbox"]'));
        var checkAll = document.getElementById('check-all');

        function allChecked() {
            return checkboxes.every(function (cb) { return cb.checked; });
        }

        checkboxes.forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                if (checkAll) {
                    checkAll.textContent = allChecked() ? 'Odznacz wszystko' : 'Zaznacz wszystko';
                }
            });
        });

        if (checkAll) {
            checkAll.addEventListener('click', function (event) {
                event.preventDefault();
                var shouldCheck = !allChecked();
                checkboxes.forEach(function (checkbox) {
                    checkbox.checked = shouldCheck;
                });
                checkAll.textContent = shouldCheck ? 'Odznacz wszystko' : 'Zaznacz wszystko';
            });
        }
    });
</script>
</body>
</html>
