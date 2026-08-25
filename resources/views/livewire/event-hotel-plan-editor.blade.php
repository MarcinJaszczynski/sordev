<div class="space-y-4 hotel-plan-editor">
    <div class="hotel-status-bar !mb-0">
        <div class="hotel-status-title">
            <div class="min-w-0">
                <p class="hotel-status-main">Planowanie — {{ $event->name }}</p>
                <p class="hotel-status-sub">
                    {{ count($stays) }} {{ count($stays) === 1 ? 'noc' : 'nocy' }} ·
                    Suma noclegów:
                    <span class="font-semibold">{{ $this->totalDisplay }}</span>
                    @if ($formatting::isEventFlatPricing($hotelPricingMode))
                        <span>(cena grupowa za pobyt)</span>
                    @endif
                </p>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <x-filament::button wire:click="restoreFromTemplate" color="gray" size="sm" icon="heroicon-o-arrow-path">Przywróć z szablonu</x-filament::button>
        </div>
    </div>

        <nav class="hotel-step-nav" aria-label="Kroki planu noclegów">
            <button
                type="button"
                wire:click="goToStep(1)"
                @class(['hotel-step-chip', 'is-active' => $activeStep === 1])
            >
                Krok 1 — struktura pokoi
            </button>
            <button
                type="button"
                wire:click="goToStep(2)"
                @class([
                    'hotel-step-chip',
                    'is-active' => $activeStep === 2,
                    'opacity-60' => ! $this->structureReady,
                ])
            >
                Krok 2 — lista osób
            </button>
        </nav>

        @if (empty($stays))
        <x-filament::section>
            <p class="text-sm text-gray-600 mb-4">Plan hoteli jest obecnie pusty. Jeśli impreza trwa dłużej niż jeden dzień, możesz wygenerować puste noce, lub przywrócić plan z szablonu.</p>
            <div class="flex flex-wrap gap-2">
                @if (($event->duration_days ?? 1) > 1)
                    <x-filament::button wire:click="initializeEmptyPlan">Rozpocznij planowanie ({{ max(1, $event->duration_days - 1) }} nocy)</x-filament::button>
                @endif
                <x-filament::button wire:click="restoreFromTemplate" color="gray">Utwórz plan z szablonu</x-filament::button>
            </div>
        </x-filament::section>
    @else
        <div class="hotel-planning-grid">
            <div class="hotel-night-list">
                @foreach ($stays as $index => $stay)
                    @php
                        $isActive = $index === $activeStayIndex;
                        $hotelLabel = $hotelLabels[$stay['contractor_id']] ?? null;
                        $isFlatStayMode = $formatting::isStayFlatPricing($stay['pricing_mode'] ?? 'lines')
                            || $formatting::isEventFlatPricing($hotelPricingMode);
                        $costLabel = $formatting::isEventFlatPricing($hotelPricingMode)
                            ? $this->totalDisplay
                            : $formatting::stayTotalDisplay($stay, $hotelPricingMode, $currencies, $peoplePerNight);
                        $summary = $isFlatStayMode
                            ? null
                            : $formatting::staySummary($stay, $hotelRoomsById, $currencies);
                    @endphp
                    <button
                        type="button"
                        wire:click="selectStay({{ $index }})"
                        @class([
                            'hotel-night-card',
                            'is-active' => $isActive,
                            'is-empty' => blank($hotelLabel),
                        ])
                    >
                        <p class="hotel-night-card-title">Noc {{ $stay['day'] }}</p>
                        <p class="hotel-night-card-sub">
                            @if ($hotelLabel)
                                {{ $hotelLabel }}
                            @else
                                Hotel nie wybrany
                            @endif
                            @if ($costLabel && $costLabel !== '—')
                                · {{ $costLabel }}
                            @endif
                        </p>
                        @php $hint = $stayContactHints[$index] ?? []; @endphp
                        @if (! empty($hint['phone']) || ! empty($hint['city']) || ! empty($hint['address']))
                            <p class="hotel-night-card-sub">
                                @if (! empty($hint['city']))
                                    {{ $hint['city'] }}
                                @elseif (! empty($hint['address']))
                                    {{ $hint['address'] }}
                                @endif
                                @if (! empty($hint['phone']))
                                    @if (! empty($hint['city']) || ! empty($hint['address'])) · @endif
                                    {{ $hint['phone'] }}
                                @endif
                            </p>
                        @endif
                        @if ($activeStep === 2)
                            @php
                                $slotCount = $formatting::countPersonSlots($stay, $hotelRoomsById);
                                $namedCount = collect($stay['room_lines'] ?? [])->flatMap(fn ($l) => $l['occupants'] ?? [])
                                    ->filter(fn ($o) => trim((string) ($o['name'] ?? '')) !== '')->count();
                                $remaining = max(0, $slotCount - $namedCount);
                            @endphp
                            <p class="hotel-night-card-sub">
                                {{ $slotCount }} {{ $slotCount === 1 ? 'miejsce' : 'miejsc' }}
                                · {{ $namedCount }} wpisane
                                · zostało {{ $remaining }}
                            </p>
                        @elseif ($activeStep === 1 && filled($summary))
                            <p class="hotel-night-card-sub">{{ $summary }}</p>
                        @elseif ($activeStep === 1 && $isFlatStayMode && ($stay['pricing_mode'] ?? '') === 'flat_night_per_person')
                            <p class="hotel-night-card-sub">stała × {{ $peoplePerNight }} os.</p>
                        @elseif ($activeStep === 1 && $isFlatStayMode && $formatting::isStayFlatPricing($stay['pricing_mode'] ?? 'lines'))
                            <p class="hotel-night-card-sub">stała kwota za noc</p>
                        @endif
                    </button>
                @endforeach
            </div>

            @php $stay = $stays[$activeStayIndex] ?? null; @endphp
            @if ($stay)
                <div class="space-y-4">
                    @if ($activeStep === 1)
                        <x-filament::section>
                            <x-slot name="heading">Krok 1 — ile jakich pokoi, po ile (biuro)</x-slot>
            <p class="mb-4 text-sm text-gray-600">
                Uzupełnij ofertę hotelu dla nocy {{ $stay['day'] }}: wybierz hotel, dodaj typy pokoi z ilością, liczbą osób w pokoju i ceną za sztukę
                (np. <strong>5 × triple (3 os.) po 670 zł</strong>, <strong>3 × double (2 os.) po 250 zł</strong>).
            </p>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-3">
                                    @if ($activeContractor)
                                        <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-3 dark:border-gray-700 dark:bg-gray-900/40">
                                            <div class="flex flex-wrap items-start justify-between gap-2">
                                                <div>
                                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Hotel tej nocy</p>
                                                    <p class="mt-0.5 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                        {{ $activeContractor->displayLabel() }}
                                                    </p>
                                                </div>
                                                <div class="flex flex-wrap gap-2">
                                                    <button
                                                        type="button"
                                                        wire:click="clearHotelSelection"
                                                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                                                    >
                                                        Zmień
                                                    </button>
                                                    @if ($contractorEditUrl)
                                                        <a
                                                            href="{{ $contractorEditUrl }}"
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            class="inline-flex items-center rounded-md border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                                                        >
                                                            Edytuj hotel
                                                        </a>
                                                    @endif
                                                    <a
                                                        href="{{ $programUrl }}"
                                                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                                                    >
                                                        Program D{{ $stay['day'] }}
                                                    </a>
                                                </div>
                                            </div>

                                            <x-contractor-contact-details
                                                :contractor="$activeContractor"
                                                :location="$activeLocation"
                                                address-label="Adres / podjazd"
                                                class="mt-2 text-xs text-gray-700 dark:text-gray-300"
                                            />

                                            @if ($linkedProgramPoint)
                                                <p class="mt-2 text-xs text-emerald-800 dark:text-emerald-200">
                                                    Podpięte do punktu programu:
                                                    <span class="font-semibold">{{ $linkedProgramPoint->name ?: ($linkedProgramPoint->templatePoint?->name ?? ('#'.$linkedProgramPoint->id)) }}</span>
                                                    (dzień {{ (int) ($linkedProgramPoint->day ?? $stay['day']) }})
                                                </p>
                                            @else
                                                <p class="mt-2 text-xs text-amber-800 dark:text-amber-200">
                                                    Brak punktu programu z oznaczeniem hotel na dzień {{ $stay['day'] }} — dane kontaktu nie trafią automatycznie do programu/PDF.
                                                </p>
                                            @endif
                                        </div>

                                        @if ($showLocationSelect)
                                            <div>
                                                <label class="block text-sm font-medium text-gray-950">Miejsce prowadzenia działalności</label>
                                                <select wire:model.live="stays.{{ $activeStayIndex }}.contractor_location_id" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800">
                                                    <option value="">— wybierz oddział —</option>
                                                    @foreach ($locationOptions as $id => $name)
                                                        <option value="{{ $id }}">{{ $name }}</option>
                                                    @endforeach
                                                </select>
                                                <p class="mt-1 text-xs text-gray-500">Adres podjazdu dla pilota — nie adres rozliczeniowy sieci hotelowej.</p>
                                            </div>
                                        @endif

                                        <div class="rounded-lg border border-gray-200 bg-white px-3 py-3 dark:border-gray-700 dark:bg-gray-900/40">
                                            <div class="flex flex-wrap items-start justify-between gap-2">
                                                <div>
                                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Rezerwacja hotelu</p>
                                                    <p class="mt-0.5 text-sm text-gray-700 dark:text-gray-200">
                                                        Status wspólny dla
                                                        @if ($hotelReservationDays !== [])
                                                            nocy {{ collect($hotelReservationDays)->map(fn ($d) => 'D'.$d)->implode(', ') }}
                                                        @else
                                                            tego hotelu
                                                        @endif
                                                    </p>
                                                </div>
                                                @if ($hotelReservationId)
                                                    <a
                                                        href="{{ \App\Filament\Resources\EventResource::getUrl('reservations', ['record' => $event]) }}"
                                                        class="text-xs font-medium text-primary-600 hover:text-primary-700"
                                                    >
                                                        Operacje → Rezerwacje
                                                    </a>
                                                @endif
                                            </div>

                                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                                <div class="sm:col-span-2">
                                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Status</label>
                                                    <select wire:model.live="hotelReservationStatus" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800">
                                                        @foreach (\App\Models\Reservation::$statuses as $value => $label)
                                                            <option value="{{ $value }}">{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Potwierdzić do</label>
                                                    <input type="date" wire:model.live="hotelReservationConfirmBy" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800" />
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Potwierdzono</label>
                                                    <input type="date" wire:model.live="hotelReservationConfirmedAt" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800" />
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Zaliczka do</label>
                                                    <input type="date" wire:model.live="hotelReservationDepositDueAt" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800" />
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Zaliczka zapłacona</label>
                                                    <input type="date" wire:model.live="hotelReservationDepositPaidAt" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800" />
                                                </div>
                                                <div class="sm:col-span-2">
                                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Nr potwierdzenia dostawcy</label>
                                                    <input type="text" wire:model.blur="hotelReservationBookingReference" placeholder="np. z maila / vouchera" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800" />
                                                </div>
                                            </div>
                                            <p class="mt-2 text-xs text-gray-500">Status zapisuje się automatycznie. Pełne finanse, wpłaty i rezerwacja — zakładka <strong>Przegląd nocy</strong> → Płatności wg hotelu (drawer).</p>
                                        </div>
                                    @else
                                        <div
                                            class="relative"
                                            x-data="{ open: @entangle('showHotelSearchResults') }"
                                            @click.outside="open = false"
                                        >
                                            <label class="mb-1 block text-sm font-medium text-gray-950">Hotel / kontrahent</label>
                                            <div class="relative">
                                                <input
                                                    type="search"
                                                    wire:model.live.debounce.300ms="hotelContractorSearch"
                                                    @focus="if (Object.keys($wire.hotelSearchResults).length) { open = true }"
                                                    placeholder="Wyszukaj hotel po nazwie, mieście, NIP…"
                                                    autocomplete="off"
                                                    class="block w-full rounded-lg border-gray-300 py-2 pl-9 pr-3 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800"
                                                />
                                                <x-heroicon-m-magnifying-glass class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                                            </div>
                                            <p class="mt-1 text-xs text-gray-500">Wpisz min. {{ \App\Livewire\EventHotelPlanEditor::HOTEL_SEARCH_MIN_LENGTH }} znaki — wyniki pojawią się automatycznie. Hotel zapisuje się po wyborze.</p>
                                            <label class="mt-2 inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                                <input
                                                    type="checkbox"
                                                    wire:model.live="hotelContractorSearchAll"
                                                    class="rounded border-gray-400 text-primary-600 shadow-sm focus:ring-primary-500"
                                                />
                                                Szukaj we wszystkich kontrahentach
                                            </label>
                                            <p class="mt-1 text-xs text-gray-500">Domyślnie tylko typ hotel. Zaznacz, gdy hotel ma źle przypisany typ.</p>

                                            @if ($showHotelSearchResults)
                                                <div
                                                    x-show="open"
                                                    x-cloak
                                                    class="absolute z-40 mt-1 w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-900"
                                                >
                                                    @if ($hotelSearchResults !== [])
                                                        <ul class="max-h-64 divide-y divide-gray-100 overflow-y-auto dark:divide-gray-800" role="listbox">
                                                            @foreach ($hotelSearchResults as $hotelId => $hotelLabel)
                                                                <li>
                                                                    <button
                                                                        type="button"
                                                                        wire:click="selectHotel({{ (int) $hotelId }})"
                                                                        class="flex w-full px-3 py-2.5 text-left text-sm text-gray-900 transition hover:bg-primary-50 dark:text-gray-100 dark:hover:bg-primary-950/40"
                                                                        role="option"
                                                                    >
                                                                        {{ $hotelLabel }}
                                                                    </button>
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    @else
                                                        <div class="px-3 py-3 text-sm text-gray-600 dark:text-gray-300">
                                                            Brak dopasowań dla „{{ $hotelContractorSearch }}”.
                                                        </div>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                                <div class="flex flex-wrap items-end gap-2">
                                    <x-filament::button wire:click="applySameHotelEverywhere" color="gray" size="sm" icon="heroicon-o-building-office-2">
                                        Ten sam hotel na wszystkie noce
                                    </x-filament::button>
                                    <x-filament::button wire:click="copyToAllNights" color="gray" size="sm" icon="heroicon-o-document-duplicate">
                                        Kopiuj strukturę na wszystkie noce
                                    </x-filament::button>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="text-sm font-medium text-gray-950">Co hotel oferuje w cenie</label>
                                    <textarea wire:model.blur="stays.{{ $activeStayIndex }}.offer_notes" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm" placeholder="Śniadanie, kolacja, parking…"></textarea>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-gray-950">Uwagi do tej nocy</label>
                                    <p class="text-xs text-gray-500">Preferowany hotel, kontakt do rezerwacji, lokalizacja (dla nocy {{ $stay['day'] }}).</p>
                                    <textarea wire:model.blur="stays.{{ $activeStayIndex }}.notes" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm" placeholder="Np. Hotel XYZ przy rynku, rezerwacja na nazwisko biura…"></textarea>
                                </div>
                            </div>
                        </x-filament::section>

                        <x-filament::section heading="Cennik noclegów">
                            <div class="space-y-4">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Cała impreza</p>
                                    <div class="mt-2 flex flex-wrap gap-4">
                                        <label class="inline-flex items-center gap-2 text-sm">
                                            <input type="radio" wire:model.live="hotelPricingMode" value="lines" class="rounded border-gray-300" />
                                            Z linii pokoi (ilość × cena)
                                        </label>
                                        <label class="inline-flex items-center gap-2 text-sm">
                                            <input type="radio" wire:model.live="hotelPricingMode" value="flat_stay" class="rounded border-gray-300" />
                                            Stała kwota za cały pobyt
                                        </label>
                                        <label class="inline-flex items-center gap-2 text-sm">
                                            <input type="radio" wire:model.live="hotelPricingMode" value="flat_stay_per_person" class="rounded border-gray-300" />
                                            Stała kwota za pobyt za osobę
                                        </label>
                                    </div>
                                    @if ($formatting::isEventFlatPricing($hotelPricingMode))
                                        <div class="mt-3 flex flex-wrap items-center gap-3">
                                            <input type="number" step="0.01" min="0" wire:model.blur="hotelFlatStayAmount" class="block w-full max-w-[10rem] rounded-lg border-gray-300 text-sm" placeholder="np. 28500" />
                                            <select wire:model.live="hotelFlatStayCurrencyId" class="rounded-lg border-gray-300 text-sm shadow-sm">
                                                @foreach ($currencyOptions as $id => $label)
                                                    <option value="{{ $id }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                                <input type="checkbox" wire:model.live="hotelFlatStayConvertToPln" class="rounded border-gray-400" />
                                                Przelicz na PLN
                                            </label>
                                        </div>
                                        @if ($hotelPricingMode === 'flat_stay_per_person')
                                            <p class="mt-1 text-xs text-amber-700">
                                                Stawka × {{ $peoplePerNight }} osób (potrzebne miejsca na noc) = {{ $this->totalDisplay }}.
                                                Ceny w tabeli pokoi są tylko informacyjne.
                                            </p>
                                        @else
                                            <p class="mt-1 text-xs text-amber-700">Ceny w tabeli pokoi są tylko informacyjne — do kalkulacji liczy się kwota grupowa.</p>
                                        @endif
                                    @endif
                                </div>

                                @if (! $formatting::isEventFlatPricing($hotelPricingMode))
                                    <div class="rounded-lg border border-gray-200 p-3">
                                        <p class="text-sm font-medium text-gray-900">Noc {{ $stay['day'] }}</p>
                                        <div class="mt-2 flex flex-wrap gap-4">
                                            <label class="inline-flex items-center gap-2 text-sm">
                                                <input type="radio" wire:model.live="stays.{{ $activeStayIndex }}.pricing_mode" value="lines" class="rounded border-gray-300" />
                                                Z linii pokoi
                                            </label>
                                            <label class="inline-flex items-center gap-2 text-sm">
                                                <input type="radio" wire:model.live="stays.{{ $activeStayIndex }}.pricing_mode" value="flat_night" class="rounded border-gray-300" />
                                                Stała kwota za tę noc
                                            </label>
                                            <label class="inline-flex items-center gap-2 text-sm">
                                                <input type="radio" wire:model.live="stays.{{ $activeStayIndex }}.pricing_mode" value="flat_night_per_person" class="rounded border-gray-300" />
                                                Stała kwota za noc za osobę
                                            </label>
                                        </div>
                                        @if ($formatting::isStayFlatPricing($stay['pricing_mode'] ?? 'lines'))
                                            <div class="mt-3 flex flex-wrap items-center gap-3">
                                                <input type="number" step="0.01" min="0" wire:model.blur="stays.{{ $activeStayIndex }}.flat_amount" class="block w-full max-w-[10rem] rounded-lg border-gray-300 text-sm" />
                                                <select wire:model.live="stays.{{ $activeStayIndex }}.flat_currency_id" class="rounded-lg border-gray-300 text-sm shadow-sm">
                                                    @foreach ($currencyOptions as $id => $label)
                                                        <option value="{{ $id }}">{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                                    <input type="checkbox" wire:model.live="stays.{{ $activeStayIndex }}.flat_convert_to_pln" class="rounded border-gray-400" />
                                                    Przelicz na PLN
                                                </label>
                                            </div>
                                            @if (($stay['pricing_mode'] ?? '') === 'flat_night_per_person')
                                                <p class="mt-1 text-xs text-amber-700">
                                                    Stawka × {{ $peoplePerNight }} osób =
                                                    {{ $formatting::stayTotalDisplay($stay, $hotelPricingMode, $currencies, $peoplePerNight) }}.
                                                    Ceny pokoi w tej nocy nie wchodzą do sumy.
                                                </p>
                                            @else
                                                <p class="mt-1 text-xs text-amber-700">Ceny pokoi w tej nocy nie wchodzą do sumy.</p>
                                            @endif
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </x-filament::section>

                        <x-filament::section heading="Struktura pokoi — noc {{ $stay['day'] }}">
                            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                <p class="text-sm font-medium text-gray-800">{{ $formatting::staySummary($stay, $hotelRoomsById, $currencies) }}</p>
                                <x-filament::button wire:click="addRoomLine" size="sm" icon="heroicon-o-plus">Dodaj typ pokoju</x-filament::button>
                            </div>

                            @if (! empty($stay['room_lines']))
                                <div class="overflow-x-auto rounded-lg border border-gray-200">
                                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                                            <tr>
                                                <th class="px-3 py-2">Typ pokoju</th>
                                                <th class="px-3 py-2 w-24">Ilość</th>
                                                <th class="px-3 py-2 w-24">Osób/pokój</th>
                                                <th class="px-3 py-2 w-28">Cena za</th>
                                                <th class="px-3 py-2 w-32">Cena</th>
                                                <th class="px-3 py-2 w-24">Waluta</th>
                                                <th class="px-3 py-2 w-28">PLN</th>
                                                <th class="px-3 py-2 w-32">Suma</th>
                                                <th class="px-3 py-2 w-32">Rola</th>
                                                <th class="px-3 py-2 w-20"></th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 bg-white">
                                            @foreach ($stay['room_lines'] as $lineIndex => $line)
                                                <tr wire:key="struct-{{ $activeStayIndex }}-{{ $lineIndex }}">
                                                    <td class="px-3 py-2 align-top">
                                                        <select wire:model.live="stays.{{ $activeStayIndex }}.room_lines.{{ $lineIndex }}.hotel_room_id" class="block w-full min-w-[10rem] rounded-lg border-gray-300 text-sm">
                                                            <option value="">— własna nazwa —</option>
                                                            @foreach ($hotelRooms as $room)
                                                                <option value="{{ $room->id }}">{{ $room->name }}</option>
                                                            @endforeach
                                                        </select>
                                                        @if (empty($line['hotel_room_id']))
                                                            <input type="text" wire:model.blur="stays.{{ $activeStayIndex }}.room_lines.{{ $lineIndex }}.label" placeholder="np. Triple, Double" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" />
                                                        @endif
                                                    </td>
                                                    <td class="px-3 py-2 align-top">
                                                        <input type="number" min="1" wire:model.blur="stays.{{ $activeStayIndex }}.room_lines.{{ $lineIndex }}.quantity" class="block w-full rounded-lg border-gray-300 text-sm" />
                                                    </td>
                                                    <td class="px-3 py-2 align-top">
                                                        <input type="number" min="1" wire:model.blur="stays.{{ $activeStayIndex }}.room_lines.{{ $lineIndex }}.people_count" placeholder="{{ $formatting::linePeopleCount($line, $hotelRoomsById) }}" class="block w-full rounded-lg border-gray-300 text-sm" />
                                                        <p class="mt-0.5 text-[0.65rem] text-gray-500">Miejsca w pokoju</p>
                                                    </td>
                                                    <td class="px-3 py-2 align-top @if (! $this->activeStayUsesLinePricing) opacity-50 @endif">
                                                        <select wire:model.live="stays.{{ $activeStayIndex }}.room_lines.{{ $lineIndex }}.price_basis" class="block w-full rounded-lg border-gray-300 text-sm">
                                                            @foreach ($priceBasisOptions as $basisKey => $basisLabel)
                                                                <option value="{{ $basisKey }}">{{ $basisLabel }}</option>
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                    <td class="px-3 py-2 align-top">
                                                        <div class="flex items-center gap-1 @if (! $this->activeStayUsesLinePricing) opacity-50 @endif">
                                                            <input type="number" step="0.01" min="0" wire:model.blur="stays.{{ $activeStayIndex }}.room_lines.{{ $lineIndex }}.unit_price" class="block w-full rounded-lg border-gray-300 text-sm" />
                                                        </div>
                                                        <p class="mt-0.5 text-[0.65rem] text-gray-500">
                                                            @if (($line['price_basis'] ?? 'per_room') === 'per_person')
                                                                za osobę
                                                            @else
                                                                za pokój
                                                            @endif
                                                        </p>
                                                        @if (! $this->activeStayUsesLinePricing)
                                                            <p class="mt-0.5 text-[0.65rem] text-amber-700">nie liczy się</p>
                                                        @endif
                                                    </td>
                                                    <td class="px-3 py-2 align-top @if (! $this->activeStayUsesLinePricing) opacity-50 @endif">
                                                        <select wire:model.live="stays.{{ $activeStayIndex }}.room_lines.{{ $lineIndex }}.currency_id" class="block w-full rounded-lg border-gray-300 text-sm">
                                                            @foreach ($currencyOptions as $id => $label)
                                                                <option value="{{ $id }}">{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                    <td class="px-3 py-2 align-top @if (! $this->activeStayUsesLinePricing) opacity-50 @endif">
                                                        <label class="inline-flex items-center gap-1 text-xs text-gray-700">
                                                            <input type="checkbox" wire:model.live="stays.{{ $activeStayIndex }}.room_lines.{{ $lineIndex }}.convert_to_pln" class="rounded border-gray-400" />
                                                            Tak
                                                        </label>
                                                    </td>
                                                    <td class="px-3 py-2 align-top font-semibold text-gray-900 @if (! $this->activeStayUsesLinePricing) text-gray-400 @endif">
                                                        @if ($this->activeStayUsesLinePricing)
                                                            {{ $formatting::lineTotalDisplay($line, $currencies, $hotelRoomsById) }}
                                                        @else
                                                            —
                                                        @endif
                                                    </td>
                                                    <td class="px-3 py-2 align-top">
                                                        <select wire:model.live="stays.{{ $activeStayIndex }}.room_lines.{{ $lineIndex }}.role" class="block w-full rounded-lg border-gray-300 text-sm">
                                                            @foreach ($roles as $roleKey => $roleLabel)
                                                                <option value="{{ $roleKey }}">{{ $roleLabel }}</option>
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                    <td class="px-3 py-2 align-top">
                                                        <button type="button" wire:click="removeRoomLine({{ $lineIndex }})" class="text-xs text-red-600 hover:underline">Usuń</button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot class="bg-gray-50">
                                            <tr>
                                                <td colspan="6" class="px-3 py-2 text-right text-xs font-semibold uppercase text-gray-500">Razem noc {{ $stay['day'] }}</td>
                                                <td class="px-3 py-2 font-bold text-gray-900">
                                                    @if ($formatting::isEventFlatPricing($hotelPricingMode))
                                                        —
                                                    @else
                                                        {{ $formatting::stayTotalDisplay($stay, $hotelPricingMode, $currencies, $peoplePerNight) }}
                                                    @endif
                                                </td>
                                                <td colspan="2"></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            @else
                                <p class="text-sm text-gray-500">Brak pokoi — dodaj pierwszy typ lub skopiuj strukturę z innej nocy.</p>
                            @endif
                        </x-filament::section>

                        <x-filament::section heading="Szybkie kopiowanie ułożonej struktury" compact>
                            <p class="text-sm text-gray-600 mb-4">Jeśli ułożyłeś pokoje dla konkretnej nocy, możesz je łatwo skopiować na pozostałe, by nie robić tego ręcznie.</p>
                            <div class="flex flex-col sm:flex-row sm:items-start gap-4 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1.5">Źródło</label>
                                    <select wire:model.live="copySourceDay" class="block w-full min-w-[120px] rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 text-sm focus:ring-primary-500 focus:border-primary-500">
                                        @foreach ($stays as $s)
                                            <option value="{{ $s['day'] }}">Noc {{ $s['day'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="flex flex-col justify-center mt-6 text-gray-400 hidden sm:flex">
                                    <x-heroicon-m-arrow-right class="w-5 h-5" />
                                </div>
                                <div class="flex-1">
                                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1.5">Skopiuj na wybrane noce</label>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($stays as $s)
                                            <label class="inline-flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer shadow-sm transition">
                                                <input type="checkbox" wire:model.live="copyTargetDays" value="{{ $s['day'] }}" class="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-500 dark:bg-gray-700 dark:checked:bg-primary-500" />
                                                Noc {{ $s['day'] }}
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="mt-6 sm:mt-0 self-end">
                                    <x-filament::button wire:click="copyToSelectedDays" color="primary" icon="heroicon-o-document-duplicate">Wykonaj kopiowanie</x-filament::button>
                                </div>
                            </div>
                        </x-filament::section>
                    @else
                        <x-filament::section>
                            <x-slot name="heading">Krok 2 — lista miejsc wg pokoi</x-slot>
                            @php
                                $slotCount = $formatting::countPersonSlots($stay, $hotelRoomsById);
                                $namedCount = collect($stay['room_lines'] ?? [])->flatMap(fn ($l) => $l['occupants'] ?? [])
                                    ->filter(fn ($o) => trim((string) ($o['name'] ?? '')) !== '')->count();
                                $remaining = max(0, $slotCount - $namedCount);
                            @endphp
                            <p class="text-sm text-gray-600">
                                
                            </p>
                            <div class="mt-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">
                                <span class="font-semibold">Miejsca:</span> {{ $slotCount }}
                                <span class="mx-2 text-gray-300">|</span>
                                <span class="font-semibold">Wpisane:</span> {{ $namedCount }}
                                <span class="mx-2 text-gray-300">|</span>
                                <span class="font-semibold">Zostało:</span> {{ $remaining }}
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <x-filament::button wire:click="copyOccupantsToAllNights" color="gray" size="sm" icon="heroicon-o-document-duplicate">
                                    Kopiuj listę osób z tej nocy na wszystkie
                                </x-filament::button>
                            </div>
                        </x-filament::section>

                        <x-filament::section heading="Import z pliku" compact>
                            <p class="mb-3 text-sm text-gray-600">
                                Szablon ma po jednym wierszu na każde miejsce (10 pokoi 3-osobowych = 30 wierszy).
                                Pobierz plik, wypełnij kolumnę <strong>Imię i nazwisko</strong> i zaimportuj.
                            </p>

                            <div class="mb-4 flex flex-wrap gap-2">
                                <x-filament::button
                                    tag="a"
                                    href="{{ route('admin.events.hotel-occupants.import-template', ['event' => $event->id, 'format' => 'xlsx']) }}"
                                    color="gray"
                                    icon="heroicon-o-arrow-down-tray"
                                >
                                    Pobierz szablon Excel (.xlsx)
                                </x-filament::button>
                                <x-filament::button
                                    tag="a"
                                    href="{{ route('admin.events.hotel-occupants.import-template', ['event' => $event->id, 'format' => 'csv']) }}"
                                    color="gray"
                                    icon="heroicon-o-arrow-down-tray"
                                >
                                    Pobierz szablon CSV
                                </x-filament::button>
                            </div>

                            @if (! $this->structureReady)
                                <p class="text-sm text-amber-700">Aby importować listę, najpierw uzupełnij i zapisz strukturę pokoi w kroku 1.</p>
                            @else
                                <div class="flex flex-wrap items-end gap-3">
                                    <div class="min-w-[14rem] flex-1">
                                        <label class="mb-1 block text-xs font-medium text-gray-700">Plik Excel lub CSV</label>
                                        <input type="file" wire:model.live.debounce.500ms="importFile" accept=".xlsx,.xls,.csv,text/csv" class="block w-full text-sm text-gray-600" />
                                        @error('importFile') <p class="mt-1 text-xs text-danger-600">{{ $message }}</p> @enderror
                                    </div>
                                    <x-filament::button wire:click="importOccupants" color="primary" icon="heroicon-o-arrow-up-tray" wire:loading.attr="disabled">
                                        Importuj listę
                                    </x-filament::button>
                                </div>
                            @endif
                        </x-filament::section>

                        <x-filament::section heading="Lista osób — noc {{ $stay['day'] }}">
                            @php
                                $slots = $formatting::expandedPersonSlots($stay, $hotelRoomsById);
                                $groupedSlots = collect($slots)->groupBy(
                                    fn (array $slot): string => $slot['line_index'].'-'.$slot['unit_index']
                                );
                            @endphp
                            @if ($slots === [])
                                <p class="text-sm text-gray-500">Brak pokoi — wróć do kroku 1 i uzupełnij strukturę.</p>
                            @else
                                <div class="space-y-4">
                                    @foreach ($groupedSlots as $roomSlots)
                                        @php
                                            $roomHeader = $roomSlots->first();
                                            $roomOccupants = $roomSlots->filter(
                                                fn (array $slot): bool => trim((string) ($slot['occupant']['name'] ?? '')) !== ''
                                            )->count();
                                        @endphp
                                        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 bg-gray-50 px-3 py-2">
                                                <div>
                                                    <p class="text-sm font-semibold text-gray-900">{{ $roomHeader['room_label'] }}</p>
                                                    @if (! empty($roomHeader['room_number']))
                                                        <p class="text-xs text-gray-500">Nr pokoju: {{ $roomHeader['room_number'] }}</p>
                                                    @endif
                                                </div>
                                                <p class="text-xs text-gray-500">
                                                    {{ $roomOccupants }}/{{ $roomSlots->count() }} miejsc zajętych
                                                </p>
                                            </div>
                                            <div class="overflow-x-auto">
                                                <table class="min-w-full divide-y divide-gray-100 text-sm">
                                                    <thead class="bg-white text-left text-xs uppercase tracking-wide text-gray-500">
                                                        <tr>
                                                            <th class="px-3 py-2 w-28">Miejsce</th>
                                                            <th class="px-3 py-2 min-w-[14rem]">Imię i nazwisko</th>
                                                            <th class="px-3 py-2 min-w-[12rem]">Z umowy / rezerwacji</th>
                                                            <th class="px-3 py-2 w-16"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-gray-100">
                                                        @foreach ($roomSlots as $slot)
                                                            @php
                                                                $lineIndex = $slot['line_index'];
                                                                $unitIndex = $slot['unit_index'];
                                                                $bedIndex = $slot['bed_index'];
                                                                $occupantName = trim((string) ($slot['occupant']['name'] ?? ''));
                                                            @endphp
                                                            <tr wire:key="slot-{{ $activeStayIndex }}-{{ $lineIndex }}-{{ $unitIndex }}-{{ $bedIndex }}">
                                                                <td class="px-3 py-2 text-gray-600">
                                                                    <span class="block font-medium text-gray-800">{{ $slot['bed_label'] }}</span>
                                                                    <span class="text-[0.65rem] text-gray-400">#{{ $slot['slot_number'] }}</span>
                                                                </td>
                                                                <td class="px-3 py-2">
                                                                    <input
                                                                        type="text"
                                                                        value="{{ $occupantName }}"
                                                                        placeholder="Imię i nazwisko"
                                                                        class="block w-full rounded-lg border-gray-300 text-sm"
                                                                        wire:change="updateSlotOccupant({{ $lineIndex }}, {{ $unitIndex }}, {{ $bedIndex }}, $event.target.value)"
                                                                    />
                                                                </td>
                                                                <td class="px-3 py-2">
                                                                    <select
                                                                        class="block w-full rounded-lg border-gray-300 text-sm"
                                                                        onchange="if (this.value) { @this.assignSlotParticipant({{ $lineIndex }}, {{ $unitIndex }}, {{ $bedIndex }}, this.value); this.value=''; }"
                                                                    >
                                                                        <option value="">— wybierz —</option>
                                                                        @foreach ($this->participantsAvailableForSlot($participants, $activeStayIndex, $lineIndex, $unitIndex, $bedIndex) as $participant)
                                                                            <option value="{{ $participant['key'] }}">{{ $participant['label'] }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </td>
                                                                <td class="px-3 py-2">
                                                                    @if ($occupantName !== '')
                                                                        <button type="button" wire:click="clearSlotOccupant({{ $lineIndex }}, {{ $unitIndex }}, {{ $bedIndex }})" class="text-xs text-red-600 hover:underline">Wyczyść</button>
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </x-filament::section>
                    @endif
                </div>
            @endif
        </div>

        <div class="hotel-sticky-actions">
            @if ($activeStep === 1)
                <p class="text-sm text-gray-600">Krok 1 — struktura pokoi</p>
                <div class="hotel-sticky-actions-primary">
                    <x-filament::button wire:click="save" color="gray" icon="heroicon-o-check">
                        Zapisz
                    </x-filament::button>
                    <x-filament::button wire:click="saveAndContinue" icon="heroicon-o-arrow-right" :disabled="! $this->structureReady">
                        Zapisz i przejdź do listy osób
                    </x-filament::button>
                </div>
            @else
                <x-filament::button wire:click="goToStep(1)" color="gray" icon="heroicon-o-arrow-left">
                    Wróć do struktury pokoi
                </x-filament::button>
                <div class="hotel-sticky-actions-primary">
                    <x-filament::button wire:click="save" icon="heroicon-o-check">
                        Zapisz
                    </x-filament::button>
                </div>
            @endif
        </div>
    @endif
</div>
