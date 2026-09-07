@php
    use App\Filament\Pilot\Resources\PilotEventResource;
    use App\Services\PilotAccessService;
    use App\Support\ClientPortalMedia;

    $trips = collect($trips ?? []);
    $access = app(PilotAccessService::class);
@endphp

@if($trips->isEmpty())
    <div class="client-portal-section">
        @if(\App\Http\Middleware\PilotPreviewMiddleware::isActive()
            && ! \App\Http\Middleware\PilotPreviewMiddleware::previewUserId())
            <p class="text-sm text-[#5F5E5A]">
                Podgląd bez wybranego pilota — nie ma tu listy wszystkich imprez biura.
                Wejdź przez <strong>„Podgląd jako ten pilot”</strong> z karty imprezy w panelu admina.
            </p>
        @else
            <p class="text-sm text-[#5F5E5A]">Nie masz jeszcze przypisanych wycieczek.</p>
        @endif
    </div>
@else
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($trips as $trip)
            @php
                $cover = ClientPortalMedia::coverUrl($trip);
                $dates = ClientPortalMedia::dateRangeLabel($trip);
                $place = $trip->startPlace?->name;
                $archived = $access->isArchived($trip);
                $url = PilotEventResource::getUrl('view', ['record' => $trip], panel: 'pilot');
            @endphp
            <a href="{{ $url }}" class="client-portal-card" wire:key="pilot-trip-{{ $trip->id }}">
                <div class="client-portal-card__media">
                    @if(filled($cover))
                        <img src="{{ $cover }}" alt="{{ $trip->name }}" loading="lazy">
                    @else
                        <div class="client-portal-card__media-fallback">BP RAFA</div>
                    @endif
                </div>
                <div class="client-portal-card__body">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900 leading-snug">{{ $trip->name }}</h3>
                        <p class="mt-1 text-sm text-slate-500">
                            @if($dates){{ $dates }}@endif
                            @if($dates && $place) · @endif
                            @if($place){{ $place }}@endif
                        </p>
                    </div>
                    <div class="mt-auto flex flex-wrap items-center gap-2 pt-1">
                        @if($archived)
                            <span class="portal-status-pill" style="background:#F1EFE8;color:#5F5E5A;">Archiwum</span>
                        @elseif(filled($trip->status))
                            <span class="portal-status-pill">{{ $trip->status }}</span>
                        @endif
                        <span class="ml-auto text-sm font-semibold" style="color:#0C447C;">Otwórz →</span>
                    </div>
                </div>
            </a>
        @endforeach
    </div>
@endif
