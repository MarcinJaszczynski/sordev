<x-filament-panels::page>
    @include('filament.components.finance-module-nav', ['tabs' => $this->getNavigationTabs()])

    <form wire:submit.prevent="runImport" class="space-y-6 max-w-3xl">
        <x-filament::section>
            <x-slot name="heading">Bank Millennium (CSV)</x-slot>
            <x-slot name="description">
                Pobierz wyciąg w Millenecie: Moje finanse → Wyciąg z historii transakcji → format CSV.
                System wczyta wpływy (dodatnie kwoty) i spróbuje dopasować je do umów / wpłat uczestników
                po numerze rezerwacji, numerze umowy, kodzie imprezy lub nazwisku.
                Niedopasowane linie możesz przypisać ręcznie poniżej albo w skrzynce
                <a href="{{ \App\Filament\Pages\UnmatchedBankPaymentsInboxPage::getUrl() }}" class="text-primary-600 underline">Wpłaty do dopasowania</a>.
            </x-slot>

            <div>
                <label class="fi-fo-field-wrp-label inline-flex items-center gap-x-3" for="bankCsvFile">
                    <span class="text-sm font-medium text-gray-950 dark:text-white">Plik CSV</span>
                </label>
                <input
                    id="bankCsvFile"
                    type="file"
                    wire:model.live.debounce.500ms="csvFile"
                    accept=".csv,text/csv,text/plain"
                    class="mt-1 block w-full text-sm text-gray-950 file:mr-4 file:rounded-lg file:border-0 file:bg-primary-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-primary-700 hover:file:bg-primary-100 dark:text-white"
                />
                <div wire:loading wire:target="csvFile" class="mt-1 text-xs text-gray-500">Przesyłanie pliku…</div>
                @error('csvFile') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
            </div>
        </x-filament::section>

        <div class="flex items-center gap-3">
            <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="runImport,csvFile">
                <span wire:loading.remove wire:target="runImport,csvFile">Wczytaj wyciąg</span>
                <span wire:loading wire:target="runImport,csvFile">Analizowanie…</span>
            </x-filament::button>
        </div>
    </form>

    @if ($lastImportSummary)
        <x-filament::section class="mt-6" heading="Podsumowanie wczytania">
            <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                <div>
                    <dt class="font-medium text-gray-500">Wpływy</dt>
                    <dd class="text-lg font-semibold">{{ $lastImportSummary['total'] ?? 0 }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500">Dopasowane</dt>
                    <dd class="text-lg font-semibold text-success-600">{{ $lastImportSummary['matched'] ?? 0 }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500">Bez dopasowania</dt>
                    <dd class="text-lg font-semibold text-warning-600">{{ $lastImportSummary['unmatched'] ?? 0 }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500">Duplikaty</dt>
                    <dd class="text-lg font-semibold">{{ $lastImportSummary['duplicate'] ?? 0 }}</dd>
                </div>
            </dl>
        </x-filament::section>
    @endif

    @if ($previewLines !== [])
        <x-filament::section class="mt-6" heading="Podgląd wpłat">
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <x-filament::button color="success" wire:click="applySelected" wire:loading.attr="disabled">
                    Zaksięguj zaznaczone
                </x-filament::button>
                <p class="text-xs text-gray-500">Zaznacz wiersze do zaksięgowania. Już zaksięgowane pozostają w tabeli jako informacja.</p>
            </div>

            <div class="overflow-x-auto rounded-xl border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left">Zazn.</th>
                            <th class="px-3 py-2 text-left">Data</th>
                            <th class="px-3 py-2 text-left">Tytuł</th>
                            <th class="px-3 py-2 text-left">Kontrahent</th>
                            <th class="px-3 py-2 text-right">Kwota</th>
                            <th class="px-3 py-2 text-left">Dopasowanie</th>
                            <th class="px-3 py-2 text-left">Cel</th>
                            <th class="px-3 py-2 text-left">Akcja</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @foreach ($previewLines as $line)
                            <tr @class([
                                'bg-success-50/60' => ! empty($line['applied']),
                                'opacity-70' => ! empty($line['applied']),
                            ])>
                                <td class="px-3 py-2">
                                    @if (empty($line['applied']))
                                        <input
                                            type="checkbox"
                                            @checked(! empty($line['selected']))
                                            wire:click="toggleLineSelection({{ $line['id'] }})"
                                        />
                                    @else
                                        <span class="text-xs text-success-700">OK</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $line['operation_date'] }}</td>
                                <td class="px-3 py-2 max-w-xs">{{ $line['title'] }}</td>
                                <td class="px-3 py-2">{{ $line['counterparty'] ?: '—' }}</td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">{{ $line['amount_pln'] }} PLN</td>
                                <td class="px-3 py-2">
                                    @if ($line['match_status'] === 'unmatched')
                                        <span class="text-warning-700">Brak</span>
                                    @else
                                        <span class="text-success-700">{{ $line['match_reason'] }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2">{{ $line['target_label'] }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">
                                    @if (empty($line['applied']))
                                        <button
                                            type="button"
                                            class="text-sm font-medium text-primary-600 hover:underline"
                                            wire:click="openAssignModal({{ $line['id'] }})"
                                        >
                                            {{ $line['match_status'] === 'unmatched' ? 'Przypisz' : 'Zmień' }}
                                        </button>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    @if ($lastApplySummary && ! empty($lastApplySummary['errors']))
        <x-filament::section class="mt-6" heading="Ostrzeżenia przy księgowaniu">
            <ul class="max-h-48 list-disc overflow-y-auto pl-5 text-sm text-danger-600">
                @foreach ($lastApplySummary['errors'] as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif

    @include('filament.components.bank-payment-assign-modal')
</x-filament-panels::page>
