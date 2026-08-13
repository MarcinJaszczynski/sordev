@php
    use App\Support\ClientPortalMedia;

    $event = $event ?? null;
    $coverUrl = $coverUrl ?? ClientPortalMedia::coverUrl($event);
    $kicker = $kicker ?? 'Wycieczka';
    $subtitle = $subtitle ?? ClientPortalMedia::dateRangeLabel($event);
    if ($event?->startPlace?->name) {
        $subtitle = trim(($subtitle ? $subtitle.' · ' : '').$event->startPlace->name);
    }
@endphp

@if($event)
    <header class="client-portal-hero">
        @if(filled($coverUrl))
            <img src="{{ $coverUrl }}" alt="{{ $event->name }}" loading="lazy">
        @else
            <div class="client-portal-card__media-fallback h-full w-full text-lg">BP RAFA</div>
        @endif
        <div class="client-portal-hero__overlay"></div>
        <div class="client-portal-hero__content">
            <p class="client-portal-kicker text-white/70">{{ $kicker }}</p>
            <h2 class="mt-1 text-xl font-semibold tracking-tight sm:text-2xl">{{ $title ?? $event->name }}</h2>
            @if(filled($subtitle))
                <p class="mt-1 text-sm text-white/80">{{ $subtitle }}</p>
            @endif
        </div>
    </header>
@endif
