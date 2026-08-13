@php
    $items = $items ?? \App\Support\EventReadinessIndicators::forEventOverview($event);
@endphp

{{-- Stałe 4 karty w jednym rzędzie na desktopie (bez grupowania w dół). --}}
<div class="event-readiness-overview grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ($items as $item)
        @php
            $href = $item['url'] ?? null;
            $tag = $href ? 'a' : 'div';
        @endphp
        <{{ $tag }}
            @if ($href) href="{{ $href }}" @endif
            class="event-readiness-overview-card event-readiness-overview-card--{{ $item['tone'] }}{{ $href ? ' event-readiness-overview-card--link' : '' }}"
            title="{{ $item['title'] }}"
            wire:key="readiness-{{ $item['key'] }}"
        >
            <div class="event-readiness-overview-card__icon-wrap">
                <x-filament::icon :icon="$item['icon']" class="event-readiness-overview-card__icon" />
            </div>
            <div class="min-w-0 flex-1">
                <p class="event-readiness-overview-card__label">{{ $item['label'] }}</p>
                <p class="event-readiness-overview-card__status">{{ $item['status_label'] }}</p>
                <p class="event-readiness-overview-card__hint">{{ $item['title'] }}</p>
            </div>
        </{{ $tag }}>
    @endforeach
</div>
