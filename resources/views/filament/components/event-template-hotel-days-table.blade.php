@php
    $hotelDays = isset($page->hotel_days) ? $page->hotel_days : [];
    $canEdit = method_exists($page, 'canMutateEventTemplateNow')
        ? (bool) $page->canMutateEventTemplateNow()
        : true;
    $activeIndex = (int) ($page->activeHotelNightIndex ?? 0);
    if ($hotelDays !== [] && ! isset($hotelDays[$activeIndex])) {
        $activeIndex = 0;
    }
    $activeDay = $hotelDays[$activeIndex] ?? null;
    $roleLabels = \App\Support\EventParticipantGroupLabels::hotelRoleLabels();
    $previewOptions = $page->previewQtyOptions();
    $structurePreview = $activeDay ? $page->structurePreviewForActiveNight() : ['variant' => null, 'roles' => []];
@endphp

<div class="space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="max-w-3xl">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Plan noclegów szablonu</h3>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Wybierasz <strong>dozwolone typy pokoi</strong> per rola.
                <strong>Ilości liczy automat</strong> (najtańsza kombinacja) osobno dla każdego wariantu liczby osób.
                Na imprezie powstanie gotowa struktura z ilościami.
            </p>
        </div>
        @if ($canEdit && ! empty($hotelDays))
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="saveHotelDays"
                    class="inline-flex items-center rounded-md bg-primary-600 px-3 py-2 text-sm font-semibold text-white hover:bg-primary-500">
                    Zapisz noclegi
                </button>
                <button type="button" wire:click="copyToAllDays({{ $activeIndex }})"
                    class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                    Kopiuj tę noc na wszystkie
                </button>
            </div>
        @endif
    </div>

    @if (empty($hotelDays))
        <div class="rounded-lg border border-dashed border-gray-300 px-4 py-10 text-center text-sm text-gray-500 dark:border-gray-600">
            Brak noclegów — ustaw liczbę dni wycieczki (min. 2) w zakładce „Dane”, potem kliknij „Odśwież z liczby dni”.
        </div>
    @else
        <div class="flex flex-wrap gap-2">
            @foreach ($hotelDays as $i => $day)
                <button
                    type="button"
                    wire:click="selectHotelNight({{ $i }})"
                    @class([
                        'rounded-full px-3 py-1.5 text-sm font-medium transition',
                        'bg-primary-600 text-white' => $i === $activeIndex,
                        'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700' => $i !== $activeIndex,
                    ])
                >
                    Noc {{ $day['day'] ?? ($i + 1) }}
                </button>
            @endforeach
        </div>

        @if ($activeDay)
            <div class="space-y-4" wire:key="hotel-night-panel-{{ $activeIndex }}">
                {{-- 1. Automat struktury — pierwsza kolejność --}}
                <div class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-4 dark:border-emerald-800 dark:bg-emerald-950/30">
                    <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h4 class="text-sm font-semibold text-emerald-900 dark:text-emerald-100">
                                Automat struktury pokoi — Noc {{ $activeDay['day'] ?? ($activeIndex + 1) }}
                            </h4>
                            <p class="mt-0.5 text-xs text-emerald-800/80 dark:text-emerald-200/80">
                                Podgląd: ile × jaki pokój policzy system dla wybranego wariantu osób (na podstawie typów poniżej).
                            </p>
                        </div>
                        @if ($previewOptions !== [])
                            <div class="min-w-[16rem]">
                                <label class="mb-1 block text-xs font-medium text-emerald-900 dark:text-emerald-100">Wariant osób</label>
                                <select
                                    wire:model.live="previewQty"
                                    class="block w-full rounded-lg border-emerald-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-emerald-700 dark:bg-gray-900"
                                >
                                    @foreach ($previewOptions as $qty => $label)
                                        <option value="{{ $qty }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>

                    @if ($previewOptions === [])
                        <p class="text-sm text-amber-800 dark:text-amber-200">
                            Brak wariantów ilości osób — dodaj je w zakładce wariantów qty, żeby zobaczyć ilości pokoi.
                        </p>
                    @elseif (! $structurePreview['variant'])
                        <p class="text-sm text-gray-600">Wybierz wariant osób.</p>
                    @else
                        <div class="grid gap-3 md:grid-cols-2">
                            @foreach ($structurePreview['roles'] as $role => $rolePreview)
                                <div class="rounded-lg border border-emerald-200/80 bg-white p-3 dark:border-emerald-800 dark:bg-gray-900">
                                    <div class="mb-2 flex items-center justify-between gap-2">
                                        <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $rolePreview['label'] }}</span>
                                        <span class="text-xs text-gray-500">{{ $rolePreview['people'] }} os.</span>
                                    </div>
                                    @if ($rolePreview['warning'])
                                        <p class="text-xs font-medium text-amber-700 dark:text-amber-300">{{ $rolePreview['warning'] }}</p>
                                    @elseif ($rolePreview['people'] <= 0)
                                        <p class="text-xs italic text-gray-400">Brak osób w tym wariancie</p>
                                    @elseif (empty($rolePreview['lines']))
                                        <p class="text-xs italic text-gray-400">Brak alokacji</p>
                                    @else
                                        <ul class="space-y-1">
                                            @foreach ($rolePreview['lines'] as $line)
                                                <li class="flex items-center justify-between gap-2 text-sm text-gray-800 dark:text-gray-100">
                                                    <span>
                                                        <strong>{{ $line['quantity'] }}×</strong>
                                                        {{ $line['name'] }}
                                                        <span class="text-xs text-gray-500">({{ $line['people_count'] }} os.)</span>
                                                    </span>
                                                    <span class="text-xs text-gray-500 whitespace-nowrap">
                                                        {{ number_format($line['unit_price'] * $line['quantity'], 0, ',', ' ') }}
                                                        {{ $line['currency'] }}
                                                    </span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- 2. Wybór dozwolonych typów --}}
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h4 class="text-base font-semibold text-gray-950 dark:text-white">
                                Dozwolone typy pokoi — Noc {{ $activeDay['day'] ?? ($activeIndex + 1) }}
                            </h4>
                            <p class="text-xs text-gray-500">Automat użyje tylko wybranych typów przy liczeniu ilości.</p>
                        </div>
                        @if ($canEdit && $activeIndex < count($hotelDays) - 1)
                            <button
                                type="button"
                                wire:click="copyToNextDay({{ $activeIndex }})"
                                class="text-xs font-medium text-blue-700 hover:underline dark:text-blue-400"
                            >
                                Kopiuj typy → następna noc
                            </button>
                        @endif
                    </div>

                    <div class="mb-4">
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Szukaj typu pokoju</label>
                        <input
                            type="search"
                            wire:model.live.debounce.200ms="hotelRoomSearch"
                            @disabled(! $canEdit)
                            placeholder="Np. twin, Biebrza, Ateny…"
                            class="block w-full max-w-md rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:bg-gray-100 disabled:cursor-not-allowed dark:border-gray-600 dark:bg-gray-800"
                        >
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        @foreach ($roleLabels as $role => $label)
                            @php
                                $selectedIds = array_map('strval', $activeDay["hotel_room_ids_{$role}"] ?? []);
                                $results = $canEdit ? $page->hotelRoomSearchResults($activeIndex, $role) : [];
                            @endphp
                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-950/40" wire:key="hotel-role-{{ $activeIndex }}-{{ $role }}">
                                <div class="mb-2 flex items-center justify-between gap-2">
                                    <h5 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $label }}</h5>
                                    <span class="text-xs text-gray-500">{{ count($selectedIds) }} typów</span>
                                </div>

                                <div class="mb-3 flex min-h-[2rem] flex-wrap gap-1.5">
                                    @forelse ($selectedIds as $roomId)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-white px-2.5 py-1 text-xs font-medium text-gray-800 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-100 dark:ring-gray-600">
                                            {{ $page->hotelRoomChipLabel($roomId) }}
                                            @if ($canEdit)
                                                <button
                                                    type="button"
                                                    wire:click="removeRoomFromDay({{ $activeIndex }}, '{{ $role }}', {{ (int) $roomId }})"
                                                    class="ml-0.5 rounded-full text-gray-400 hover:text-red-600"
                                                    title="Usuń"
                                                >×</button>
                                            @endif
                                        </span>
                                    @empty
                                        <span class="text-xs italic text-gray-400">Brak typów — wyszukaj i dodaj</span>
                                    @endforelse
                                </div>

                                @if ($canEdit)
                                    <div class="max-h-40 overflow-y-auto rounded-md border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                                        @if (trim($page->hotelRoomSearch) === '')
                                            <p class="px-3 py-2 text-xs text-gray-400">Zacznij pisać w polu wyszukiwania…</p>
                                        @elseif (empty($results))
                                            <p class="px-3 py-2 text-xs text-gray-400">Brak wyników dla „{{ $page->hotelRoomSearch }}”</p>
                                        @else
                                            <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                                                @foreach ($results as $roomId => $roomName)
                                                    <li>
                                                        <button
                                                            type="button"
                                                            wire:click="addHotelRoomToDay({{ $activeIndex }}, '{{ $role }}', {{ (int) $roomId }})"
                                                            class="flex w-full items-center justify-between px-3 py-2 text-left text-sm text-gray-800 hover:bg-primary-50 dark:text-gray-100 dark:hover:bg-primary-950/40"
                                                        >
                                                            <span>{{ $roomName }}</span>
                                                            <span class="text-xs font-medium text-primary-600">Dodaj typ</span>
                                                        </button>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4">
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Notatka do tej nocy</label>
                        <textarea
                            wire:model.blur="hotel_days.{{ $activeIndex }}.notes"
                            rows="3"
                            @disabled(! $canEdit)
                            class="block w-full rounded-lg border-gray-300 text-sm shadow-sm disabled:cursor-not-allowed disabled:bg-gray-100 dark:border-gray-600 dark:bg-gray-800"
                            placeholder="Uwagi do tej nocy…"
                        ></textarea>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
