@php
    $token = $agreement->public_token ?? $token ?? null;
@endphp

<style>
    .stepper-link {
        text-decoration: none;
    }

    .stepper-link:hover .stepper-label {
        text-decoration: underline;
    }
</style>

<div class="reservation-stepper" aria-label="Kroki zawarcia umowy">
    <div class="stepper-items">
        <div class="stepper-item">
            <a class="stepper-link" href="{{ route('agreement.flow.show', ['token' => $token]) }}">
                <button class="stepper-dot {{ isset($active) && $active === 1 ? 'is-active' : '' }}" type="button">1</button>
                <span class="stepper-label">Plan wycieczki</span>
            </a>
        </div>
        <div class="stepper-item">
            <a class="stepper-link" href="{{ route('agreement.flow.consents', ['token' => $token]) }}">
                <button class="stepper-dot {{ isset($active) && $active === 2 ? 'is-active' : '' }}" type="button">2</button>
                <span class="stepper-label">Wymagane zgody</span>
            </a>
        </div>
        <div class="stepper-item">
            <a class="stepper-link" href="{{ route('agreement.flow.personal', ['token' => $token]) }}">
                <button class="stepper-dot {{ isset($active) && $active === 3 ? 'is-active' : '' }}" type="button">3</button>
                <span class="stepper-label">Dane osobowe</span>
            </a>
        </div>
        <div class="stepper-item">
            <a class="stepper-link" href="{{ route('agreement.flow.payment', ['token' => $token]) }}">
                <button class="stepper-dot {{ isset($active) && $active === 4 ? 'is-active' : '' }}" type="button">4</button>
                <span class="stepper-label">Metody płatności</span>
            </a>
        </div>
        <div class="stepper-item">
            <a class="stepper-link" href="{{ route('agreement.flow.success', ['token' => $token]) }}">
                <button class="stepper-dot {{ isset($active) && $active === 5 ? 'is-active' : '' }}" type="button">5</button>
                <span class="stepper-label">Podsumowanie</span>
            </a>
        </div>
    </div>
</div>
