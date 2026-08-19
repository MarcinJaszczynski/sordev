<div class="space-y-3">
    @if ($additional !== [])
        <div class="space-y-3">
            @foreach ($additional as $index => $row)
                @if ($editingIndex === $index)
                    @continue
                @endif
                <div class="rounded-xl border border-primary-200 bg-primary-50/60 p-4 dark:border-primary-500/30 dark:bg-primary-950/30">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-semibold uppercase tracking-wide text-primary-700 dark:text-primary-300">
                                Dodatkowy kontakt
                            </p>
                            <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $row['label'] ?? '—' }}
                            </p>
                            <dl class="mt-3 grid gap-1 text-sm text-gray-600 dark:text-gray-300 sm:grid-cols-2">
                                @foreach (['company' => 'Firma', 'person' => 'Osoba', 'department' => 'Dział', 'phone' => 'Telefon', 'email' => 'E-mail', 'address' => 'Adres'] as $key => $label)
                                    @if (! empty($row['preview'][$key] ?? null))
                                        <div>
                                            <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                                            <dd>{{ $row['preview'][$key] }}</dd>
                                        </div>
                                    @endif
                                @endforeach
                            </dl>
                            <div class="mt-3">
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">
                                    Rola / notatka
                                </label>
                                <input
                                    type="text"
                                    value="{{ $row['notes'] ?? '' }}"
                                    wire:change="updateNotes({{ $index }}, $event.target.value)"
                                    placeholder="np. rodzic odpowiedzialny za rozliczenie"
                                    class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5"
                                />
                            </div>
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-2">
                            <button
                                type="button"
                                wire:click="changeAdditional({{ $index }})"
                                class="text-xs font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                            >
                                Zmień
                            </button>
                            <button
                                type="button"
                                wire:click="removeAdditional({{ $index }})"
                                class="text-xs font-medium text-gray-500 hover:text-danger-600 dark:text-gray-400 dark:hover:text-danger-400"
                            >
                                Usuń
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if (! $showAddPanel)
        <button
            type="button"
            wire:click="openAddPanel"
            class="text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
        >
            Dodaj dodatkowy kontakt
        </button>
    @else
        <div class="rounded-xl border border-dashed border-gray-300 p-4 dark:border-white/10">
            <div class="mb-3 flex items-center justify-between gap-2">
                <p class="text-sm font-medium text-gray-800 dark:text-gray-100">
                    {{ $editingIndex !== null ? 'Zmień dodatkowy kontakt' : 'Nowy dodatkowy kontakt' }}
                </p>
                <button
                    type="button"
                    wire:click="closeAddPanel"
                    class="text-xs font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400"
                >
                    Anuluj
                </button>
            </div>

            @if ($companyContacts !== [])
                <div class="mb-4">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Kontakty firmy głównego zamawiającego
                    </p>
                    <ul class="max-h-48 divide-y divide-gray-100 overflow-y-auto rounded-lg border border-gray-200 dark:divide-white/5 dark:border-white/10">
                        @foreach ($companyContacts as $index => $contactRow)
                            @php
                                $preview = $contactRow['preview'] ?? [];
                                $subtitle = collect([
                                    $preview['phone'] ?? null,
                                    $preview['email'] ?? null,
                                ])->filter()->implode(' · ');
                            @endphp
                            <li>
                                <button
                                    type="button"
                                    wire:click="selectCompanyContact({{ $index }})"
                                    class="flex w-full flex-col gap-0.5 px-3 py-2.5 text-left transition hover:bg-primary-50 dark:hover:bg-primary-950/40"
                                >
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $preview['person'] ?? ($contactRow['label'] ?? '—') }}
                                    </span>
                                    @if ($subtitle !== '')
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $subtitle }}</span>
                                    @endif
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @elseif ($primaryContractorId)
                <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">
                    Ta firma nie ma jeszcze innych kontaktów w bazie — wyszukaj osobę albo dodaj nową poniżej.
                </p>
            @endif

            <div
                class="relative"
                x-data="{ open: @entangle('showResults') }"
                @click.outside="open = false"
            >
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                    Szukaj w całej bazie
                </label>
                <div class="relative">
                    <input
                        type="search"
                        wire:model.live.debounce.350ms="searchQuery"
                        @focus="if ($wire.results.length) { open = true }"
                        placeholder="Imię, nazwisko, telefon, e-mail, firma…"
                        autocomplete="off"
                        class="fi-input block w-full rounded-lg border-gray-300 py-2.5 pl-10 pr-3 text-sm shadow-sm dark:border-white/10 dark:bg-white/5"
                    />
                    <x-heroicon-m-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Wpisz min. 3 znaki — albo wybierz kontakt firmy powyżej.</p>
                <label class="mt-2 inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input
                        type="checkbox"
                        wire:model.live="searchAll"
                        class="rounded border-gray-400 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-white/20 dark:bg-white/5"
                    />
                    Szukaj we wszystkich kontrahentach
                </label>

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
                        @else
                            <div class="px-3 py-3 text-sm text-gray-600 dark:text-gray-300">
                                Brak dopasowań dla „{{ $searchQuery }}”.
                            </div>
                            <div class="border-t border-gray-100 px-3 py-2 dark:border-white/5">
                                <button
                                    type="button"
                                    wire:click="openQuickCreate(true)"
                                    class="text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                                >
                                    Utwórz nową firmę / klienta
                                </button>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            <div class="mt-3">
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Rola / notatka (opcjonalnie)</label>
                <input
                    type="text"
                    wire:model="notes"
                    placeholder="np. nauczyciel jadący na wycieczkę"
                    class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5"
                />
            </div>

            @if (! $showQuickCreate)
                <div class="mt-3 flex flex-col items-start gap-2">
                    @if ($primaryContractorId)
                        <button
                            type="button"
                            wire:click="openQuickCreate"
                            class="text-sm font-medium text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200"
                        >
                            Dodaj osobę do firmy głównego zamawiającego
                        </button>
                    @endif
                    <button
                        type="button"
                        wire:click="openQuickCreate(true)"
                        class="text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                    >
                        {{ $primaryContractorId ? 'Utwórz nową firmę / klienta' : 'Kontaktu nie ma w bazie? Wprowadź ręcznie' }}
                    </button>
                </div>
            @endif

            @if ($showQuickCreate)
                <div class="mt-4 rounded-lg border border-gray-200 p-3 dark:border-white/10">
                    <p class="mb-3 text-sm font-medium text-gray-800 dark:text-gray-100">
                        {{ $createAsNewCompany ? 'Nowa firma / klient' : 'Dane dodatkowego kontaktu' }}
                    </p>
                    @if ($primaryContractorId)
                        <label class="mb-3 inline-flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input
                                type="checkbox"
                                wire:model.live="createAsNewCompany"
                                class="mt-0.5 rounded border-gray-400 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-white/20 dark:bg-white/5"
                            />
                            <span>
                                <span class="font-medium">Utwórz nową firmę (typ klient)</span>
                                <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                                    Odznacz, aby dopisać osobę do firmy głównego zamawiającego.
                                </span>
                            </span>
                        </label>
                    @endif
                    <div class="grid gap-3 sm:grid-cols-2">
                        @if ($createAsNewCompany || ! $primaryContractorId)
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Firma / instytucja</label>
                                <input type="text" wire:model="companyName" class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5" />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Nowa firma dostanie typ „klient”. Pole opcjonalne — bez nazwy użyjemy imienia i nazwiska.
                                </p>
                            </div>
                        @else
                            <p class="sm:col-span-2 text-xs text-gray-500 dark:text-gray-400">
                                Osoba zostanie powiązana z firmą głównego zamawiającego.
                            </p>
                        @endif
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Imię</label>
                            <input type="text" wire:model="firstName" class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Nazwisko</label>
                            <input type="text" wire:model="lastName" class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Telefon</label>
                            <input type="text" wire:model="phone" class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">E-mail</label>
                            <input type="email" wire:model="email" class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5" />
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <x-filament::button type="button" wire:click="quickCreate" size="sm">
                            {{ $editingIndex !== null ? 'Zapisz zmianę' : 'Dodaj jako dodatkowy kontakt' }}
                        </x-filament::button>
                        <x-filament::button type="button" wire:click="$set('showQuickCreate', false)" color="gray" size="sm">
                            Anuluj
                        </x-filament::button>
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
