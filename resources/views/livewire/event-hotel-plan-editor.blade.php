<div class="space-y-6">
    <div class="sor-sticky-toolbar space-y-3 bg-white/95 dark:bg-gray-900/95">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-gray-900">Hotele — {{ $event->name }}</p>
                <p class="text-xs text-gray-500">
                    {{ count($stays) }} {{ count($stays) === 1 ? 'noc' : 'nocy' }} ·
                    Suma noclegów:
                    <span class="font-semibold text-gray-800">{{ $this->totalDisplay }}</span>
                    @if ($hotelPricingMode === 'flat_stay')
                        <span class="text-amber-700">(cena grupowa za pobyt)</span>
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-filament::button wire:click="save" icon="heroicon-o-check">Zapisz plan</x-filament::button>
                <x-filament::button wire:click="restoreFromTemplate" color="gray" icon="heroicon-o-arrow-path">Przywróć z szablonu</x-filament::button>
            </div>
        </div>

        

        <nav class="flex flex-wrap gap-2" aria-label="Kroki planu noclegów">
            <button
                type="button"
                wire:click="goToStep(1)"
                @class([
                    'rounded-lg border px-4 py-2 text-left text-sm transition',
                    'border-amber-400 bg-amber-50 text-amber-950' => $activeStep === 1,
                    'border-gray-200 bg-white text-gray-700 hover:border-gray-300' => $activeStep !== 1,
                ])
            >
                <span class="font-semibold">Krok 1</span>
                <span class="block text-xs opacity-80">Struktura pokoi (biuro)</span>
            </button>
            <button
                type="button"
                wire:click="goToStep(2)"
                @class([
                    'rounded-lg border px-4 py-2 text-left text-sm transition',
                    'border-amber-400 bg-amber-50 text-amber-950' => $activeStep === 2,
                    'border-gray-200 bg-white text-gray-700 hover:border-gray-300' => $activeStep !== 2,
                    'opacity-60' => ! $this->structureReady,
                ])
            >
                <span class="font-semibold">Krok 2</span>
                <span class="block text-xs opacity-80">Lista osób (1 wiersz = 1 miejsce)</span>
            </button>
        </nav>
    </div>

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
        <div class="grid gap-6 xl:grid-cols-[16rem_minmax(0,1fr)]">
            <div class="space-y-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Noce</p>
                @foreach ($stays as $index => $stay)
                    @php
                        $stayTotal = $formatting::stayTotalPln($stay, $hotelPricingMode, $currencies);
                        $summary = $formatting::staySummary($stay, $hotelRoomsById, $currencies);
                        $isActive = $index === $activeStayIndex;
                    @endphp
                    <button
                        type="button"
                        wire:click="selectStay({{ $index }})"
                        @class([
                            'w-full rounded-lg border px-3 py-2.5 text-left text-sm transition min-h-[var(--admin-touch-min)]',
                            'border-amber-400 bg-amber-50' => $isActive,
                            'border-gray-200 bg-white hover:border-gray-300' => ! $isActive,
                        ])
                    >
                        <div class="font-semibold text-gray-900">
                            Noc {{ $stay['day'] }}
                            
                        </div>
                        <div class="text-xs text-gray-500">{{ $hotelLabels[$stay['contractor_id']] ?? 'Hotel nie wybrany' }}</div>
                        @if ($activeStep === 1)
                            <div class="mt-1 text-[0.65rem] leading-snug text-gray-600">{{ $summary }}</div>
                            <div class="mt-1 text-xs font-semibold text-gray-800">
                                @if ($hotelPricingMode === 'flat_stay')
                                    grupowo
                                @else
                                    {{ $formatting::stayTotalDisplay($stay, $hotelPricingMode, $currencies) }}
                                @endif
                            </div>
                        @else
                            @php
                                $slotCount = $formatting::countPersonSlots($stay, $hotelRoomsById);
                                $namedCount = collect($stay['room_lines'] ?? [])->flatMap(fn ($l) => $l['occupants'] ?? [])
                                    ->filter(fn ($o) => trim((string) ($o['name'] ?? '')) !== '')->count();
                                $remaining = max(0, $slotCount - $namedCount);
                            @endphp
                            <div class="mt-1 text-xs text-gray-500">
                                {{ $slotCount }} {{ $slotCount === 1 ? 'miejsce' : 'miejsc' }}
                                · {{ $namedCount }} wpisane
                                · zostało {{ $remaining }}
                            </div>
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
                                    <label class="text-sm font-medium text-gray-950">Hotel / kontrahent</label>
                                    <input
                                        type="search"
                                        wire:model.live.debounce.300ms="hotelContractorSearch"
                                        placeholder="Wyszukaj hotel po nazwie, mieście, NIP…"
                                        class="block w-full rounded-lg border-gray-300 text-sm shadow-sm"
                                    />
                                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                        <input
                                            type="checkbox"
                                            wire:model.live="hotelContractorSearchAll"
                                            class="rounded border-gray-400"
                                        />
                                        Szukaj we wszystkich kontrahentach
                                    </label>
                                    <select wire:model.live="stays.{{ $activeStayIndex }}.contractor_id" class="block w-full rounded-lg border-gray-300 text-sm shadow-sm">
                                        <option value="">— wybierz hotel —</option>
                                        @foreach ($hotels as $id => $name)
                                            <option value="{{ $id }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                    @if ($showLocationSelect)
                                        <label class="mt-3 block text-sm font-medium text-gray-950">Miejsce prowadzenia działalności</label>
                                        <select wire:model.live="stays.{{ $activeStayIndex }}.contractor_location_id" class="block w-full rounded-lg border-gray-300 text-sm shadow-sm">
                                            <option value="">— wybierz oddział —</option>
                                            @foreach ($locationOptions as $id => $name)
                                                <option value="{{ $id }}">{{ $name }}</option>
                                            @endforeach
                                        </select>
                                        <p class="text-xs text-gray-500">Adres podjazdu dla pilota — nie adres rozliczeniowy sieci hotelowej.</p>
                                    @endif
                                    <p class="text-xs text-gray-500">Hotel zapisuje się automatycznie po wyborze z listy. Domyślnie lista obejmuje kontrahentów typu hotel. Zaznacz opcję powyżej, gdy hotel ma źle przypisany typ.</p>
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
                                    <textarea wire:model.live.debounce.500ms="stays.{{ $activeStayIndex }}.offer_notes" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm" placeholder="Śniadanie, kolacja, parking…"></textarea>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-gray-950">Uwagi do tej nocy</label>
                                    <p class="text-xs text-gray-500">Preferowany hotel, kontakt do rezerwacji, lokalizacja (dla nocy {{ $stay['day'] }}).</p>
                                    <textarea wire:model.live.debounce.500ms="stays.{{ $activeStayIndex }}.notes" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm" placeholder="Np. Hotel XYZ przy rynku, rezerwacja na nazwisko biura…"></textarea>
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
                                    </div>
                                    @if ($hotelPricingMode === 'flat_stay')
                                        <div class="mt-3 flex flex-wrap items-center gap-3">
                                            <input type="number" step="0.01" min="0" wire:model.live.debounce.500ms="hotelFlatStayAmount" class="block w-full max-w-[10rem] rounded-lg border-gray-300 text-sm" placeholder="np. 28500" />
                                            <select wire:model.live="hotelFlatStayCurrencyId" class="rounded-lg border-gray-300 text-sm shadow-sm">
                                                @foreach ($currencies as $id => $symbol)
                                                    <option value="{{ $id }}">{{ $symbol }}</option>
                                                @endforeach
                                            </select>
                                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                                <input type="checkbox" wire:model.live="hotelFlatStayConvertToPln" class="rounded border-gray-400" />
                                                Przelicz na PLN
                                            </label>
                                        </div>
                                        <p class="mt-1 text-xs text-amber-700">Ceny w tabeli pokoi są tylko informacyjne — do kalkulacji liczy się kwota grupowa.</p>
                                    @endif
                                </div>

                                @if ($hotelPricingMode !== 'flat_stay')
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
                                        </div>
                                        @if (($stay['pricing_mode'] ?? 'lines') === 'flat_night')
                                            <div class="mt-3 flex flex-wrap items-center gap-3">
                                                <input type="number" step="0.01" min="0" wire:model.live.debounce.500ms="stays.{{ $activeStayIndex }}.flat_amount" class="block w-full max-w-[10rem] rounded-lg border-gray-300 text-sm" />
                                                <select wire:model.live="stays.{{ $activeStayIndex }}.flat_currency_id" class="rounded-lg border-gray-300 text-sm shadow-sm">
                                                    @foreach ($currencies as $id => $symbol)
                                                        <option value="{{ $id }}">{{ $symbol }}</option>
                                                    @endforeach
                                                </select>
                                                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                                    <input type="checkbox" wire:model.live="stays.{{ $activeStayIndex }}.flat_convert_to_pln" class="rounded border-gray-400" />
                                                    Przelicz na PLN
                                                </label>
                                            </div>
                                            <p class="mt-1 text-xs text-amber-700">Ceny pokoi w tej nocy nie wchodzą do sumy.</p>
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
                                                            <input type="text" wire:model.live.debounce.500ms="stays.{{ $activeStayIndex }}.room_lines.{{ $lineIndex }}.label" placeholder="np. Triple, Double" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" />
                                                        @endif
                                                    </td>
                                                    <td class="px-3 py-2 align-top">
                                                        <input type="number" min="1" wire:model.live.debounce.500ms="stays.{{ $activeStayIndex }}.room_lines.{{ $lineIndex }}.quantity" class="block w-full rounded-lg border-gray-300 text-sm" />
                                                    </td>
                                                    <td class="px-3 py-2 align-top">
                                                        <input type="number" min="1" wire:model.live.debounce.500ms="stays.{{ $activeStayIndex }}.room_lines.{{ $lineIndex }}.people_count" placeholder="{{ $formatting::linePeopleCount($line, $hotelRoomsById) }}" class="block w-full rounded-lg border-gray-300 text-sm" />
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
                                                            <input type="number" step="0.01" min="0" wire:model.live.debounce.500ms="stays.{{ $activeStayIndex }}.room_lines.{{ $lineIndex }}.unit_price" class="block w-full rounded-lg border-gray-300 text-sm" />
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
                                                            @foreach ($currencies as $id => $symbol)
                                                                <option value="{{ $id }}">{{ $symbol }}</option>
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
                                                        <select wire:model.live.debounce.500ms="stays.{{ $activeStayIndex }}.room_lines.{{ $lineIndex }}.role" class="block w-full rounded-lg border-gray-300 text-sm">
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
                                                    @if ($hotelPricingMode === 'flat_stay')
                                                        —
                                                    @elseif (($stay['pricing_mode'] ?? 'lines') === 'flat_night')
                                                        {{ $formatting::stayTotalDisplay($stay, $hotelPricingMode, $currencies) }}
                                                    @else
                                                        {{ $formatting::stayTotalDisplay($stay, $hotelPricingMode, $currencies) }}
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
                                    <select wire:model.live.debounce.500ms="copySourceDay" class="block w-full min-w-[120px] rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 text-sm focus:ring-primary-500 focus:border-primary-500">
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
                                                <input type="checkbox" wire:model.live.debounce.500ms="copyTargetDays" value="{{ $s['day'] }}" class="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-500 dark:bg-gray-700 dark:checked:bg-primary-500" />
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

                        <div class="flex flex-wrap justify-end gap-2">
                            <x-filament::button wire:click="save" color="gray" icon="heroicon-o-check">
                                Zapisz
                            </x-filament::button>
                            <x-filament::button wire:click="saveAndContinue" icon="heroicon-o-arrow-right" :disabled="! $this->structureReady">
                                Zapisz i przejdź do listy osób
                            </x-filament::button>
                        </div>
                    @else
                        <x-filament::section>
                            <x-slot name="heading">Krok 2 — lista miejsc (1 wiersz = 1 osoba)</x-slot>
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
                            @php $slots = $formatting::expandedPersonSlots($stay, $hotelRoomsById); @endphp
                            @if ($slots === [])
                                <p class="text-sm text-gray-500">Brak pokoi — wróć do kroku 1 i uzupełnij strukturę.</p>
                            @else
                                <div class="overflow-x-auto rounded-lg border border-gray-200">
                                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                                            <tr>
                                                <th class="px-3 py-2 w-12">#</th>
                                                <th class="px-3 py-2">Typ pokoju</th>
                                                <th class="px-3 py-2 w-24">Pokój</th>
                                                <th class="px-3 py-2 w-24">Miejsce</th>
                                                <th class="px-3 py-2 min-w-[14rem]">Imię i nazwisko</th>
                                                <th class="px-3 py-2 min-w-[12rem]">Z umowy / rezerwacji</th>
                                                <th class="px-3 py-2 w-16"></th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 bg-white">
                                            @foreach ($slots as $slot)
                                                @php
                                                    $lineIndex = $slot['line_index'];
                                                    $unitIndex = $slot['unit_index'];
                                                    $bedIndex = $slot['bed_index'];
                                                    $occupantName = trim((string) ($slot['occupant']['name'] ?? ''));
                                                @endphp
                                                <tr wire:key="slot-{{ $activeStayIndex }}-{{ $lineIndex }}-{{ $unitIndex }}-{{ $bedIndex }}">
                                                    <td class="px-3 py-2 text-gray-500">{{ $slot['slot_number'] }}</td>
                                                    <td class="px-3 py-2 font-medium text-gray-900">{{ $slot['room_type'] }}</td>
                                                    <td class="px-3 py-2 text-gray-600">
                                                        {{ $slot['total_units'] > 1 ? "{$unitIndex}/{$slot['total_units']}" : '1/1' }}
                                                        @if (! empty($slot['room_number']))
                                                            <span class="block text-[0.65rem] text-gray-400">nr {{ $slot['room_number'] }}</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-3 py-2 text-gray-600">{{ $slot['beds_per_room'] > 1 ? "{$bedIndex}/{$slot['beds_per_room']}" : '1/1' }}</td>
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
                            @endif
                        </x-filament::section>

                        <div class="flex justify-start">
                            <x-filament::button wire:click="goToStep(1)" color="gray" icon="heroicon-o-arrow-left">
                                Wróć do struktury pokoi
                            </x-filament::button>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    @endif
</div>
