<div class="space-y-6">
    <div class="sticky top-0 z-10 space-y-3 rounded-xl border border-gray-200 bg-white/95 p-4 shadow-sm backdrop-blur-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-gray-900">Lista uczestników — {{ $this->event->name }}</p>
                <p class="text-xs text-gray-500">
                    {{ count($participants) }} {{ count($participants) === 1 ? 'osoba' : 'osób' }} na liście
                    · import CSV/Excel · weryfikacja z umowami, noclegami i wpłatami
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-filament::button tag="a" :href="$this->templateUrl" color="gray" icon="heroicon-o-arrow-down-tray" target="_blank">
                    Szablon
                </x-filament::button>
                <x-filament::button tag="a" :href="$this->exportUrl" color="gray" icon="heroicon-o-document-arrow-down" target="_blank">
                    Eksport ubezpieczeniowy
                </x-filament::button>
                <x-filament::button wire:click="syncFromAgreements" color="gray" icon="heroicon-o-arrow-path">
                    Z umów
                </x-filament::button>
                <x-filament::button wire:click="propagateToPayments" color="gray" icon="heroicon-o-banknotes">
                    → Wpłaty
                </x-filament::button>
                <x-filament::button wire:click="propagateToHotel" color="gray" icon="heroicon-o-building-office-2">
                    → Hotele
                </x-filament::button>
            </div>
        </div>

        <nav class="flex flex-wrap gap-2" aria-label="Zakładki listy uczestników">
            @foreach ([
                'list' => ['Lista', 'heroicon-o-users'],
                'import' => ['Import', 'heroicon-o-arrow-up-tray'],
                'verification' => ['Weryfikacja', 'heroicon-o-shield-check'],
            ] as $tab => [$label, $icon])
                <button
                    type="button"
                    wire:click="switchTab('{{ $tab }}')"
                    @class([
                        'inline-flex items-center gap-1.5 rounded-lg border px-4 py-2 text-sm transition',
                        'border-amber-400 bg-amber-50 text-amber-950' => $activeTab === $tab,
                        'border-gray-200 bg-white text-gray-700 hover:border-gray-300' => $activeTab !== $tab,
                    ])
                >
                    <x-filament::icon :icon="$icon" class="h-4 w-4" />
                    {{ $label }}
                </button>
            @endforeach
        </nav>
    </div>

    @if ($activeTab === 'list')
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <x-filament::section heading="Uczestnicy">
                @if ($participants === [])
                    <p class="text-sm text-gray-600">Brak uczestników. Zaimportuj plik CSV/Excel lub dodaj ręcznie.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[40rem] text-left text-sm">
                            <thead class="border-b text-xs uppercase text-gray-500">
                                <tr>
                                    <th class="px-2 py-2">Imię i nazwisko</th>
                                    <th class="px-2 py-2">Data urodzenia</th>
                                    <th class="px-2 py-2">PESEL</th>
                                    <th class="px-2 py-2">Kontakt</th>
                                    <th class="px-2 py-2">Dieta</th>
                                    <th class="px-2 py-2">Zgoda</th>
                                    <th class="px-2 py-2">Źródło</th>
                                    <th class="px-2 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                @foreach ($participants as $participant)
                                    <tr wire:key="participant-{{ $participant['id'] }}">
                                        <td class="px-2 py-2 font-medium text-gray-900">{{ $participant['full_name'] }}</td>
                                        <td class="px-2 py-2 text-gray-700">{{ $participant['birth_date'] ?: '—' }}</td>
                                        <td class="px-2 py-2 text-gray-700">{{ $participant['pesel'] ?: '—' }}</td>
                                        <td class="px-2 py-2 text-gray-600">
                                            @if ($participant['email'])
                                                <div>{{ $participant['email'] }}</div>
                                            @endif
                                            @if ($participant['phone'])
                                                <div>{{ $participant['phone'] }}</div>
                                            @endif
                                            @if (! $participant['email'] && ! $participant['phone'])
                                                —
                                            @endif
                                        </td>
                                        <td class="px-2 py-2 text-gray-700">{{ $participant['diet'] ?: '—' }}</td>
                                        <td class="px-2 py-2">
                                            @if ($participant['parent_consent'] ?? false)
                                                <span class="text-emerald-700">Tak</span>
                                            @else
                                                <span class="text-gray-400">Nie</span>
                                            @endif
                                        </td>
                                        <td class="px-2 py-2 text-gray-600">{{ $participant['source'] }}</td>
                                        <td class="px-2 py-2 text-right">
                                            <button type="button" wire:click="startEdit({{ $participant['id'] }})" class="text-primary-600 hover:underline">Edytuj</button>
                                            <button type="button" wire:click="deleteParticipant({{ $participant['id'] }})" wire:confirm="Usunąć uczestnika z listy?" class="ml-2 text-danger-600 hover:underline">Usuń</button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>

            <x-filament::section :heading="$editParticipantId ? 'Edycja uczestnika' : 'Nowy uczestnik'">
                <div class="space-y-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700">Imię</label>
                        <input type="text" wire:model.live.debounce.500ms="formFirstName" class="fi-input block w-full rounded-lg border-gray-300 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700">Nazwisko</label>
                        <input type="text" wire:model.live.debounce.500ms="formLastName" class="fi-input block w-full rounded-lg border-gray-300 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700">Data urodzenia</label>
                        <input type="date" wire:model.live.debounce.500ms="formBirthDate" class="fi-input block w-full rounded-lg border-gray-300 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700">PESEL</label>
                        <input type="text" wire:model.live.debounce.500ms="formPesel" maxlength="11" class="fi-input block w-full rounded-lg border-gray-300 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700">E-mail</label>
                        <input type="email" wire:model.live.debounce.500ms="formEmail" class="fi-input block w-full rounded-lg border-gray-300 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700">Telefon</label>
                        <input type="text" wire:model.live.debounce.500ms="formPhone" class="fi-input block w-full rounded-lg border-gray-300 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700">Nr rezerwacji</label>
                        <input type="text" wire:model.live.debounce.500ms="formBookingReference" class="fi-input block w-full rounded-lg border-gray-300 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700">Dieta</label>
                        <input type="text" wire:model.live.debounce.500ms="formDiet" class="fi-input block w-full rounded-lg border-gray-300 text-sm" placeholder="np. wegetariańska" />
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model.live="formParentConsent" class="rounded border-gray-300" />
                        Zgoda rodzica / opiekuna
                    </label>
                    <div class="flex gap-2">
                        <x-filament::button wire:click="saveParticipant" color="primary" class="flex-1">
                            {{ $editParticipantId ? 'Zapisz' : 'Dodaj' }}
                        </x-filament::button>
                        @if ($editParticipantId)
                            <x-filament::button wire:click="cancelEdit" color="gray">Anuluj</x-filament::button>
                        @endif
                    </div>
                </div>
            </x-filament::section>
        </div>
    @endif

    @if ($activeTab === 'import')
        <x-filament::section heading="Import listy uczestników">
            <div class="space-y-4">
                <p class="text-sm text-gray-600">
                    Wgraj plik CSV lub Excel z kolumnami: <strong>Imię</strong>, <strong>Nazwisko</strong>, <strong>Data urodzenia</strong>.
                    Opcjonalnie: PESEL, e-mail, telefon, nr rezerwacji.
                </p>

                <div class="flex flex-wrap items-end gap-4">
                    <div class="min-w-[14rem] flex-1">
                        <label class="mb-1 block text-xs font-medium text-gray-700">Plik CSV lub Excel</label>
                        <input type="file" wire:model.live.debounce.500ms="importFile" accept=".xlsx,.xls,.csv,text/csv" class="block w-full text-sm text-gray-600" />
                        @error('importFile') <p class="mt-1 text-xs text-danger-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700">Tryb importu</label>
                        <select wire:model.live.debounce.500ms="importMode" class="fi-input rounded-lg border-gray-300 text-sm">
                            <option value="append">Dodaj do listy</option>
                            <option value="replace">Zastąp wcześniejszy import</option>
                        </select>
                    </div>

                    <x-filament::button wire:click="importParticipants" color="primary" icon="heroicon-o-arrow-up-tray" wire:loading.attr="disabled">
                        Importuj
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>
    @endif

    @if ($activeTab === 'verification')
        <x-filament::section heading="Weryfikacja danych między źródłami">
            <div class="mb-4 flex flex-wrap gap-3 text-sm">
                <span class="rounded-full bg-gray-100 px-3 py-1">Lista: <strong>{{ $verificationSummary['roster_count'] ?? 0 }}</strong></span>
                <span class="rounded-full bg-gray-100 px-3 py-1">Umowy: <strong>{{ $verificationSummary['agreements_count'] ?? 0 }}</strong></span>
                <span class="rounded-full bg-gray-100 px-3 py-1">Hotele: <strong>{{ $verificationSummary['hotel_count'] ?? 0 }}</strong></span>
                <span class="rounded-full bg-gray-100 px-3 py-1">Wpłaty: <strong>{{ $verificationSummary['payments_count'] ?? 0 }}</strong></span>
                <span class="rounded-full bg-success-50 px-3 py-1 text-success-700">Spójne: <strong>{{ $verificationSummary['fully_matched'] ?? 0 }}</strong></span>
                <span class="rounded-full bg-warning-50 px-3 py-1 text-warning-700">Uwagi: <strong>{{ $verificationSummary['issues'] ?? 0 }}</strong></span>
            </div>

            <x-filament::button wire:click="runVerification" color="gray" icon="heroicon-o-arrow-path" class="mb-4">
                Odśwież weryfikację
            </x-filament::button>

            @if ($verificationRows === [])
                <p class="text-sm text-gray-600">Brak danych do porównania.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[48rem] text-left text-sm">
                        <thead class="border-b text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-2 py-2">Uczestnik</th>
                                <th class="px-2 py-2">Data ur.</th>
                                <th class="px-2 py-2 text-center">Lista</th>
                                <th class="px-2 py-2 text-center">Umowa</th>
                                <th class="px-2 py-2 text-center">Nocleg</th>
                                <th class="px-2 py-2 text-center">Wpłata</th>
                                <th class="px-2 py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($verificationRows as $row)
                                @php
                                    $statusColors = [
                                        'matched' => 'bg-success-50 text-success-800',
                                        'partial' => 'bg-warning-50 text-warning-800',
                                        'missing' => 'bg-danger-50 text-danger-800',
                                        'orphan' => 'bg-gray-100 text-gray-700',
                                        'birth_mismatch' => 'bg-danger-50 text-danger-800',
                                    ];
                                    $statusLabels = [
                                        'matched' => 'OK',
                                        'partial' => 'Częściowo',
                                        'missing' => 'Brak powiązań',
                                        'orphan' => 'Tylko w jednym źródle',
                                        'birth_mismatch' => 'Data urodzenia',
                                    ];
                                    $color = $statusColors[$row['status']] ?? 'bg-gray-100 text-gray-700';
                                    $label = $statusLabels[$row['status']] ?? $row['status'];
                                @endphp
                                <tr wire:key="verify-{{ $row['key'] }}">
                                    <td class="px-2 py-2 font-medium">{{ $row['name'] }}</td>
                                    <td class="px-2 py-2">{{ $row['birth_date'] ?: '—' }}</td>
                                    @foreach (['roster', 'agreement', 'hotel', 'payment'] as $source)
                                        <td class="px-2 py-2 text-center">
                                            @if ($row['sources'][$source] ?? false)
                                                <span class="text-success-600">✓</span>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="px-2 py-2">
                                        <span class="rounded-full px-2 py-0.5 text-xs {{ $color }}" title="{{ $row['details'] ?? '' }}">{{ $label }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    @endif
</div>
