@php
    use App\Services\EventProgramPointOrderService;

    $service = app(EventProgramPointOrderService::class);
    $points = $service->pilotProgramPoints($event);
    $baseDate = $event->start_date?->copy()->startOfDay() ?? now()->startOfDay();
    $byDay = $points->groupBy(fn ($point) => max(1, (int) ($point->day ?? 1)));
@endphp

<div class="space-y-4">
    @forelse($byDay->sortKeys() as $day => $dayPoints)
        @php
            $dayDate = $baseDate->copy()->addDays($day - 1);
        @endphp
        <section class="sor-lw-card overflow-hidden !p-0">
            <header class="border-b border-gray-100 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800">
                <h3 class="sor-lw-title">Dzień {{ $day }}</h3>
                <p class="sor-lw-muted">{{ $dayDate->format('d.m.Y (l)') }}</p>
            </header>
            <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach($dayPoints as $point)
                    @php
                        $name = $point->templatePoint->name ?? $point->name ?? ('Punkt #'.$point->id);
                        $start = $point->start_time ? substr((string) $point->start_time, 0, 5) : null;
                        $end = $point->end_time ? substr((string) $point->end_time, 0, 5) : null;
                        $description = $point->resolvedDescription();
                        $isSetChild = filled($point->parent_id);
                    @endphp
                    <li @class(['px-4 py-3', 'pl-8' => $isSetChild])>
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    @if($isSetChild)
                                        <span class="mr-1 text-gray-400" aria-hidden="true">↳</span>
                                    @endif
                                    @if($point->is_transport) 🚌 @elseif($point->is_hotel) 🏨 @endif
                                    {{ $name }}
                                </p>
                                @if($start || $end)
                                    <p class="sor-lw-muted text-xs">
                                        {{ $start }}{{ $start && $end ? ' – ' : '' }}{{ $end }}
                                    </p>
                                @endif
                                @if(filled($description))
                                    <div class="prose prose-sm mt-2 max-w-none text-gray-600 dark:prose-invert">{!! $description !!}</div>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <div class="sor-lw-card text-sm text-gray-500">Program wycieczki nie jest jeszcze dostępny.</div>
    @endforelse
</div>
