@php
    /** @var string $title */
    /** @var string $description */
    /** @var string $accentClass */
    /** @var string|null $exitUrl */
@endphp
<div
    class="mb-4 rounded-lg border px-4 py-3 text-sm {{ $accentClass }}"
    role="status"
    aria-live="polite"
>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <div class="font-semibold">{{ $title }}</div>
            <div class="mt-0.5 opacity-90">{{ $description }}</div>
        </div>
        @if(! empty($exitUrl))
            <a href="{{ $exitUrl }}" class="shrink-0 rounded-md border border-current/20 bg-white/70 px-3 py-1.5 text-xs font-semibold hover:bg-white">
                Zakończ podgląd
            </a>
        @endif
    </div>
</div>
