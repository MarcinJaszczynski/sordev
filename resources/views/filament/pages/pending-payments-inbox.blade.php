<x-filament-panels::page>

    @include('filament.components.finance-module-nav', ['activeTab' => 'pending-payments'])

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <x-filament::button :color="$displayMode === 'list' ? 'primary' : 'gray'" wire:click="$set('displayMode', 'list')">
            Lista
        </x-filament::button>
        <x-filament::button :color="$displayMode === 'calendar' ? 'primary' : 'gray'" wire:click="$set('displayMode', 'calendar')">
            Kalendarz
        </x-filament::button>

        <span class="mx-1 text-gray-300">|</span>

        @foreach([
            'all' => 'Wszystkie',
            'settlement' => 'Rozliczenia',
            'program_payment' => 'Zaliczki punktów',
            'vendor_invoice' => 'Faktury KSeF',
            'contract' => 'Kontrakty TFG',
            'agreement' => 'Umowy',
        ] as $filter => $label)
            <x-filament::button
                size="sm"
                :color="$typeFilter === $filter ? 'info' : 'gray'"
                wire:click="$set('typeFilter', '{{ $filter }}')"
            >
                {{ $label }}
            </x-filament::button>
        @endforeach

        <span class="mx-1 text-gray-300">|</span>

        @foreach([
            'all' => 'Wszyscy płatnicy',
            'office' => 'Biuro',
            'pilot' => 'Pilot',
        ] as $filter => $label)
            <x-filament::button
                size="sm"
                :color="$payerFilter === $filter ? 'warning' : 'gray'"
                wire:click="$set('payerFilter', '{{ $filter }}')"
            >
                {{ $label }}
            </x-filament::button>
        @endforeach

        <span class="ms-auto text-xs text-gray-500">
            {{ count($this->inboxEntries) }} pozycji
        </span>
    </div>

    @if($this->wasTruncated)
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
            Lista jest ucięta limitem źródeł (koszty {{ \App\Services\PendingPaymentAggregator::LIMIT_SETTLEMENT_COSTS }}, faktury/harmonogramy {{ \App\Services\PendingPaymentAggregator::LIMIT_VENDOR_INVOICES }}).
            Zawęź filtr typu lub sprawdź rejestr faktur / rozliczenia, jeśli brakuje pozycji.
        </div>
    @endif

    @if($displayMode === 'calendar')
        @if(count($this->calendarEvents) === 0)
            <div class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
                Brak terminów płatności w kalendarzu dla wybranych filtrów.
            </div>
        @endif

        <script
            type="application/json"
            id="pending-payments-calendar-events"
            wire:key="pending-payments-calendar-events-{{ md5(json_encode([$typeFilter, $payerFilter, count($this->calendarEvents)])) }}"
        >@json($this->calendarEvents)</script>

        <div
            id="pending-payments-calendar"
            wire:ignore
            class="min-h-[28rem] rounded-xl border border-gray-200 bg-white p-2 dark:border-gray-700 dark:bg-gray-900"
        ></div>

        @include('filament.components.fullcalendar-boot', [
            'elementId' => 'pending-payments-calendar',
            'livewireId' => $this->getId(),
        ])
    @else
        <div class="sor-scroll-hint overflow-x-auto rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <table class="admin-zebra-table w-full min-w-[64rem] text-sm">
                <thead class="bg-gray-50 text-left text-xs font-bold uppercase tracking-wide text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                    <tr>
                        <th class="px-3 py-2">Termin</th>
                        <th class="px-3 py-2">Typ</th>
                        <th class="px-3 py-2">Impreza</th>
                        <th class="px-3 py-2">Pozycja / kontekst</th>
                        <th class="px-3 py-2 text-right">Kwota</th>
                        <th class="px-3 py-2">Płatnik</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2 text-right">Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->inboxEntries as $row)
                        <tr wire:key="pending-payment-{{ $row['id'] }}">
                            <td class="px-3 py-2 whitespace-nowrap font-medium">
                                {{ $row['due_date'] ? \Carbon\Carbon::parse($row['due_date'])->format('d.m.Y') : '—' }}
                            </td>
                            <td class="px-3 py-2">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold" style="background: {{ $row['color'] }}22; color: {{ $row['color'] }}">
                                    {{ $row['type_label'] }}
                                </span>
                            </td>
                            <td class="px-3 py-2">
                                @if(!empty($row['url']) && !empty($row['event_id']))
                                    <a href="{{ $row['url'] }}" class="font-medium text-primary-700 hover:underline dark:text-primary-300">
                                        {{ $row['event_label'] ?? $row['event_name'] ?? $row['event_code'] ?? 'Impreza #'.$row['event_id'] }}
                                    </a>
                                @else
                                    {{ $row['event_label'] ?? $row['event_name'] ?? $row['event_code'] ?? '—' }}
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                <div class="font-medium text-gray-900 dark:text-gray-100">{{ $row['title'] }}</div>
                                @if(!empty($row['context']))
                                    <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $row['context'] }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right money-nowrap">{{ $row['amount_label'] }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $row['payer'] ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $row['status'] }}</td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-2">
                                    @if(in_array($row['type'] ?? '', ['contract', 'agreement'], true))
                                        <button
                                            type="button"
                                            wire:click="copyPaymentLink('{{ $row['id'] }}')"
                                            class="text-xs font-semibold text-amber-700 hover:underline"
                                        >Link płatności</button>
                                    @endif

                                    @if($row['url'])
                                        <a href="{{ $row['url'] }}" class="text-xs font-semibold text-primary-600 hover:underline">Otwórz</a>
                                    @endif

                                    @if($row['can_complete'] ?? false)
                                        <button
                                            type="button"
                                            wire:click="beginComplete('{{ $row['id'] }}')"
                                            @if(($row['complete_mode'] ?? 'quick') === 'quick')
                                                wire:confirm="Oznaczyć tę pozycję jako opłaconą / wykonaną?"
                                            @endif
                                            class="text-xs font-semibold text-emerald-700 hover:underline"
                                        >
                                            {{ ($row['complete_mode'] ?? 'quick') === 'cost_payment_form' ? 'Zaksięguj wpłatę' : 'Wykonane' }}
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-3 py-8 text-center text-gray-500">Brak oczekujących płatności w wybranym zakresie.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if($showCostPaymentModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/50 p-4" wire:click.self="closeCostPaymentModal">
            <div class="w-full max-w-lg rounded-xl border border-gray-200 bg-white p-5 shadow-xl dark:border-gray-700 dark:bg-gray-900" role="dialog" aria-modal="true">
                <div class="mb-4">
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Zaksięguj wpłatę kosztową</h2>
                    <p class="mt-1 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $costPaymentContextTitle }}</p>
                    @if($costPaymentContextMeta)
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $costPaymentContextMeta }}</p>
                    @endif
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <label class="block text-sm sm:col-span-2">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Kwota PLN</span>
                        <input type="number" step="0.01" min="0.01" wire:model="costPaymentForm.amount_pln" class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800" />
                        @error('costPaymentForm.amount_pln') <span class="mt-1 block text-xs text-danger-600">{{ $message }}</span> @enderror
                    </label>

                    <label class="block text-sm">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Płatnik</span>
                        <select wire:model.live="costPaymentForm.paid_by" class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
                            @foreach (\App\Models\EventSettlementCost::$paidByOptions as $k => $v)
                                <option value="{{ $k }}">{{ $v }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block text-sm">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Metoda</span>
                        <select wire:model="costPaymentForm.payment_method" class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800" @disabled(($costPaymentForm['paid_by'] ?? '') === 'pilot')>
                            <option value="transfer">Przelew</option>
                            <option value="cash">Gotówka</option>
                            <option value="card">Karta</option>
                            <option value="other">Inny</option>
                        </select>
                    </label>

                    <label class="block text-sm">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Rodzaj</span>
                        <select wire:model="costPaymentForm.advance_type" class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
                            @foreach (\App\Models\EventSettlementCost::userSelectableAdvanceTypes() as $k => $v)
                                <option value="{{ $k }}">{{ $v }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block text-sm">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Data wpłaty</span>
                        <input type="date" wire:model="costPaymentForm.paid_at" class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800" />
                    </label>

                    <label class="block text-sm">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Termin (opcjonalnie)</span>
                        <input type="date" wire:model="costPaymentForm.due_date" class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800" />
                    </label>

                    <label class="block text-sm sm:col-span-2">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Nr dokumentu</span>
                        <input type="text" wire:model="costPaymentForm.document_number" class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800" />
                    </label>

                    <label class="block text-sm sm:col-span-2">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Notatka</span>
                        <textarea rows="2" wire:model="costPaymentForm.notes" class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800"></textarea>
                    </label>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <x-filament::button color="gray" wire:click="closeCostPaymentModal">Anuluj</x-filament::button>
                    <x-filament::button color="success" wire:click="saveCostPayment">Zapisz wpłatę</x-filament::button>
                </div>
            </div>
        </div>
    @endif

</x-filament-panels::page>
