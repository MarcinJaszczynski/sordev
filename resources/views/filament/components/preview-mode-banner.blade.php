@php
    /** @var string $title */
    /** @var string $description */
    /** @var string $accentClass */
@endphp
<div
    class="mb-4 rounded-lg border px-4 py-3 text-sm {{ $accentClass }}"
    role="status"
    aria-live="polite"
>
    <div class="font-semibold">{{ $title }}</div>
    <div class="mt-0.5 opacity-90">{{ $description }}</div>
</div>
