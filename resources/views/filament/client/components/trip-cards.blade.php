@php
    use App\Filament\Client\Resources\ClientEventResource;
    use App\Services\ClientAccessService;
    use App\Support\ClientPortalMedia;

    $trips = collect($trips ?? []);
    $access = app(ClientAccessService::class);
@endphp

@if($trips->isEmpty())
    <div class="client-portal-section">
        <p class="text-sm text-slate-600">Nie masz jeszcze przypisanych wycieczek.</p>
    </div>
@else
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($trips as $trip)
            @php
                $cover = ClientPortalMedia::coverUrl($trip);
                $dates = ClientPortalMedia::dateRangeLabel($trip);
                $place = $trip->startPlace?->name;
                $archived = $access->isArchived($trip);
                $url = ClientEventResource::getUrl('view', ['record' => $trip], panel: 'portal');
            @endphp
            <a href="{{ $url }}" class="client-portal-card" wire:key="portal-trip-{{ $trip->id }}">
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
                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-600">Archiwum</span>
                        @elseif(filled($trip->status))
                            <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-[11px] font-semibold text-blue-800">{{ $trip->status }}</span>
                        @endif
                        <span class="ml-auto text-sm font-semibold text-[#0663fc]">Otwórz →</span>
                    </div>
                </div>
            </a>
        @endforeach
    </div>
@endif
