<x-filament-panels::page>
    @if(filled($archiveMessage) && $readOnly)
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            {{ $archiveMessage }}
        </div>
    @endif

    <div class="space-y-6">
        <x-filament::section heading="Zaliczka od biura" icon="heroicon-o-banknotes">
            <p class="mb-4 text-sm text-gray-600">
                Biuro planuje i wypłaca zaliczkę w jednej lub kilku walutach. Kwoty poniżej to gotówka przekazana pilotowi przed wyjazdem.
            </p>

            @if($paidLines->isNotEmpty())
                <div class="overflow-hidden rounded-xl border md:hidden">
                    <div class="divide-y">
                        @foreach($paidLines as $line)
                            @php
                                $symbol = $line['currency']?->symbol ?? $line['currency']?->code ?? '—';
                            @endphp
                            <div class="flex items-center justify-between px-4 py-3 text-sm">
                                <span class="text-gray-700">{{ $line['currency']?->name ?? 'Waluta' }}</span>
                                <span class="font-semibold text-gray-900">
                                    {{ \App\Support\MoneyFormatter::format($line['amount'], $symbol) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="hidden overflow-hidden rounded-xl border md:block">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Waluta</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">Wypłacono</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @foreach($paidLines as $line)
                                @php
                                    $symbol = $line['currency']?->symbol ?? $line['currency']?->code ?? '—';
                                @endphp
                                <tr>
                                    <td class="px-4 py-3">{{ $line['currency']?->name ?? 'Waluta' }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-900">
                                        {{ \App\Support\MoneyFormatter::format($line['amount'], $symbol) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @elseif($plannedLines->isNotEmpty())
                <div class="rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                    <p class="font-medium">Zaplanowana zaliczka (jeszcze niewypłacona)</p>
                    <ul class="mt-2 space-y-1">
                        @foreach($plannedLines as $line)
                            <li>
                                {{ \App\Support\MoneyFormatter::format($line->amount, $line->currency?->symbol ?? $line->currency?->code ?? 'PLN') }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @else
                <p class="text-sm text-gray-500">Biuro nie zaplanowało jeszcze zaliczki dla tej imprezy.</p>
            @endif

            @if($this->event->pilot_advance_paid_comment)
                <p class="mt-3 text-sm text-gray-600">
                    <span class="font-medium">Komentarz biura:</span> {{ $this->event->pilot_advance_paid_comment }}
                </p>
            @endif
        </x-filament::section>

        @if($showCurrencyExchange)
        <x-filament::section heading="Wymiana walut" icon="heroicon-o-arrows-right-left">
            <p class="mb-4 text-sm text-gray-600">
                Jeśli wymienisz część PLN na walutę obcą (np. w kantorze), zapisz to tutaj.
                Wymiana <strong>nie jest kosztem</strong> w rozliczeniu — przesuwa saldo między walutami.
            </p>

            @if($currencyExchanges->isNotEmpty())
                <div class="mb-4 space-y-2 md:hidden">
                    @foreach($currencyExchanges as $exchange)
                        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm">
                            <div class="font-medium text-gray-900">{{ $exchange->exchanged_at?->format('d.m.Y H:i') }}</div>
                            <div class="mt-1 text-gray-700">
                                {{ number_format((float) $exchange->from_amount, 2, ',', ' ') }}
                                {{ $exchange->fromCurrency?->symbol ?? '' }}
                                →
                                {{ number_format((float) $exchange->to_amount, 2, ',', ' ') }}
                                {{ $exchange->toCurrency?->symbol ?? '' }}
                            </div>
                            <div class="mt-1 text-xs text-gray-500">
                                Kurs: {{ number_format((float) $exchange->exchange_rate, 4, ',', ' ') }}
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mb-4 hidden overflow-hidden rounded-xl border md:block">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Data</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Wymiana</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">Kurs</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @foreach($currencyExchanges as $exchange)
                                <tr>
                                    <td class="px-4 py-3">{{ $exchange->exchanged_at?->format('d.m.Y H:i') }}</td>
                                    <td class="px-4 py-3">
                                        {{ number_format((float) $exchange->from_amount, 2, ',', ' ') }}
                                        {{ $exchange->fromCurrency?->symbol ?? '' }}
                                        →
                                        {{ number_format((float) $exchange->to_amount, 2, ',', ' ') }}
                                        {{ $exchange->toCurrency?->symbol ?? '' }}
                                    </td>
                                    <td class="px-4 py-3 text-right">{{ number_format((float) $exchange->exchange_rate, 4, ',', ' ') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if($settlementEditable && ! $readOnly)
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Z waluty</label>
                        <select wire:model.live.debounce.500ms="exchangeFromCurrencyId" class="fi-select-input w-full rounded-lg border px-3 py-2 text-sm">
                            <option value="">— wybierz —</option>
                            @foreach($this->getCurrencyOptions() as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('exchangeFromCurrencyId') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Na walutę</label>
                        <select wire:model.live.debounce.500ms="exchangeToCurrencyId" class="fi-select-input w-full rounded-lg border px-3 py-2 text-sm">
                            <option value="">— wybierz —</option>
                            @foreach($this->getCurrencyOptions() as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('exchangeToCurrencyId') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Kwota wydana</label>
                        <input type="number" step="0.01" wire:model.live.debounce.500ms="exchangeFromAmount" class="fi-input w-full rounded-lg border px-3 py-2 text-sm" />
                        @error('exchangeFromAmount') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Kwota otrzymana</label>
                        <input type="number" step="0.01" wire:model.live.debounce.500ms="exchangeToAmount" class="fi-input w-full rounded-lg border px-3 py-2 text-sm" />
                        @error('exchangeToAmount') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Kurs (opcjonalnie)</label>
                        <input type="number" step="0.00001" wire:model.live.debounce.500ms="exchangeRate" class="fi-input w-full rounded-lg border px-3 py-2 text-sm" placeholder="np. 4,35" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Uwagi</label>
                        <input type="text" wire:model.live.debounce.500ms="exchangeNotes" class="fi-input w-full rounded-lg border px-3 py-2 text-sm" />
                    </div>
                </div>
                <div class="mt-4">
                    <x-filament::button wire:click="recordCurrencyExchange" size="sm">
                        Zapisz wymianę walut
                    </x-filament::button>
                </div>
            @else
                <p class="text-sm text-gray-500">Wymiany można dodawać tylko w okresie pełnego dostępu do imprezy.</p>
            @endif
        </x-filament::section>
        @endif

        <x-filament::section heading="Zwrot reszty gotówki" icon="heroicon-o-arrow-uturn-left">
            <p class="mb-4 text-sm text-gray-600">
                Po wyjeździe zwróć niewydaną gotówkę do biura <strong>w tej samej walucie</strong>, w której ją otrzymałeś.
                System liczy saldo per waluta: wydano ± wymiany − wydatki − zwrot.
            </p>

            @include('pilot.partials.cash-summary', [
                'editable' => $settlementEditable && ! $readOnly,
                'compact' => false,
            ])

            <p class="mt-4 text-sm text-gray-500">
                Pełne rozliczenie wydatków i raport końcowy:
                <a href="{{ $settlementUrl }}" class="font-semibold text-teal-700 hover:text-teal-800">Przejdź do rozliczenia</a>
            </p>
        </x-filament::section>
    </div>
</x-filament-panels::page>
