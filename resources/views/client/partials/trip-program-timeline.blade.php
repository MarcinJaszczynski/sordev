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
@endphp

<div class="client-portal-program">
    @if(! $hideHero && filled($coverUrl))
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-900 shadow-sm">
            <div class="relative aspect-[16/10] max-h-64 w-full">
                <img src="{{ $coverUrl }}" alt="{{ $event->name }}" class="h-full w-full object-cover object-center opacity-90">
                <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-slate-950/20 to-transparent"></div>
                <div class="absolute bottom-0 left-0 right-0 p-5 text-white">
                    <p class="text-xs uppercase tracking-[0.2em] text-white/70">Program wycieczki</p>
                    <h2 class="mt-1 text-xl font-semibold sm:text-2xl">{{ $event->name }}</h2>
                    @if($event->start_date)
                        <p class="mt-1 text-sm text-white/80">
                            {{ $event->start_date->format('d.m.Y') }}
                            @if($event->end_date)
                                – {{ $event->end_date->format('d.m.Y') }}
                            @endif
                            · {{ $byDay->count() }} {{ $byDay->count() === 1 ? 'dzień' : 'dni' }}
                        </p>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @forelse($byDay->sortKeys() as $day => $dayPoints)
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
        @endphp

        <section class="client-portal-program__day" id="day-{{ $day }}">
            <header class="client-portal-program__day-header">
                <h3 class="client-portal-program__day-title">Dzień {{ $day }}</h3>
                <p class="client-portal-program__day-date">{{ $dayDate->translatedFormat('l, d.m.Y') }}</p>
            </header>

            <ul class="client-portal-program__list">
                @foreach($parents as $point)
                    @php
                        $title = $pointTitle($point);
                        $description = $pointDescription($point);
                        $showBold = $point->show_title_style !== false;
                        $start = (! $hideTimes && ! ($point->hide_times ?? false) && $point->start_time)
                            ? substr((string) $point->start_time, 0, 5)
                            : null;
                        $end = (! $hideTimes && ! ($point->hide_times ?? false) && $point->end_time)
                            ? substr((string) $point->end_time, 0, 5)
                            : null;
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
        </section>

        @if(! $loop->last)
            <hr class="client-portal-program__hr">
        @endif
    @empty
        <p class="py-6 text-center text-sm text-slate-500">
            Program wycieczki nie jest jeszcze dostępny.
        </p>
    @endforelse
</div>
