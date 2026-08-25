<x-filament-panels::page>
    @include('filament.client.components.trip-hero', [
        'event' => $event,
        'kicker' => 'Hotele',
    ])

    @if (empty($plan))
        <div class="portal-card">
            <p class="portal-muted" style="margin:0;">Brak planu noclegów dla tej imprezy.</p>
        </div>
    @else
        @if ($this->canEditRoomNumbers())
            <div class="portal-notice portal-notice--accent">
                Po zakwaterowaniu wpisz numery pokoi nadane przez hotel — widoczne dla biura i w dokumentach.
            </div>
        @elseif(app(\App\Services\PilotAccessService::class)->isPreviewReadOnly())
            <div class="portal-notice portal-notice--accent">
                Podgląd tylko do odczytu — numery pokoi nie są edytowalne.
            </div>
        @endif

        <form wire:submit.prevent="saveRoomNumbers" class="space-y-3">
            @foreach ($plan as $night)
                @php
                    $sections = [
                        'qty' => 'Pokoje płatne',
                        'gratis' => \App\Support\EventParticipantGroupLabels::GRATIS,
                        'staff' => 'Obsługa',
                        'driver' => 'Kierowca',
                    ];
                @endphp

                <div class="portal-card" wire:key="hotel-night-{{ $night['day'] ?? $loop->index }}">
                    <div class="portal-room-group-header">
                        <p>
                            Noc {{ $night['day'] }} — numery pokoi
                            @if (! empty($night['hotel_name']))
                                <span style="font-weight:400;color:#5F5E5A;">
                                    · {{ $night['hotel_name'] }}
                                    @if(! empty($night['hotel_branch'])) ({{ $night['hotel_branch'] }})@endif
                                </span>
                            @endif
                        </p>
                        <p>
                            @if (! empty($night['hotel_name']))
                                Plan noclegu · wpisz nr pokoju hotelowego przy każdym pokoju
                            @else
                                Brak przypisanego hotelu — uzupełnij w biurze
                            @endif
                        </p>
                    </div>

                    @if (! empty($night['hotel_name']))
                        <div class="mb-3 rounded-lg border border-[#E5E3DA] bg-[#F1EFE8] px-3 py-2">
                            @if (! empty($night['hotel_address']) || ! empty($night['hotel_phone']) || ! empty($night['hotel_email']))
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-[#888780]">Adres / kontakt</p>
                                <x-contractor-contact-meta
                                    :address="$night['hotel_address'] ?? null"
                                    :phone="$night['hotel_phone'] ?? null"
                                    :email="$night['hotel_email'] ?? null"
                                    class="mt-1 text-xs text-[#5F5E5A]"
                                />
                            @else
                                <p class="text-xs text-[#6B5420]">
                                    Brak adresu hotelu — biuro musi przypisać hotel (kontrahenta) w planie noclegów.
                                </p>
                            @endif
                        </div>
                    @else
                        <div class="portal-notice portal-notice--amber !mb-3">
                            Brak przypisanego hotelu na tę noc — uzupełnij w zakładce Hotele (biuro).
                        </div>
                    @endif

                    @if (! empty($night['offer_notes']))
                        <div class="mb-3 rounded-lg border border-[#E5E3DA] bg-[#F5F4EF] p-3 text-sm text-[#2C2C2A]">
                            {!! $night['offer_notes'] !!}
                        </div>
                    @endif

                    @foreach ($sections as $role => $label)
                        @php
                            $lines = $night[$role] ?? [];
                            $units = collect($lines)->flatMap(fn ($line) => $line['units'] ?? [])->values();
                            $roomTypeLabel = trim((string) preg_replace(
                                '/\s*\(\d+\s*\/\s*\d+\)\s*$/u',
                                '',
                                (string) ($units->first()['label'] ?? 'Pokój')
                            ));
                            $peoplePerRoom = (int) ($units->first()['people_count'] ?? 1);
                            $roomCount = $units->count();
                            $mod10 = $roomCount % 10;
                            $mod100 = $roomCount % 100;
                            $roomsWord = $roomCount === 1
                                ? 'pokój'
                                : (($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 10 || $mod100 >= 20)) ? 'pokoje' : 'pokoi');
                        @endphp
                        @if ($units->isNotEmpty())
                            <div class="mb-4 last:mb-0">
                                <div class="portal-room-group-header !mb-2">
                                    <p>{{ $label }}</p>
                                    <p>
                                        {{ $roomTypeLabel !== '' ? $roomTypeLabel : 'Pokój' }}
                                        · {{ $roomCount }} {{ $roomsWord }}
                                        · {{ $peoplePerRoom }} os./pokój
                                    </p>
                                </div>
                                <div class="portal-room-grid">
                                    @foreach ($units as $unitIndex => $unit)
                                        @php
                                            $unitId = $unit['id'] ?? null;
                                            $roomOrdinal = $unitIndex + 1;
                                            $roomLabel = 'pokój '.$roomOrdinal;
                                            $occupantSlots = collect($unit['occupant_slots'] ?? []);
                                            $titleParts = array_filter([
                                                $roomTypeLabel,
                                                $roomLabel,
                                                $occupantSlots->pluck('name')->filter(fn ($n) => filled($n))->implode(', ') ?: null,
                                            ]);
                                        @endphp
                                        <div
                                            class="portal-room-slot"
                                            wire:key="unit-{{ $unitId ?? ($night['day'].'-'.$role.'-'.$unitIndex) }}"
                                            title="{{ implode(' · ', $titleParts) }}"
                                        >
                                            <p class="idx">{{ $roomLabel }}</p>

                                            <div class="portal-room-slot-body">
                                                @foreach ($occupantSlots as $slot)
                                                    @php
                                                        $bedIndex = (int) ($slot['bed_index'] ?? 1);
                                                        $slotKey = filled($unitId) ? "{$unitId}.{$bedIndex}" : null;
                                                        $occupantName = trim((string) ($slot['name'] ?? ''));
                                                        $occupantSource = (string) ($slot['source'] ?? '');
                                                        $isStructured = $occupantName !== '' && $occupantSource !== '' && $occupantSource !== 'manual';
                                                    @endphp
                                                    @if ($isStructured)
                                                        <span class="occupant-name" title="{{ $occupantName }}">
                                                            {{ \App\Support\EventHotelPlanFormatting::displaySurname($occupantName) }}
                                                        </span>
                                                    @elseif ($this->canEditRoomNumbers() && filled($slotKey))
                                                        <input
                                                            type="text"
                                                            wire:model.live.debounce.500ms="occupantNames.{{ $slotKey }}"
                                                            placeholder="nazwisko"
                                                            aria-label="Nazwisko — {{ $roomLabel }}, miejsce {{ $bedIndex }}"
                                                        />
                                                    @elseif ($occupantName !== '')
                                                        <span class="occupant-name" title="{{ $occupantName }}">
                                                            {{ \App\Support\EventHotelPlanFormatting::displaySurname($occupantName) }}
                                                        </span>
                                                    @else
                                                        <span class="occupant-empty">Gość {{ $bedIndex }}</span>
                                                    @endif
                                                @endforeach

                                                @if ($this->canEditRoomNumbers() && ! empty($unitId))
                                                    <input
                                                        type="text"
                                                        wire:model.live.debounce.500ms="roomNumbers.{{ $unitId }}"
                                                        placeholder="np. 214"
                                                        aria-label="Numer pokoju hotelowego — {{ $roomLabel }}"
                                                        class="room-number-input"
                                                    />
                                                @else
                                                    <span class="room-readonly">{{ filled($unit['room_number'] ?? null) ? $unit['room_number'] : '—' }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach

                    <p class="mt-3 text-right text-xs text-[#888780]">
                        Suma nocy: {{ number_format((float) ($night['day_total_pln'] ?? 0), 2, ',', ' ') }} PLN
                    </p>
                </div>
            @endforeach

            @if ($this->canEditRoomNumbers())
                <div class="flex justify-end">
                    <button type="submit" class="portal-btn-primary">
                        Zapisz numery pokoi
                    </button>
                </div>
            @endif
        </form>
    @endif
</x-filament-panels::page>
