<!-- iziToast -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/izitoast@1.4.0/dist/css/iziToast.min.css">
<script src="https://cdn.jsdelivr.net/npm/izitoast@1.4.0/dist/js/iziToast.min.js"></script>

@if(session('success'))
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            iziToast.success({
                title: 'Sukces',
                message: @json(session('success')),
                position: 'topRight',
                timeout: 5000,
                close: true,
                progressBar: true
            });
        });
    </script>
@endif

@extends('front.layout.master')

@section('main_content')
<style>
    /* Wniosek o fakturę — rozszerzenie stylu contact-form / contact-divide-box */
    .invoice-request-page .contact-divide-box {
        align-items: flex-start;
        gap: 1.5rem;
        padding-bottom: 3rem;
    }

    .invoice-request-page .invoice-info {
        color: #333;
    }

    .invoice-request-page .invoice-info .header h1 {
        font-size: clamp(1.6rem, 2.4vw, 2rem);
        font-weight: 600;
        margin-bottom: 0.35em;
        line-height: 1.25;
    }

    .invoice-request-page .invoice-info .lead {
        color: rgba(51, 51, 51, 0.85);
        font-size: 1.05rem;
        line-height: 1.55;
        margin-bottom: 1.5rem;
    }

    .invoice-request-page .invoice-steps {
        list-style: none;
        padding: 0;
        margin: 0 0 1.75rem;
        display: grid;
        gap: 0.85rem;
    }

    .invoice-request-page .invoice-steps li {
        display: flex;
        gap: 0.85rem;
        align-items: flex-start;
        line-height: 1.45;
    }

    .invoice-request-page .invoice-steps .step-num {
        flex: 0 0 1.85rem;
        width: 1.85rem;
        height: 1.85rem;
        border-radius: 50%;
        background: #ce0d0d;
        color: #fff;
        font-size: 0.85rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-top: 0.1rem;
    }

    .invoice-request-page .invoice-note {
        border-left: 3px solid #ce0d0d;
        padding: 0.85rem 1rem;
        background: rgba(206, 13, 13, 0.05);
        border-radius: 0 12px 12px 0;
        font-size: 0.95rem;
        line-height: 1.5;
        margin-bottom: 1.5rem;
    }

    .invoice-request-page .invoice-note strong {
        display: block;
        margin-bottom: 0.25rem;
    }

    .invoice-request-page .invoice-contact-mini {
        font-size: 0.95rem;
        color: rgba(51, 51, 51, 0.9);
    }

    .invoice-request-page .invoice-contact-mini a {
        color: inherit;
        text-decoration: none;
    }

    .invoice-request-page .invoice-contact-mini a:hover {
        color: #ce0d0d;
    }

    .invoice-request-page .invoice-contact-mini .row-item {
        display: flex;
        gap: 0.65rem;
        align-items: center;
        margin-bottom: 0.45rem;
    }

    .invoice-request-page .invoice-contact-mini i {
        color: #ce0d0d;
        width: 1.1rem;
        text-align: center;
    }

    .invoice-request-page .contact-form {
        padding: 1.75rem 1.75rem 1.25rem;
    }

    .invoice-request-page .contact-form h4 {
        margin-bottom: 0.35em;
    }

    .invoice-request-page .form-intro {
        color: rgba(51, 51, 51, 0.75);
        font-size: 0.95rem;
        margin-bottom: 1.25rem;
        line-height: 1.45;
    }

    .invoice-request-page .form-section {
        margin-top: 1.35rem;
        padding-top: 1.1rem;
        border-top: 1px solid rgba(107, 107, 107, 0.2);
    }

    .invoice-request-page .form-section:first-of-type {
        margin-top: 0.5rem;
        padding-top: 0;
        border-top: 0;
    }

    .invoice-request-page .form-section-title {
        font-size: 0.8rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: rgba(51, 51, 51, 0.65);
        margin: 0 0 0.85rem;
    }

    .invoice-request-page .buyer-toggle {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.65rem;
        margin-bottom: 0.35rem;
    }

    .invoice-request-page .buyer-toggle label {
        margin-top: 0 !important;
        display: flex !important;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        text-align: center;
        padding: 0.75rem 0.65rem;
        border: 1px solid rgba(107, 107, 107, 0.45);
        border-radius: 999px;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.92rem;
        line-height: 1.25;
        transition: border-color 0.2s, background 0.2s, color 0.2s, box-shadow 0.2s;
        user-select: none;
    }

    .invoice-request-page .buyer-toggle input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
        width: 0;
        height: 0;
    }

    .invoice-request-page .buyer-toggle label:hover {
        border-color: rgba(206, 13, 13, 0.55);
    }

    .invoice-request-page .buyer-toggle label.is-active {
        background: #ce0d0d;
        border-color: #ce0d0d;
        color: #fff;
        box-shadow: 0 6px 16px rgba(206, 13, 13, 0.22);
    }

    .invoice-request-page .field-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0 1rem;
    }

    .invoice-request-page .field-grid .span-2 {
        grid-column: 1 / -1;
    }

    .invoice-request-page .contact-form label {
        margin-top: 0.9em;
    }

    .invoice-request-page .contact-form input[type="email"],
    .invoice-request-page .contact-form input[type="tel"],
    .invoice-request-page .contact-form input[type="number"],
    .invoice-request-page .contact-form input[type="text"],
    .invoice-request-page .contact-form textarea {
        border: none;
        border-bottom: 1px solid rgba(107, 107, 107, 0.65);
        background-color: transparent;
        width: 100%;
        padding: 0.45em 0.2em;
        box-sizing: border-box;
        outline: none;
        resize: vertical;
        max-width: 100%;
    }

    .invoice-request-page .contact-form input:focus,
    .invoice-request-page .contact-form textarea:focus {
        border-bottom-color: #333;
    }

    .invoice-request-page .contact-form input.is-invalid,
    .invoice-request-page .contact-form textarea.is-invalid {
        border-bottom-color: #ce0d0d;
    }

    .invoice-request-page .field-error {
        color: #ce0d0d;
        font-size: 0.82rem;
        margin-top: 0.25rem;
        display: block;
    }

    .invoice-request-page .code-row {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 0.65rem;
        align-items: end;
    }

    .invoice-request-page .code-row .check-code-btn {
        appearance: none;
        border: 1px solid rgba(107, 107, 107, 0.55);
        background: #fff;
        color: #333;
        font-weight: 600;
        font-size: 0.9rem;
        padding: 0.55rem 1rem;
        border-radius: 999px;
        white-space: nowrap;
        cursor: pointer;
        transition: background 0.2s, border-color 0.2s, color 0.2s;
        margin-bottom: 0.15rem;
    }

    .invoice-request-page .code-row .check-code-btn:hover {
        border-color: #ce0d0d;
        color: #ce0d0d;
    }

    .invoice-request-page .code-row .check-code-btn:disabled {
        opacity: 0.65;
        cursor: wait;
    }

    .invoice-request-page .code-hint {
        min-height: 1.25rem;
        font-size: 0.88rem;
        margin: 0.35rem 0 0;
        line-height: 1.35;
    }

    .invoice-request-page .code-hint.is-ok { color: #1b7a3d; }
    .invoice-request-page .code-hint.is-bad { color: #ce0d0d; }
    .invoice-request-page .code-hint.is-muted { color: rgba(51, 51, 51, 0.55); }

    .invoice-request-page .contact-form .center {
        margin-top: 0.5rem;
    }

    .invoice-request-page .contact-form input[type="submit"] {
        width: auto;
        min-width: 12rem;
        max-width: 100%;
        cursor: pointer;
    }

    .invoice-request-page .submit-note {
        margin-top: 0.85rem;
        font-size: 0.82rem;
        color: rgba(51, 51, 51, 0.6);
        line-height: 1.4;
    }

    @media (max-width: 767px) {
        .invoice-request-page .contact-form {
            padding: 1.35rem 1.15rem 1rem;
            border-radius: 22px;
        }

        .invoice-request-page .buyer-toggle {
            grid-template-columns: 1fr;
        }

        .invoice-request-page .field-grid {
            grid-template-columns: 1fr;
        }

        .invoice-request-page .code-row {
            grid-template-columns: 1fr;
        }

        .invoice-request-page .code-row .check-code-btn {
            width: 100%;
            margin-top: 0.35rem;
        }

        .invoice-request-page .contact-form input[type="submit"] {
            width: 100%;
        }
    }
</style>

<div class="invoice-request-page">
    <div class="page-top">
        <div class="container">
            <div class="row">
                <div class="col-md-12">
                    <div class="breadcrumb-container">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('home') }}">Start</a></li>
                            <li class="breadcrumb-item active">Wniosek o fakturę</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container pt_50">
        <div class="contact-divide-box">
            <div class="contact-section-left invoice-info">
                <div class="header">
                    <h1>Wniosek o fakturę</h1>
                </div>
                <p class="lead">
                    Faktura za imprezę turystyczną (procedura marży dla biur podróży).
                    Dokument wyślemy e-mailem po zakończeniu wyjazdu.
                </p>

                <ul class="invoice-steps" aria-label="Jak złożyć wniosek">
                    <li>
                        <span class="step-num" aria-hidden="true">1</span>
                        <span>Wybierz typ nabywcy — osoba fizyczna albo firma / szkoła.</span>
                    </li>
                    <li>
                        <span class="step-num" aria-hidden="true">2</span>
                        <span>Podaj dane do faktury oraz kod imprezy z umowy lub oferty.</span>
                    </li>
                    <li>
                        <span class="step-num" aria-hidden="true">3</span>
                        <span>Wyślij wniosek — potwierdzenie otrzymasz na podany e-mail.</span>
                    </li>
                </ul>

                <div class="invoice-note">
                    <strong>Kod imprezy</strong>
                    Znajdziesz go w umowie, ofercie Word lub w portalu klienta przy swojej wycieczce.
                    Możesz też sprawdzić kod przyciskiem w formularzu.
                </div>

                <div class="invoice-contact-mini">
                    <div class="row-item">
                        <i class="fas fa-envelope" aria-hidden="true"></i>
                        <a href="mailto:rafa@bprafa.pl">rafa@bprafa.pl</a>
                    </div>
                    <div class="row-item">
                        <i class="fas fa-phone" aria-hidden="true"></i>
                        <a href="tel:606102243">+48 606 102 243</a>
                    </div>
                </div>
            </div>

            <div class="contact-section-right">
                <form
                    class="contact-form invoice-request-form"
                    method="POST"
                    action="{{ route('invoice-request.submit') }}"
                    id="invoice-request-form"
                    novalidate
                >
                    @csrf

                    <h4>Dane do faktury</h4>
                    <p class="form-intro">Pola oznaczone <span class="required">*</span> są wymagane.</p>

                    <div style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;" aria-hidden="true">
                        <label for="website">Website</label>
                        <input type="text" name="website" id="website" value="" tabindex="-1" autocomplete="off">
                    </div>
                    <input type="hidden" name="form_ts" id="form_ts" value="">

                    <div class="form-section">
                        <p class="form-section-title">Nabywca</p>

                        <div class="buyer-toggle" role="radiogroup" aria-label="Typ nabywcy">
                            <label class="{{ old('buyer_type', $prefill['buyer_type'] ?? 'company') === 'company' ? 'is-active' : '' }}">
                                <input
                                    type="radio"
                                    name="buyer_type"
                                    value="company"
                                    @checked(old('buyer_type', $prefill['buyer_type'] ?? 'company') === 'company')
                                >
                                <i class="fas fa-building" aria-hidden="true"></i>
                                Firma / szkoła
                            </label>
                            <label class="{{ old('buyer_type') === 'person' ? 'is-active' : '' }}">
                                <input
                                    type="radio"
                                    name="buyer_type"
                                    value="person"
                                    @checked(old('buyer_type') === 'person')
                                >
                                <i class="fas fa-user" aria-hidden="true"></i>
                                Osoba fizyczna
                            </label>
                        </div>

                        <div class="field-grid">
                            <div class="span-2">
                                <label for="company_name" id="company_name_label">Nazwa firmy / instytucji <span class="required">*</span></label>
                                <input
                                    type="text"
                                    name="company_name"
                                    id="company_name"
                                    value="{{ old('company_name') }}"
                                    required
                                    maxlength="255"
                                    placeholder="Np. Szkoła Podstawowa nr 1"
                                    class="@error('company_name') is-invalid @enderror"
                                    autocomplete="organization"
                                >
                                @error('company_name')<span class="field-error">{{ $message }}</span>@enderror
                            </div>

                            <div class="span-2" id="nip-wrap">
                                <label for="nip">NIP <span class="required">*</span></label>
                                <input
                                    type="text"
                                    name="nip"
                                    id="nip"
                                    value="{{ old('nip') }}"
                                    maxlength="16"
                                    inputmode="numeric"
                                    placeholder="0000000000"
                                    class="@error('nip') is-invalid @enderror"
                                    autocomplete="off"
                                >
                                @error('nip')<span class="field-error">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <p class="form-section-title">Adres i kontakt</p>
                        <div class="field-grid">
                            <div>
                                <label for="street">Ulica</label>
                                <input type="text" name="street" id="street" value="{{ old('street') }}" maxlength="255" autocomplete="address-line1">
                            </div>
                            <div>
                                <label for="house_number">Nr domu / lokalu</label>
                                <input type="text" name="house_number" id="house_number" value="{{ old('house_number') }}" maxlength="32" autocomplete="address-line2">
                            </div>
                            <div>
                                <label for="postal_code">Kod pocztowy</label>
                                <input type="text" name="postal_code" id="postal_code" value="{{ old('postal_code') }}" maxlength="16" placeholder="00-000" autocomplete="postal-code">
                            </div>
                            <div>
                                <label for="city">Miasto</label>
                                <input type="text" name="city" id="city" value="{{ old('city') }}" maxlength="120" autocomplete="address-level2">
                            </div>
                            <div class="span-2">
                                <label for="invoice_email">E-mail do faktury <span class="required">*</span></label>
                                <input
                                    type="email"
                                    name="invoice_email"
                                    id="invoice_email"
                                    value="{{ old('invoice_email') }}"
                                    required
                                    maxlength="255"
                                    placeholder="faktury@przyklad.pl"
                                    class="@error('invoice_email') is-invalid @enderror"
                                    autocomplete="email"
                                >
                                @error('invoice_email')<span class="field-error">{{ $message }}</span>@enderror
                            </div>
                            <div class="span-2">
                                <label for="applicant_phone">Telefon kontaktowy</label>
                                <input type="tel" name="applicant_phone" id="applicant_phone" value="{{ old('applicant_phone') }}" maxlength="50" placeholder="+48 …" autocomplete="tel">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <p class="form-section-title">Impreza</p>
                        <label for="event_code">Kod imprezy <span class="required">*</span></label>
                        <div class="code-row">
                            <input
                                type="text"
                                name="event_code"
                                id="event_code"
                                value="{{ old('event_code', $prefill['event_code'] ?? '') }}"
                                required
                                maxlength="32"
                                placeholder="np. 26ABCDEF"
                                class="@error('event_code') is-invalid @enderror"
                                autocomplete="off"
                                spellcheck="false"
                            >
                            <button type="button" class="check-code-btn" id="check-event-code">Sprawdź kod</button>
                        </div>
                        <p id="event-code-hint" class="code-hint" aria-live="polite"></p>
                        @error('event_code')<span class="field-error">{{ $message }}</span>@enderror

                        <div class="field-grid">
                            <div>
                                <label for="amount">Kwota (PLN)</label>
                                <input type="number" name="amount" id="amount" value="{{ old('amount') }}" min="0" step="0.01" placeholder="0.00" inputmode="decimal">
                            </div>
                            <div>
                                <label for="payment_reference">Tytuł przelewu / nr umowy</label>
                                <input type="text" name="payment_reference" id="payment_reference" value="{{ old('payment_reference') }}" maxlength="120">
                            </div>
                            <div class="span-2">
                                <label for="notes">Uwagi</label>
                                <textarea name="notes" id="notes" rows="3" maxlength="2000" placeholder="Opcjonalnie">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="center">
                        @include('front.partials.turnstile', ['action' => 'invoice_request_form'])
                        <input type="submit" value="Wyślij wniosek">
                        <p class="submit-note">Wysyłając wniosek, prosisz o wystawienie faktury na podane dane. Potwierdzenie dostaniesz e-mailem.</p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var ts = document.getElementById('form_ts');
        if (ts) { ts.value = Date.now().toString(); }

        var label = document.getElementById('company_name_label');
        var nipWrap = document.getElementById('nip-wrap');
        var nip = document.getElementById('nip');
        var nameInput = document.getElementById('company_name');
        var buyerLabels = document.querySelectorAll('.buyer-toggle label');

        function syncBuyerType() {
            var checked = document.querySelector('input[name="buyer_type"]:checked');
            var type = (checked && checked.value) || 'company';
            var isPerson = type === 'person';

            buyerLabels.forEach(function (el) {
                var input = el.querySelector('input');
                el.classList.toggle('is-active', !!(input && input.checked));
            });

            if (label) {
                label.innerHTML = isPerson
                    ? 'Imię i nazwisko <span class="required">*</span>'
                    : 'Nazwa firmy / instytucji <span class="required">*</span>';
            }
            if (nameInput) {
                nameInput.placeholder = isPerson ? 'Jan Kowalski' : 'Np. Szkoła Podstawowa nr 1';
                nameInput.setAttribute('autocomplete', isPerson ? 'name' : 'organization');
            }
            if (nipWrap) {
                nipWrap.style.display = isPerson ? 'none' : '';
            }
            if (nip) {
                nip.required = !isPerson;
                if (isPerson) { nip.value = ''; }
            }
        }

        document.querySelectorAll('input[name="buyer_type"]').forEach(function (el) {
            el.addEventListener('change', syncBuyerType);
        });
        syncBuyerType();

        var checkBtn = document.getElementById('check-event-code');
        var codeInput = document.getElementById('event_code');
        var hint = document.getElementById('event-code-hint');
        var checkUrl = @json(route('invoice-request.check-code'));

        function setHint(text, state) {
            if (!hint) return;
            hint.textContent = text || '';
            hint.className = 'code-hint' + (state === true ? ' is-ok' : (state === false ? ' is-bad' : ' is-muted'));
        }

        if (checkBtn && codeInput) {
            checkBtn.addEventListener('click', function () {
                var code = (codeInput.value || '').trim();
                if (!code) {
                    setHint('Wpisz kod imprezy.', false);
                    return;
                }
                setHint('Sprawdzam…', null);
                checkBtn.disabled = true;
                fetch(checkUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]')?.value || ''
                    },
                    body: JSON.stringify({ event_code: code })
                }).then(function (r) { return r.json(); })
                  .then(function (data) {
                      setHint(data.message || '', !!data.valid);
                      if (data.valid && data.code) {
                          codeInput.value = data.code;
                      }
                  })
                  .catch(function () {
                      setHint('Nie udało się sprawdzić kodu. Spróbuj ponownie.', false);
                  })
                  .finally(function () {
                      checkBtn.disabled = false;
                  });
            });
        }
    });
</script>
@endsection
