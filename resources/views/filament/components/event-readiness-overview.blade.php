@php
    $items = $items ?? \App\Support\EventReadinessIndicators::forEventOverview($event);
@endphp

<div class="event-readiness-overview grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ($items as $item)
        <div
            class="event-readiness-overview-card event-readiness-overview-card--{{ $item['tone'] }}"
            title="{{ $item['title'] }}"
        >
            <div class="event-readiness-overview-card__icon-wrap">
                <x-filament::icon :icon="$item['icon']" class="event-readiness-overview-card__icon" />
            </div>
            <div class="min-w-0 flex-1">
                <p class="event-readiness-overview-card__label">{{ $item['label'] }}</p>
                <p class="event-readiness-overview-card__status">{{ $item['status_label'] }}</p>
                <p class="event-readiness-overview-card__hint">{{ $item['title'] }}</p>
            </div>
        </div>
    @endforeach
</div>
