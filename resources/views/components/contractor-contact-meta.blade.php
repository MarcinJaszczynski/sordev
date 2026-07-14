@props([
    'address' => null,
    'phone' => null,
    'email' => null,
    'class' => '',
])

@if ($address || $phone || $email)
    <div {{ $attributes->merge(['class' => 'contractor-contact-details '.$class]) }}>
        @if ($address)
            <div class="contractor-contact-details__line">{{ $address }}</div>
        @endif

        @if ($phone || $email)
            <div class="contractor-contact-details__line flex flex-wrap gap-x-2 gap-y-0.5">
                @if ($phone)
                    <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="hover:underline">{{ $phone }}</a>
                @endif
                @if ($phone && $email)
                    <span aria-hidden="true">·</span>
                @endif
                @if ($email)
                    <a href="mailto:{{ $email }}" class="hover:underline">{{ $email }}</a>
                @endif
            </div>
        @endif
    </div>
@endif
