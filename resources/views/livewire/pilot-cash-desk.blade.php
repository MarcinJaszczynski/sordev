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

    {{-- Zaliczka z biura: pełny formularz w adminie; w panelu pilota podgląd (opcjonalnie) --}}
    @if ($this->showOfficePayoutBlock && $focus !== 'exchange')
    <section @class(['pilot-card' => $compact, 'rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900' => ! $compact])>
        <h3 class="mb-1 text-base font-semibold text-gray-900 dark:text-gray-100">Zaliczki wypłacone pilotowi</h3>
        <p class="mb-3 text-xs text-gray-500">
            Ile biuro fizycznie wydało pilotowi, w jakiej walucie i kiedy. To zasila kolumnę „Od biura” poniżej.
            Przy pomyłce edytuj lub usuń pozycję — nie trzeba zakładać nowej imprezy.
            @if ($this->context === 'admin')
                Ten sam zapis widzi pilot w panelu zaliczki.
            @endif
        </p>

        @if ($this->officePayouts->isNotEmpty())
            <ul class="mb-3 divide-y rounded-lg border border-gray-200 dark:border-gray-700">
                @foreach ($this->officePayouts as $cash)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-3 py-3 text-sm" wire:key="office-payout-{{ $cash->currency_id }}">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900 dark:text-gray-100">
                                {{ number_format((float) $cash->provided_amount, 2, ',', ' ') }}
                                {{ $cash->currency?->code ?: $cash->currency?->symbol ?: '—' }}
                            </p>
                            <p class="mt-0.5 text-xs text-gray-500">
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
                            </p>
                        </div>
                        @if ($this->canRecordPayout)
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($compact)
                                    <button type="button" wire:click="editOfficePayout({{ $cash->currency_id }})" class="pilot-touch-btn border border-indigo-300 bg-white text-indigo-800">Edytuj</button>
                                    <button
                                        type="button"
                                        wire:click="deleteOfficePayout({{ $cash->currency_id }})"
                                        wire:confirm="Usunąć tę zaliczkę?"
                                        class="pilot-touch-btn border border-red-300 bg-white text-red-800"
                                    >Usuń</button>
                                @else
                                    <x-filament::button wire:click="editOfficePayout({{ $cash->currency_id }})" size="sm" color="gray">
                                        Edytuj
                                    </x-filament::button>
                                    <x-filament::button
                                        wire:click="deleteOfficePayout({{ $cash->currency_id }})"
                                        wire:confirm="Usunąć tę zaliczkę?"
                                        size="sm"
                                        color="danger"
                                        outlined
                                    >
                                        Usuń
                                    </x-filament::button>
                                @endif
                            </div>
                        @endif
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
                Brak zarejestrowanej zaliczki — saldo „Od biura” będzie puste, dopóki ktoś z biura jej nie zapisze.
            </p>
        @endif

        @if ($this->canRecordPayout)
            @if (! app(\App\Services\PilotContractorAssignmentService::class)->eventHasAssignedPilot($this->event))
                <p class="text-sm text-red-700">Najpierw przypisz pilota do imprezy.</p>
            @else
                @if ($this->editingPayoutCurrencyId)
                    <p class="mb-2 text-xs font-medium text-indigo-800 dark:text-indigo-200">
                        Edycja zaliczki
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
                        <label class="mb-1 block text-xs text-gray-600">Kwota zaliczki *</label>
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
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    @if ($compact)
                        <button type="button" wire:click="saveOfficePayout" class="pilot-touch-btn bg-gray-800 text-white">
                            {{ $this->editingPayoutCurrencyId ? 'Zapisz zmiany' : 'Dodaj zaliczkę' }}
                        </button>
                        <button type="button" wire:click="prefillPayoutFromCalculation" class="pilot-touch-btn border border-gray-300 bg-white text-gray-900">Uzupełnij z wyliczenia</button>
                    @else
                        <x-filament::button wire:click="saveOfficePayout" size="md" color="primary">
                            {{ $this->editingPayoutCurrencyId ? 'Zapisz zmiany' : 'Dodaj zaliczkę' }}
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
        <h3 class="mb-1 text-base font-semibold text-gray-900 dark:text-gray-100">
            {{ $this->context === 'pilot' ? 'Rozliczenie zaliczki od biura' : 'Gotówka dla pilota' }}
        </h3>
        <p class="mb-3 text-xs text-gray-500">
            @if ($this->context === 'pilot')
                Kwota od biura / z autokaru → ewentualna wymiana → wydatki → zwrot. Saldo per waluta.
            @else
                Od biura / zbiórka w autokarze → ewentualna wymiana → wydatki → zwrot / dopłata. Saldo per waluta.
            @endif
        </p>
        @include('pilot.partials.cash-summary', [
            'editable' => $this->editable,
            'compact' => $compact,
        ])
    </section>
    @endif

    @if ($this->showCurrencyExchange && ($focus === 'exchange' || ($focus === 'all' && $this->includeCurrencyExchange)))
    <section @class(['pilot-card' => $compact, 'rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900' => ! $compact])>
        <h3 class="mb-2 text-base font-semibold text-gray-900 dark:text-gray-100">Wymiana walut</h3>
        <p class="mb-3 text-xs text-gray-500">
            Np. 2000 PLN → EUR na wyjeździe. Zmienia zasoby pilota i kolumnę „Po wymianie”.
            Przy podanym kursie kwota oddana = otrzymana × kurs (np. 100 EUR × 4,30 = 430 PLN).
        </p>

        @if ($focus === 'exchange')
            @include('pilot.partials.cash-resource-status', [
                'compact' => $compact,
                'canEditExchange' => $this->canEditCurrencyExchange,
            ])
        @endif

        @if ($this->respectPortalVisibility && ! $this->event->showsPilotCurrencyExchange())
            <p class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
                Portal: wymiana wyłączona — pilot nie widzi tej sekcji. Biuro może edytować tutaj; włącz flagę, żeby pokazać ją w panelu pilota.
            </p>
        @endif

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
                            @if ($this->canEditCurrencyExchange)
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
        @elseif (! $this->cashResourceStory->has_movements)
            <p class="mb-3 text-sm text-gray-500">Brak wymian — zasoby = wypłata z biura.</p>
        @endif

        @if ($this->canEditCurrencyExchange)
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
    @php
        $expenseCount = $this->expenseLines->count();
        $wrapExpenses = $this->collapseExpenses;
    @endphp
    @if ($wrapExpenses)
    <details open class="pilot-accordion rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
        <summary>Wydatki / koszty pilota ({{ $expenseCount }} {{ $expenseCount === 1 ? 'pozycja' : 'pozycji' }})</summary>
        <div class="mt-2">
    @else
    <section @class(['pilot-card' => $compact, 'rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900' => ! $compact])>
    @endif
        <h3 class="mb-1 text-base font-semibold text-gray-900 dark:text-gray-100">Wydatki / koszty pilota</h3>
        <p class="mb-3 text-xs text-gray-500">
            Pozycje z płatnikiem Pilot. Kolumna „Zapłacono” to tylko gotówka pilota — zaliczki biura są osobno (nie sumują się jako wydatek pilota).
        </p>

        @if ($this->officePayouts->isNotEmpty())
            <div class="mb-3 rounded-lg border border-indigo-100 bg-indigo-50/70 px-3 py-2 text-sm text-indigo-950 dark:border-indigo-900/40 dark:bg-indigo-950/30 dark:text-indigo-100">
                <div class="font-medium">Gotówka od biura</div>
                <div class="mt-0.5 flex flex-wrap gap-x-3 gap-y-1">
                    @foreach ($this->officePayouts as $cash)
                        <span>
                            {{ number_format((float) $cash->provided_amount, 2, ',', ' ') }}
                            {{ $cash->currency?->code ?: $cash->currency?->symbol ?: '—' }}
                            @if ($cash->provided_at)
                                <span class="text-xs text-indigo-800/80 dark:text-indigo-200/80">({{ $cash->provided_at->format('d.m.Y') }})</span>
                            @endif
                        </span>
                    @endforeach
                </div>
            </div>
        @else
            <p class="mb-3 text-xs text-amber-800 dark:text-amber-200">
                Brak zarejestrowanej gotówki od biura — wydatki rozliczaj względem wypłaty powyżej.
            </p>
        @endif

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
                        <button type="button" wire:click="addExpense" class="pilot-touch-btn sor-lw-btn--accent w-full sm:w-auto">
                            Dodaj wydatek
                        </button>
                    @else
                        <x-filament::button wire:click="addExpense" size="sm" color="primary">
                            Dodaj wydatek
                        </x-filament::button>
                    @endif
                </div>
            </div>
        @endif
    @if ($wrapExpenses)
        </div>
    </details>
    @else
    </section>
    @endif
    @endif
</div>
