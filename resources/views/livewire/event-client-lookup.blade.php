<div class="space-y-4">
    @if ($selected)
        <div
            class="rounded-xl border-2 border-primary-400 bg-primary-50 p-4 shadow-sm dark:border-primary-500/50 dark:bg-primary-950/40"
            wire:key="selected-client-{{ $selected['contractor_id'] ?? $selected['label'] ?? 'x' }}"
        >
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-primary-700 dark:text-primary-300">
                        Wybrany zamawiający
                    </p>
                    <p class="mt-1 text-base font-semibold text-gray-900 dark:text-white">
                        {{ $selected['label'] ?? '—' }}
                    </p>
                    <dl class="mt-3 grid gap-1 text-sm text-gray-600 dark:text-gray-300 sm:grid-cols-2">
                        @foreach (['company' => 'Firma', 'person' => 'Osoba', 'department' => 'Dział', 'phone' => 'Telefon', 'email' => 'E-mail', 'address' => 'Adres'] as $key => $label)
                            @if (! empty($selected['preview'][$key] ?? null))
                                <div>
                                    <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                                    <dd>{{ $selected['preview'][$key] }}</dd>
                                </div>
                            @endif
                        @endforeach
                    </dl>
                    <label class="mt-3 inline-flex items-center gap-2 text-sm text-gray-800 dark:text-gray-100">
                        <input
                            type="radio"
                            name="trip-contact-primary"
                            @checked((bool) ($selected['goes_on_trip'] ?? false))
                            wire:click="setAsTripContact"
                            class="rounded-full border-gray-400 text-primary-600 focus:ring-primary-500"
                        />
                        <span>Jedzie na wyjazd — kontakt dla pilota</span>
                    </label>
                </div>
                <button
                    type="button"
                    wire:click="clearSelection"
                    class="text-xs font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                >
                    Zmień
                </button>
            </div>
        </div>
    @else
        <div
            class="relative"
            x-data="{ open: @entangle('showResults') }"
            @click.outside="open = false"
        >
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                Szukaj zamawiającego w bazie
            </label>
            <div class="relative">
                <input
                    type="search"
                    wire:model.live.debounce.350ms="searchQuery"
                    @focus="if ($wire.results.length) { open = true }"
                    placeholder="Imię, nazwisko, telefon, e-mail, firma, adres…"
                    autocomplete="off"
                    class="fi-input block w-full rounded-lg border-gray-300 py-2.5 pl-10 pr-3 text-sm shadow-sm dark:border-white/10 dark:bg-white/5"
                />
                <x-heroicon-m-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
            </div>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Wpisz min. 3 znaki — wyniki pojawią się automatycznie.</p>
            <label class="mt-2 inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input
                    type="checkbox"
                    wire:model.live="searchAll"
                    class="rounded border-gray-400 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-white/20 dark:bg-white/5"
                />
                Szukaj we wszystkich kontrahentach
            </label>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Domyślnie tylko typ „klient”. Zaznacz, gdy firma ma źle przypisany typ.</p>

            @if ($showResults)
                <div
                    x-show="open"
                    x-cloak
                    class="absolute z-40 mt-1 w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg dark:border-white/10 dark:bg-gray-900"
                >
                    @if ($results !== [])
                        <ul class="max-h-64 divide-y divide-gray-100 overflow-y-auto dark:divide-white/5" role="listbox">
                            @foreach ($results as $index => $result)
                                @php
                                    $preview = $result['preview'] ?? [];
                                    $subtitle = collect([
                                        $preview['phone'] ?? null,
                                        $preview['email'] ?? null,
                                        $preview['company'] ?? null,
                                    ])->filter()->implode(' · ');
                                @endphp
                                <li>
                                    <button
                                        type="button"
                                        wire:click="selectResult({{ $index }})"
                                        class="flex w-full flex-col gap-0.5 px-3 py-2.5 text-left transition hover:bg-primary-50 dark:hover:bg-primary-950/40"
                                        role="option"
                                    >
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $result['label'] ?? '—' }}
                                        </span>
                                        @if ($subtitle !== '')
                                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $subtitle }}</span>
                                        @endif
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                        <div class="border-t border-gray-100 px-3 py-2 dark:border-white/5">
                            <button
                                type="button"
                                wire:click="openQuickCreate"
                                class="text-sm font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400"
                            >
                                Nie ma na liście? Dodaj nowego klienta
                            </button>
                        </div>
                    @else
                        <div class="px-3 py-3 text-sm text-gray-600 dark:text-gray-300">
                            Brak dopasowań dla „{{ $searchQuery }}”.
                        </div>
                        <div class="border-t border-gray-100 px-3 py-2 dark:border-white/5">
                            <button
                                type="button"
                                wire:click="openQuickCreate"
                                class="text-sm font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400"
                            >
                                Dodaj nowego klienta
                            </button>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        @if (! $showQuickCreate)
            <div class="rounded-lg border border-dashed border-primary-300 bg-primary-50/40 px-4 py-3 dark:border-primary-500/40 dark:bg-primary-950/20">
                <p class="text-sm text-gray-700 dark:text-gray-200">
                    Klienta nie ma w bazie?
                </p>
                <div class="mt-2">
                    <x-filament::button type="button" wire:click="openQuickCreate" size="sm" color="primary" outlined>
                        Dodaj nowego klienta
                    </x-filament::button>
                </div>
            </div>
        @endif

        @if ($showQuickCreate)
            <div
                class="rounded-xl border-2 border-primary-300 bg-white p-4 shadow-sm dark:border-primary-500/40 dark:bg-gray-900"
                x-data
                @keydown.enter.prevent="$wire.quickCreate()"
            >
                <p class="mb-1 text-sm font-semibold text-gray-900 dark:text-white">
                    Nowy zamawiający
                </p>
                <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">
                    Uzupełnij dane, potem kliknij „Zapisz klienta i wybierz go” (albo Enter). Dopiero potem zapisuj imprezę.
                </p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Firma / instytucja</label>
                        <input type="text" wire:model="companyName" class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Imię</label>
                        <input type="text" wire:model="firstName" class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Nazwisko</label>
                        <input type="text" wire:model="lastName" class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Telefon <span class="text-gray-500">(wymagany telefon lub e-mail)</span></label>
                        <input type="text" wire:model="phone" class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">E-mail <span class="text-gray-500">(wymagany telefon lub e-mail)</span></label>
                        <input type="email" wire:model="email" class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5" />
                    </div>
                </div>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Wymagany jest telefon lub e-mail. Firma, imię i nazwisko są opcjonalne.</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <x-filament::button type="button" wire:click="quickCreate" size="sm" color="primary">
                        Zapisz klienta i wybierz go
                    </x-filament::button>
                    <x-filament::button type="button" wire:click="$set('showQuickCreate', false)" color="gray" size="sm">
                        Anuluj
                    </x-filament::button>
                </div>
            </div>
        @endif
    @endif
</div>
