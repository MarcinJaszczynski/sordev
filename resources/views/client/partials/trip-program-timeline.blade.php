@php
    use App\Services\EventProgramPointOrderService;

    $service = app(EventProgramPointOrderService::class);
    $points = isset($programPoints) ? collect($programPoints) : $service->clientProgramPoints($event);
    $baseDate = $event->start_date?->copy()->startOfDay() ?? now()->startOfDay();
    $byDay = $points->groupBy(fn ($point) => max(1, (int) ($point->day ?? 1)));
    $coverUrl = $coverUrl ?? ($event->eventTemplate?->full_image_url ?: $event->eventTemplate?->preview_image_url);
    $hideHero = (bool) ($hideHero ?? false);
    $hideTimes = (bool) ($hideTimes ?? false);

    $pointTitle = static function ($point): string {
        $name = $point->templatePoint->name ?? $point->name ?? ('Punkt #'.$point->id);

        return trim((string) preg_replace('/\s*-?\s*\d+:\d+h?.*$/u', '', $name));
    };

    $pointDescription = static function ($point): ?string {
        if ($point->show_description === false) {
            return null;
        }

        $raw = $point->resolvedDescription();
        if (! filled($raw)) {
            return null;
        }

        $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $raw)) ?? '');

        return $text !== '' ? $text : null;
    };

    $today = now()->startOfDay();
    $openDay = null;
    $sortedDays = $byDay->sortKeys();
    foreach ($sortedDays as $dayKey => $_) {
        $candidate = $baseDate->copy()->addDays(((int) $dayKey) - 1)->startOfDay();
        if ($candidate->equalTo($today)) {
            $openDay = (int) $dayKey;
            break;
        }
        if ($candidate->greaterThanOrEqualTo($today) && $openDay === null) {
            $openDay = (int) $dayKey;
        }
    }
    if ($openDay === null && $sortedDays->isNotEmpty()) {
        $openDay = (int) $sortedDays->keys()->last();
    }
@endphp

<div class="space-y-3">
    @if(! $hideHero && filled($coverUrl))
        <header class="client-portal-hero" style="background-image:url('{{ $coverUrl }}'); background-size:cover; background-position:center;">
            <div class="client-portal-hero__overlay"></div>
            <div class="client-portal-hero__content">
                <h2>Program wycieczki</h2>
                <p class="mt-1 text-sm text-white/80">
                    {{ $event->name }}
                    @if($event->start_date)
                        · {{ $event->start_date->format('d.m.Y') }}
                        @if($event->end_date)
                            – {{ $event->end_date->format('d.m.Y') }}
                        @endif
                    @endif
                </p>
            </div>
        </header>
    @endif

    <section class="portal-card">
        <div class="portal-card-title">
            <p>Program — {{ $event->name }}</p>
        </div>

        @forelse($sortedDays as $day => $dayPoints)
            @php
                $dayDate = $baseDate->copy()->addDays($day - 1);
                $parents = $dayPoints->whereNull('parent_id')->values();
                $childrenByParent = $dayPoints
                    ->whereNotNull('parent_id')
                    ->groupBy('parent_id');
                $orphanChildren = $dayPoints
                    ->whereNotNull('parent_id')
                    ->filter(fn ($child) => ! $parents->contains('id', $child->parent_id))
                    ->values();
                $visibleCount = $parents->count() + $orphanChildren->count();
                $dayLabel = 'Dzień '.$day.' · '.$dayDate->locale('pl')->translatedFormat('d.m (l)');
                $countLabel = $visibleCount === 1
                    ? '1 punkt'
                    : ($visibleCount.' punktów');
                $isOpen = $openDay !== null && (int) $day === (int) $openDay;
            @endphp

            <details class="portal-day-accordion" @if($isOpen) open @endif id="day-{{ $day }}">
                <summary>
                    <p>{{ $dayLabel }}</p>
                    <p>{{ $countLabel }}</p>
                </summary>

                <ul class="client-portal-program__list" style="margin-top:0.5rem;">
                    @foreach($parents as $point)
                        @php
                            $title = $pointTitle($point);
                            $description = $pointDescription($point);
                            $showBold = $point->show_title_style !== false;
                            $start = (! $hideTimes) ? $point->displayStartTime() : null;
                            $end = (! $hideTimes) ? $point->displayEndTime() : null;
                            $children = $childrenByParent->get($point->id, collect());
                        @endphp
                        <li class="client-portal-program__item">
                            <div class="client-portal-program__item-main">
                                @unless($hideTimes)
                                    @if($start || $end)
                                        <span class="client-portal-program__time">
                                            {{ $start }}{{ $start && $end ? '–' : '' }}{{ $end }}
                                        </span>
                                    @endif
                                @endunless
                                <span class="client-portal-program__bullet" aria-hidden="true">•</span>
                                @if($showBold)
                                    <strong>{{ $title }}</strong>
                                @else
                                    {{ $title }}
                                @endif
                                @if(filled($description))
                                    <span class="client-portal-program__desc">– {{ $description }}</span>
                                @endif
                            </div>

                            @if($children->isNotEmpty())
                                <ul class="client-portal-program__children">
                                    @foreach($children as $child)
                                        @php
                                            $childTitle = $pointTitle($child);
                                            $childDescription = $pointDescription($child);
                                            $childBold = $child->show_title_style !== false;
                                        @endphp
                                        <li class="client-portal-program__item client-portal-program__item--child">
                                            <span class="client-portal-program__bullet" aria-hidden="true">•</span>
                                            @if($childBold)
                                                <strong>{{ $childTitle }}</strong>
                                            @else
                                                {{ $childTitle }}
                                            @endif
                                            @if(filled($childDescription))
                                                <span class="client-portal-program__desc">– {{ $childDescription }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach

                    @foreach($orphanChildren as $point)
                        @php
                            $title = $pointTitle($point);
                            $description = $pointDescription($point);
                            $showBold = $point->show_title_style !== false;
                        @endphp
                        <li class="client-portal-program__item">
                            <span class="client-portal-program__bullet" aria-hidden="true">•</span>
                            @if($showBold)
                                <strong>{{ $title }}</strong>
                            @else
                                {{ $title }}
                            @endif
                            @if(filled($description))
                                <span class="client-portal-program__desc">– {{ $description }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </details>
        @empty
            <p class="portal-muted" style="margin:0; text-align:center; padding:1rem 0;">
                Program wycieczki nie jest jeszcze dostępny.
            </p>
        @endforelse
    </section>
</div>
