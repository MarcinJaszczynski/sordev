@php
    /** @var \App\Models\Vehicle|null $vehicle */
    $vehicle = $this->vehicle;
    $items = $this->photoItems;
    $readOnly = $this->readOnly;
    $maxPhotos = \App\Actions\Events\AttachPilotEventVehiclePhotosAction::MAX_PHOTOS;
@endphp

<div class="portal-card">
    <div class="portal-card-title">
        <p>Autokar — zdjęcia</p>
    </div>

    @if(! $vehicle)
        <p class="portal-muted" style="margin:0;">
            Brak przypisanego pojazdu floty. Biuro wybiera autokar w Operacje → Transport.
        </p>
    @else
        <div class="portal-info-grid" style="margin-bottom:12px;">
            <div>
                <p class="label">Pojazd</p>
                <p class="value">{{ $vehicle->displayLabel() }}</p>
            </div>
            @if(filled($vehicle->manufacture_year))
                <div>
                    <p class="label">Rocznik</p>
                    <p class="value">{{ $vehicle->manufacture_year }}</p>
                </div>
            @endif
        </div>

        <p class="portal-muted" style="margin:0 0 10px;">
            Zrób zdjęcia autokaru na wyjeździe (np. stan, rejestracja). Widoczne też w biurze przy Transporcie.
            Limit: {{ $maxPhotos }} zdjęć na wycieczkę.
        </p>

        @if($items !== [])
            <div class="flex flex-wrap gap-2" style="margin-bottom:12px;">
                @foreach($items as $item)
                    <div class="relative">
                        <a href="{{ $item['url'] }}" target="_blank" rel="noopener" class="block">
                            <img
                                src="{{ $item['url'] }}"
                                alt="Zdjęcie autokaru"
                                class="h-20 w-28 rounded-md border border-[#E5E3DA] object-cover"
                            />
                        </a>
                        @unless($readOnly)
                            <button
                                type="button"
                                wire:click="deletePhoto({{ \Illuminate\Support\Js::from($item['path']) }})"
                                wire:confirm="Usunąć to zdjęcie?"
                                class="absolute -right-1 -top-1 flex h-6 w-6 items-center justify-center rounded-full bg-white text-xs font-bold text-red-600 shadow border border-[#E5E3DA]"
                                title="Usuń"
                            >×</button>
                        @endunless
                    </div>
                @endforeach
            </div>
        @else
            <p class="portal-muted" style="margin:0 0 10px;">Brak zdjęć z tej wycieczki.</p>
        @endif

        @unless($readOnly)
            @if(count($items) < $maxPhotos)
                @include('pilot.partials.photo-upload', [
                    'wireModel' => 'photos',
                    'multiple' => true,
                    'compact' => true,
                ])
            @else
                <p class="portal-muted" style="margin:0;">Osiągnięto limit zdjęć. Usuń stare, aby dodać nowe.</p>
            @endif
        @else
            <p class="portal-muted" style="margin:0;">Podgląd biura — zapis wyłączony.</p>
        @endunless
    @endif
</div>
