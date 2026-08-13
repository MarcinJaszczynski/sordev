<div class="space-y-4">
    @if (session('status'))
        <div class="rounded-lg border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    @if (! $this->editable)
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Rozliczenie zamknięte — tryb tylko do odczytu.
        </div>
    @endif

    {{-- Wypłata z biura: pełny formularz w adminie; w panelu pilota podgląd (opcjonalnie) --}}
    @if ($this->showOfficePayoutBlock && $focus !== 'exchange')
    <section @class(['pilot-card' => $compact, 'rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900' => ! $compact])>
        <h3 class="mb-1 text-base font-semibold text-gray-900 dark:text-gray-100">Wypłata gotówki pilotowi</h3>
        <p class="mb-3 text-xs text-gray-500">
            Ile biuro fizycznie wydało pilotowi, w jakiej walucie i kiedy. To zasila kolumnę „Od biura” poniżej.
            @if ($this->context === 'admin')
                Ten sam zapis widzi pilot w panelu zaliczki.
            @endif
        </p>

        @if ($this->officePayouts->isNotEmpty())
            <ul class="mb-3 divide-y rounded-lg border border-gray-200 dark:border-gray-700">
                @foreach ($this->officePayouts as $cash)
                    <li class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-sm" wire:key="office-payout-{{ $cash->currency_id }}">
                        <span class="font-medium text-gray-900 dark:text-gray-100">
                            {{ number_format((float) $cash->provided_amount, 2, ',', ' ') }}
                            {{ $cash->currency?->code ?: $cash->currency?->symbol ?: '—' }}
                        </span>
                        <span class="flex flex-wrap items-center gap-2 text-xs text-gray-500">
                            <span>
                                @if ($cash->provided_at)
                                    {{ $cash->provided_at->format('d.m.Y') }}
                                @elseif ($this->event->pilot_funds_paid_at)
                                    {{ $this->event->pilot_funds_paid_at->format('d.m.Y') }}
                                @else
                                    —
                                @endif
                                @if ($cash->notes)
                                    · {{ $cash->notes }}
                                @endif
                            </span>
                            @if ($this->canRecordPayout)
                                <button type="button" wire:click="editOfficePayout({{ $cash->currency_id }})" class="text-indigo-700 hover:underline dark:text-indigo-300">Edytuj</button>
                                <button
                                    type="button"
                                    wire:click="deleteOfficePayout({{ $cash->currency_id }})"
                                    wire:confirm="Usunąć tę wypłatę gotówki?"
                                    class="text-red-700 hover:underline dark:text-red-300"
                                >Usuń</button>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
            @if ($this->event->pilot_funds_paid)
                <p class="mb-3 text-xs text-gray-500">
                    Oznaczono jako wypłacone
                    @if ($this->event->pilot_funds_paid_at)
                        {{ $this->event->pilot_funds_paid_at->format('d.m.Y H:i') }}
                    @endif
                    @if ($this->event->pilotFundsPaidByUser)
                        · {{ $this->event->pilotFundsPaidByUser->name }}
                    @endif
                </p>
            @endif
        @else
            <p class="mb-3 text-sm text-amber-800 dark:text-amber-200">
                Brak zarejestrowanej wypłaty — saldo „Od biura” będzie puste, dopóki ktoś z biura nie zapisze gotówki.
            </p>
        @endif

        @if ($this->canRecordPayout)
            @if (! $this->event->assigned_to)
                <p class="text-sm text-red-700">Najpierw przypisz pilota do imprezy.</p>
            @else
                @if ($this->editingPayoutCurrencyId)
                    <p class="mb-2 text-xs font-medium text-indigo-800 dark:text-indigo-200">
                        Edycja wypłaty
                        <button type="button" wire:click="cancelEditOfficePayout" class="ml-2 text-gray-600 underline">Anuluj</button>
                    </p>
                @endif
                <div class="grid gap-2 {{ $compact ? '' : 'sm:grid-cols-2 lg:grid-cols-4' }}">
                    <div>
                        <label class="mb-1 block text-xs text-gray-600">Waluta *</label>
                        <select wire:model="payoutCurrencyId" @disabled($this->editingPayoutCurrencyId) class="{{ $compact ? 'pilot-field' : 'w-full rounded-lg border px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-950' }}">
                            @foreach ($this->getCurrencyOptions() as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('payoutCurrencyId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-600">Kwota wydana pilotowi *</label>
                        <input type="text" inputmode="decimal" wire:model="payoutAmount" placeholder="np. 3000" class="{{ $compact ? 'pilot-field' : 'w-full rounded-lg border px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-950' }}" />
                        @error('payoutAmount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-600">Data wypłaty *</label>
                        <input type="date" wire:model="payoutProvidedAt" class="{{ $compact ? 'pilot-field' : 'w-full rounded-lg border px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-950' }}" />
                        @error('payoutProvidedAt') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-600">Komentarz</label>
                        <input type="text" wire:model="payoutComment" placeholder="np. gotówka w biurze" class="{{ $compact ? 'pilot-field' : 'w-full rounded-lg border px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-950' }}" />
                    </div>
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    @if ($compact)
                        <button type="button" wire:click="saveOfficePayout" class="pilot-touch-btn bg-gray-800 text-white">
                            {{ $this->editingPayoutCurrencyId ? 'Zapisz zmiany' : 'Zapisz wypłatę' }}
                        </button>
                        <button type="button" wire:click="prefillPayoutFromCalculation" class="pilot-touch-btn border border-gray-300 bg-white text-gray-900">Uzupełnij z wyliczenia</button>
                    @else
                        <x-filament::button wire:click="saveOfficePayout" size="sm" color="primary">
                            {{ $this->editingPayoutCurrencyId ? 'Zapisz zmiany' : 'Zapisz wypłatę' }}
                        </x-filament::button>
                        <x-filament::button wire:click="prefillPayoutFromCalculation" size="sm" color="gray">
                            Uzupełnij z wyliczenia
                        </x-filament::button>
                    @endif
                    <span class="text-xs text-gray-500">Wyliczenie = dopłata (plan − zaliczki biura na kosztach).</span>
                </div>
            @endif
        @endif
    </section>
    @endif

    @if ($focus !== 'exchange')
    <section @class(['pilot-card' => $compact, 'rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900' => ! $compact])>
        <h3 class="mb-1 text-base font-semibold text-gray-900 dark:text-gray-100">Gotówka dla pilota</h3>
        <p class="mb-3 text-xs text-gray-500">
            Od biura → ewentualna wymiana → wydatki → zwrot / dopłata. Saldo per waluta.
        </p>
        @include('pilot.partials.cash-summary', [
            'editable' => $this->editable,
            'compact' => $compact,
        ])
    </section>
    @endif

    @if ($this->showCurrencyExchange)
    <section @class(['pilot-card' => $compact, 'rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900' => ! $compact])>
        <h3 class="mb-2 text-base font-semibold text-gray-900 dark:text-gray-100">Wymiana walut</h3>
        <p class="mb-3 text-xs text-gray-500">
            Np. 2000 PLN → EUR na wyjeździe. Zmienia kolumnę „Po wymianie” powyżej.
            Przy podanym kursie kwota oddana = otrzymana × kurs (np. 100 EUR × 4,30 = 430 PLN).
        </p>

        @if ($this->currencyExchanges->isNotEmpty())
            <ul class="mb-3 space-y-2 text-sm">
                @foreach ($this->currencyExchanges as $ex)
                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-gray-100 px-2 py-1.5 dark:border-gray-800" wire:key="exchange-{{ $ex->id }}">
                        <span class="text-gray-700 dark:text-gray-200">
                            {{ number_format((float) $ex->from_amount, 2, ',', ' ') }}
                            {{ $ex->fromCurrency?->code ?: $ex->fromCurrency?->symbol }}
                            →
                            {{ number_format((float) $ex->to_amount, 2, ',', ' ') }}
                            {{ $ex->toCurrency?->code ?: $ex->toCurrency?->symbol }}
                            @if ((float) ($ex->exchange_rate ?? 0) > 0)
                                <span class="text-[11px] text-gray-500">@ {{ rtrim(rtrim(number_format((float) $ex->exchange_rate, 5, ',', ' '), '0'), ',') }}</span>
                            @endif
                        </span>
                        <span class="flex items-center gap-2 text-xs text-gray-500">
                            <span>{{ $ex->exchanged_at?->format('d.m.Y') }}</span>
                            @if ($this->editable)
                                <button type="button" wire:click="editExchange({{ $ex->id }})" class="text-indigo-700 hover:underline dark:text-indigo-300">Edytuj</button>
                                <button
                                    type="button"
                                    wire:click="deleteExchange({{ $ex->id }})"
                                    wire:confirm="Usunąć tę wymianę walut?"
                                    class="text-red-700 hover:underline dark:text-red-300"
                                >Usuń</button>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($this->editable)
            @if ($this->editingExchangeId)
                <p class="mb-2 text-xs font-medium text-indigo-800 dark:text-indigo-200">
                    Edycja wymiany #{{ $this->editingExchangeId }}
                    <button type="button" wire:click="cancelEditExchange" class="ml-2 text-gray-600 underline">Anuluj</button>
                </p>
            @endif
            <div class="grid gap-2 {{ $compact ? '' : 'sm:grid-cols-2 lg:grid-cols-3' }}">
                <div>
                    <label class="mb-1 block text-xs text-gray-600">Z waluty</label>
                    <select wire:model="exchangeFromCurrencyId" class="{{ $compact ? 'pilot-field' : 'w-full rounded-lg border px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-950' }}">
                        <option value="">—</option>
                        @foreach ($this->getCurrencyOptions() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-600">Kwota oddana</label>
                    <input type="text" inputmode="decimal" wire:model="exchangeFromAmount" class="{{ $compact ? 'pilot-field' : 'w-full rounded-lg border px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-950' }}" />
                    <p class="mt-0.5 text-[11px] text-gray-500">Przy kursie wyliczana automatycznie.</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-600">Na walutę</label>
                    <select wire:model="exchangeToCurrencyId" class="{{ $compact ? 'pilot-field' : 'w-full rounded-lg border px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-950' }}">
                        <option value="">—</option>
                        @foreach ($this->getCurrencyOptions() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-600">Kwota otrzymana</label>
                    <input type="text" inputmode="decimal" wire:model.live.blur="exchangeToAmount" class="{{ $compact ? 'pilot-field' : 'w-full rounded-lg border px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-950' }}" />
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-600">Kurs (opcjonalnie)</label>
                    <input type="text" inputmode="decimal" wire:model.live.blur="exchangeRate" class="{{ $compact ? 'pilot-field' : 'w-full rounded-lg border px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-950' }}" />
                    <p class="mt-0.5 text-[11px] text-gray-500">Ile waluty źródłowej za 1 docelową (np. 4,30).</p>
                </div>
                <div class="{{ $compact ? '' : 'sm:col-span-2 lg:col-span-3' }}">
                    <label class="mb-1 block text-xs text-gray-600">Uwagi</label>
                    <input type="text" wire:model="exchangeNotes" class="{{ $compact ? 'pilot-field' : 'w-full rounded-lg border px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-950' }}" />
                </div>
            </div>
            <div class="mt-2 flex flex-wrap gap-2">
                @if ($compact)
                    <button type="button" wire:click="saveExchange" class="pilot-touch-btn border border-gray-300 bg-white text-gray-900">
                        {{ $this->editingExchangeId ? 'Zapisz zmiany' : 'Zapisz wymianę' }}
                    </button>
                @else
                    <x-filament::button wire:click="saveExchange" size="sm" color="gray">
                        {{ $this->editingExchangeId ? 'Zapisz zmiany' : 'Zapisz wymianę' }}
                    </x-filament::button>
                @endif
            </div>
        @endif
    </section>
    @endif

    @if ($focus !== 'exchange')
    <section @class(['pilot-card' => $compact, 'rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900' => ! $compact])>
        <h3 class="mb-1 text-base font-semibold text-gray-900 dark:text-gray-100">Wydatki pilota</h3>
        <p class="mb-3 text-xs text-gray-500">Tylko pozycje z płatnikiem Pilot — jak na zakładce Koszty.</p>

        @include('pilot.partials.expense-ledger', [
            'editable' => $this->editable,
            'compact' => $compact,
        ])

        @if ($this->editable)
            <div class="mt-4 border-t border-gray-100 pt-4 dark:border-gray-800">
                <h4 class="mb-2 text-sm font-medium text-gray-800 dark:text-gray-200">Dodaj wydatek</h4>
                <div class="grid gap-2 {{ $compact ? '' : 'sm:grid-cols-2 lg:grid-cols-4' }}">
                    <input type="text" wire:model="expenseName" placeholder="Opis" class="{{ $compact ? 'pilot-field' : 'rounded-lg border px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-950' }}" />
                    <input type="text" inputmode="decimal" wire:model="expenseAmount" placeholder="Kwota" class="{{ $compact ? 'pilot-field' : 'rounded-lg border px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-950' }}" />
                    <select wire:model="expenseCurrencyId" class="{{ $compact ? 'pilot-field' : 'rounded-lg border px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-950' }}">
                        @foreach ($this->getCurrencyOptions() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    <input type="text" wire:model="expenseInvoiceNumber" placeholder="Nr faktury / paragona" class="{{ $compact ? 'pilot-field' : 'rounded-lg border px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-950' }}" />
                </div>
                <div class="mt-2">
                    @if ($compact)
                        <button type="button" wire:click="addExpense" class="pilot-touch-btn bg-gray-800 text-white">Dodaj wydatek</button>
                    @else
                        <x-filament::button wire:click="addExpense" size="sm">Dodaj wydatek</x-filament::button>
                    @endif
                </div>
            </div>
        @endif
    </section>
    @endif
</div>
