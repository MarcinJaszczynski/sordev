{{-- Drawer szczegółów kosztu (EventFinance, day-insurances, program, hotele) — host page/RM z InteractsWithSettlementCostDrawer --}}
@php
    $selected = $selected ?? $this->selectedRow;
    $statusColors = $statusColors ?? [
        'paid' => 'bg-emerald-100 text-emerald-800',
        'partial' => 'bg-amber-100 text-amber-800',
        'advance' => 'bg-sky-100 text-sky-800',
        'due' => 'bg-blue-100 text-blue-800',
        'overdue' => 'bg-rose-100 text-rose-800',
        'review' => 'bg-red-200 text-red-900 ring-1 ring-red-400',
        'ok' => 'bg-emerald-100 text-emerald-800',
        'n/a' => 'bg-gray-100 text-gray-700',
    ];
    $overpaymentConfirmAcknowledged = $overpaymentConfirmAcknowledged ?? $this->overpaymentConfirmAcknowledged;
    $showCostForm = $showCostForm ?? $this->showCostForm;
    $showPaymentForm = $showPaymentForm ?? $this->showPaymentForm;
    $showPlanForm = $showPlanForm ?? $this->showPlanForm;
    $showDocumentForm = $showDocumentForm ?? $this->showDocumentForm;
    $paymentForm = $paymentForm ?? $this->paymentForm;
    $editingPaymentId = $editingPaymentId ?? $this->editingPaymentId;
@endphp
@if ($selected)
        {{-- z-50 + bg-black/50 są w CSS Filament; z-[100]/bg-black/30 nie — drawer był pod sticky toolbarem. --}}
        <div class="fixed inset-0 z-50 flex justify-end" wire:key="cost-drawer-{{ $selected['cost_id'] }}">
            <div class="absolute inset-0 bg-black/50" wire:click="closeCost"></div>
            <aside class="relative flex h-full w-full max-w-md flex-col overflow-y-auto border-l border-gray-200 bg-white p-4 shadow-xl dark:border-gray-700 dark:bg-gray-900">
                {{-- 1. Nagłówek --}}
                <div class="mb-4 flex items-start justify-between gap-2 border-b border-gray-100 pb-3 dark:border-gray-800">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $selected['name'] }}</h3>
                        <p class="mt-0.5 text-xs text-gray-500">
                            {{ $selected['source_label'] }}
                            · płatnik planowanych: <span class="font-medium text-gray-700 dark:text-gray-300">{{ $selected['paid_by_label'] }}</span>
                        </p>
                        @if (! empty($selected['contractor_details']))
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">{{ implode(' · ', $selected['contractor_details']) }}</p>
                        @elseif (! empty($selected['contractor']))
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">{{ $selected['contractor'] }}</p>
                        @endif
                    </div>
                    <button type="button" wire:click="closeCost" class="text-xs text-gray-500 hover:text-gray-800">Zamknij</button>
                </div>

                {{-- 2. Kwoty --}}
                <section class="mb-4">
                    <h4 class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-gray-500">Kwoty</h4>
                    <div class="grid grid-cols-2 gap-2 text-center text-xs">
                        <div class="rounded-lg border border-gray-100 bg-gray-50 p-2.5 dark:border-gray-800 dark:bg-gray-800/80">
                            <div class="text-[10px] uppercase tracking-wide text-gray-500">Szablon</div>
                            <div class="mt-0.5 font-semibold tabular-nums text-gray-800 dark:text-gray-100">{{ $selected['calculation_label'] }}</div>
                            @if (! empty($selected['pricing_hint']))
                                <div class="mt-1 text-[10px] leading-snug text-gray-500">{{ $selected['pricing_hint'] }}</div>
                            @endif
                        </div>
                        <button
                            type="button"
                            wire:click="startEditPlan"
                            class="rounded-lg border border-primary-100 bg-primary-50/50 p-2.5 text-center transition hover:bg-primary-50 dark:border-primary-900/40 dark:bg-primary-950/20 dark:hover:bg-primary-950/40"
                            title="Edytuj kwotę planowaną"
                        >
                            <div class="text-[10px] uppercase tracking-wide text-primary-700 dark:text-primary-300">Planowane</div>
                            <div class="mt-0.5 font-semibold tabular-nums text-primary-900 dark:text-primary-100">{{ $selected['planned_label'] }}</div>
                        </button>
                        <div class="rounded-lg border border-emerald-100 bg-emerald-50/60 p-2.5 dark:border-emerald-900/40 dark:bg-emerald-950/20">
                            <div class="text-[10px] uppercase tracking-wide text-emerald-700">Zapłacono</div>
                            <div class="mt-0.5 font-semibold tabular-nums text-emerald-900 dark:text-emerald-100">{{ $selected['paid_label'] }}</div>
                        </div>
                        <div class="rounded-lg border border-rose-100 bg-rose-50/60 p-2.5 dark:border-rose-900/40 dark:bg-rose-950/20">
                            <div class="text-[10px] uppercase tracking-wide text-rose-700">Pozostało</div>
                            <div class="mt-0.5 font-semibold tabular-nums text-rose-900 dark:text-rose-100">
                                @if (($selected['remaining_pln'] ?? 0) > 0.01)
                                    {{ $selected['remaining_label'] }}
                                @else
                                    —
                                @endif
                            </div>
                            @if (! empty($selected['next_due_label']))
                                <div class="mt-1 text-[10px] text-rose-800/80 dark:text-rose-200/80">termin {{ \Illuminate\Support\Carbon::parse($selected['next_due_label'])->format('d.m.Y') }}</div>
                            @endif
                        </div>
                    </div>

                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                        <span @class(['inline-flex rounded-full px-2 py-0.5 font-semibold', $statusColors[$selected['ui_status']] ?? 'bg-gray-100 text-gray-700'])>
                            {{ $selected['ui_status_label'] }}
                        </span>
                        @if (! empty($selected['savings_label']))
                            <span class="font-medium text-emerald-800">{{ $selected['savings_label'] }}</span>
                        @elseif (! empty($selected['overpayment_label']))
                            <span class="font-semibold text-red-800">{{ $selected['overpayment_label'] }}</span>
                        @endif
                    </div>

                    @if (! empty($selected['contractor_rollup']) && (int) ($selected['contractor_rollup']['cost_count'] ?? 0) > 1)
                        @php $rollup = $selected['contractor_rollup']; @endphp
                        <div class="mt-3 rounded-lg border border-sky-100 bg-sky-50/70 p-2.5 text-xs dark:border-sky-900/40 dark:bg-sky-950/20">
                            <div class="font-semibold text-sky-900 dark:text-sky-100">
                                {{ $rollup['contractor'] }} — łącznie ({{ (int) $rollup['cost_count'] }} poz.)
                            </div>
                            <div class="mt-1.5 grid grid-cols-3 gap-2 text-center">
                                <div>
                                    <div class="text-[10px] uppercase tracking-wide text-sky-700/80">Plan</div>
                                    <div class="font-semibold tabular-nums text-sky-950 dark:text-sky-50">{{ $rollup['planned_label'] }}</div>
                                </div>
                                <div>
                                    <div class="text-[10px] uppercase tracking-wide text-emerald-700/80">Zapłacono</div>
                                    <div class="font-semibold tabular-nums text-emerald-900 dark:text-emerald-100">{{ $rollup['paid_label'] }}</div>
                                </div>
                                <div>
                                    <div class="text-[10px] uppercase tracking-wide text-rose-700/80">Pozostało</div>
                                    <div class="font-semibold tabular-nums text-rose-900 dark:text-rose-100">
                                        @if (($rollup['remaining_pln'] ?? 0) > 0.01)
                                            {{ $rollup['remaining_label'] }}
                                        @else
                                            —
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @if (! empty($rollup['costs']))
                                <ul class="mt-2 space-y-1 border-t border-sky-100 pt-2 dark:border-sky-900/40">
                                    @foreach ($rollup['costs'] as $sibling)
                                        @php $isCurrent = (int) ($sibling['cost_id'] ?? 0) === (int) ($selected['cost_id'] ?? 0); @endphp
                                        <li>
                                            <button
                                                type="button"
                                                wire:click="openCost({{ (int) $sibling['cost_id'] }})"
                                                @class([
                                                    'flex w-full items-center justify-between gap-2 rounded px-1.5 py-1 text-left transition hover:bg-sky-100/80 dark:hover:bg-sky-900/40',
                                                    'bg-sky-100/60 font-semibold dark:bg-sky-900/30' => $isCurrent,
                                                ])
                                            >
                                                <span class="min-w-0 truncate">{{ $sibling['name'] }}</span>
                                                <span class="shrink-0 tabular-nums text-gray-600 dark:text-gray-300">{{ $sibling['remaining_label'] }}</span>
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endif

                    @if (! empty($selected['needs_overpayment_approval']))
                        <div class="mt-3 space-y-3 rounded-lg border-2 border-red-400 bg-red-50 p-3 text-sm text-red-950 shadow-sm dark:border-red-500/60 dark:bg-red-950/50 dark:text-red-50">
                            <div class="flex items-start gap-2">
                                <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-red-600 text-xs font-bold text-white">!</span>
                                <div>
                                    <p class="font-semibold">Nadpłata wymaga potwierdzenia</p>
                                    <p class="mt-0.5 text-xs font-normal opacity-90">
                                        Wpłata jest wyższa niż planowane — zatwierdź, aby oznaczyć pozycję jako opłaconą.
                                    </p>
                                </div>
                            </div>
                            <label class="flex items-start gap-2 text-xs font-normal">
                                <input
                                    type="checkbox"
                                    wire:model.live="overpaymentConfirmAcknowledged"
                                    class="mt-0.5 h-4 w-4 rounded border-red-400 text-red-700 focus:ring-red-600"
                                />
                                <span>Potwierdzam, że nadpłata względem planowanych jest prawidłowa.</span>
                            </label>
                            <button
                                type="button"
                                wire:click="approveOverpayment"
                                @disabled(! $overpaymentConfirmAcknowledged)
                                class="inline-flex w-full items-center justify-center rounded-md bg-red-700 px-3 py-2 text-sm font-bold text-white hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                Zatwierdź nadpłatę
                            </button>
                        </div>
                    @endif

                    @if (($selected['paid_by'] ?? '') === 'pilot' && (($selected['office_paid_pln'] ?? 0) > 0.01 || ($selected['pilot_paid_pln'] ?? 0) > 0.01))
                        <div class="mt-2 grid grid-cols-2 gap-1.5 text-[11px]">
                            <div class="rounded border border-gray-100 px-2 py-1 dark:border-gray-800">
                                <span class="text-gray-500">Biuro</span>
                                <div class="font-semibold tabular-nums">{{ \App\Support\MoneyFormatter::format((float) ($selected['office_paid_pln'] ?? 0), 'PLN') }}</div>
                            </div>
                            <div class="rounded border border-gray-100 px-2 py-1 dark:border-gray-800">
                                <span class="text-gray-500">Pilot</span>
                                <div class="font-semibold tabular-nums">{{ \App\Support\MoneyFormatter::format((float) ($selected['pilot_paid_pln'] ?? 0), 'PLN') }}</div>
                            </div>
                            @if (($selected['pilot_due_pln'] ?? null) !== null && ($selected['office_paid_pln'] ?? 0) > 0.01)
                                <div class="col-span-2 rounded bg-amber-50 px-2 py-1.5 font-medium text-amber-950 dark:bg-amber-950/40 dark:text-amber-100">
                                    Do zapłaty przez pilota: {{ \App\Support\MoneyFormatter::format((float) $selected['pilot_due_pln'], 'PLN') }}
                                    <span class="font-normal opacity-80">(po wpłatach biura)</span>
                                </div>
                            @endif
                        </div>
                    @endif
                </section>

                {{-- Formularze (plan / wpłata / nowy koszt) --}}
                @if ($showCostForm)
                    <div class="mb-3 space-y-2 rounded-lg border border-primary-200 bg-primary-50/40 p-3 dark:border-primary-900/40 dark:bg-primary-950/20">
                        <div class="text-xs font-semibold uppercase text-primary-800 dark:text-primary-200">Nowy koszt programu</div>
                        <p class="text-[11px] text-primary-900/80 dark:text-primary-100/80">
                            Dodaj pozycję spoza szablonu — trafi do programu i kosztów.
                        </p>
                        @include('filament.resources.event-resource.pages.partials.event-finance-cost-form')
                    </div>
                @endif

                @if ($showPaymentForm)
                    <div
                        class="mb-3 space-y-2 rounded-lg border border-primary-200 bg-primary-50/40 p-3 dark:border-primary-900/40 dark:bg-primary-950/20"
                        wire:key="payment-form-{{ $editingPaymentId ?? 'new' }}"
                        x-data="{
                            amount: @js($paymentForm['amount'] ?? ''),
                            rate: @js($paymentForm['rate'] ?? 1),
                            get pln() {
                                const a = parseFloat(this.amount) || 0;
                                const r = parseFloat(this.rate) || 0;
                                return (a * r).toLocaleString('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            }
                        }"
                    >
                        <div class="text-xs font-semibold uppercase text-primary-800 dark:text-primary-200">
                            @if ($editingPaymentId)
                                Edycja wpłaty
                            @else
                                Nowa {{ \App\Models\EventSettlementCost::advanceTypeLabel($paymentForm['advance_type'] ?? null) }}
                            @endif
                        </div>
                        <p class="text-[11px] text-primary-900/80 dark:text-primary-100/80">
                            Wybierz <strong>płatnika</strong> (kto zapłacił) i <strong>rodzaj wpłaty</strong>.
                            Zaliczka / dopłata = częściowo; dopłata całkowita = reszta po zaliczce; wpłata całkowita = jednorazowo całe planowane.
                        </p>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <div>
                                <label class="text-xs text-gray-600">Waluta</label>
                                <select wire:model.live="paymentForm.currency_id" class="fi-select-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
                                    @foreach ($this->currencyOptions as $id => $label)
                                        <option value="{{ $id }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('paymentForm.currency_id') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
                            @if (! empty($paymentForm['is_foreign']))
                                <div class="flex items-end">
                                    <label class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300">
                                        <input id="payment-convert-to-pln" type="checkbox" wire:model.live="paymentForm.convert_to_pln" class="rounded border-gray-300" />
                                        Przelicz na PLN
                                    </label>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-600">Kwota {{ $paymentForm['currency_symbol'] ?? '' }}</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        wire:model.live="paymentForm.amount"
                                        x-on:input="amount = $event.target.value"
                                        class="fi-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800"
                                    />
                                    @error('paymentForm.amount') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                                </div>
                                @if (! empty($paymentForm['convert_to_pln']))
                                    <div>
                                        <label class="text-xs text-gray-600">Kurs (→ PLN)</label>
                                        <input
                                            type="number"
                                            step="0.0001"
                                            wire:model.live="paymentForm.rate"
                                            x-on:input="rate = $event.target.value"
                                            class="fi-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800"
                                        />
                                        @error('paymentForm.rate') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="text-xs text-gray-600">Ekwiwalent PLN</label>
                                        <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium tabular-nums dark:border-gray-700 dark:bg-gray-900" x-text="pln + ' PLN'"></div>
                                    </div>
                                @endif
                            @else
                                <div>
                                    <label class="text-xs text-gray-600">Kwota PLN</label>
                                    <input type="number" step="0.01" wire:model.live="paymentForm.amount_pln" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800" />
                                    @error('paymentForm.amount_pln') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                                </div>
                            @endif
                            <div>
                                <label class="text-xs text-gray-600">Płatnik</label>
                                <select wire:model="paymentForm.paid_by" class="fi-select-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
                                    @foreach (\App\Models\EventSettlementCost::$paidByOptions as $k => $v)
                                        <option value="{{ $k }}">{{ $v }}</option>
                                    @endforeach
                                </select>
                                @error('paymentForm.paid_by') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="text-xs text-gray-600">Rodzaj wpłaty</label>
                                <select wire:model="paymentForm.advance_type" class="fi-select-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
                                    @foreach (\App\Models\EventSettlementCost::userSelectableAdvanceTypes() as $k => $v)
                                        <option value="{{ $k }}">{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-xs text-gray-600">Metoda</label>
                                <select wire:model="paymentForm.payment_method" class="fi-select-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
                                    @foreach (\App\Models\EventSettlementCost::$paymentMethods as $k => $v)
                                        <option value="{{ $k }}">{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-xs text-gray-600">Data wpłaty</label>
                                <input type="date" wire:model.live="paymentForm.paid_at" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800" />
                                <p class="mt-0.5 text-[11px] text-gray-500">Wyczyść, jeśli tylko planujesz termin (bez księgowania).</p>
                                @error('paymentForm.paid_at') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="text-xs text-gray-600">Termin (płatne do)</label>
                                <input type="date" wire:model="paymentForm.due_date" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800" />
                                <p class="mt-0.5 text-[11px] text-gray-500">Bez daty wpłaty = zaplanowana, świeci się na liście.</p>
                                @error('paymentForm.due_date') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label class="text-xs text-gray-600">Nr dokumentu / FV</label>
                                <input type="text" wire:model="paymentForm.document_number" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800" />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="text-xs text-gray-600">Notatka</label>
                                <textarea wire:model="paymentForm.notes" rows="2" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800"></textarea>
                            </div>
                            @if ($this->drawerReservations->isNotEmpty())
                                <div class="sm:col-span-2">
                                    <label class="text-xs text-gray-600">Podczep pod rezerwację</label>
                                    <select wire:model="paymentForm.reservation_id" class="fi-select-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
                                        <option value="">— bez rezerwacji —</option>
                                        @foreach ($this->drawerReservations as $drawerReservation)
                                            <option value="{{ $drawerReservation->id }}">
                                                {{ $drawerReservation->booking_reference ?: ('#'.$drawerReservation->id) }}
                                                · {{ \App\Models\Reservation::$statuses[$drawerReservation->status] ?? $drawerReservation->status }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <p class="mt-0.5 text-[11px] text-gray-500">Kolejna zaliczka albo dopłata to osobny wiersz — suma schodzi z pozostałej kwoty.</p>
                                </div>
                            @endif
                        </div>
                        @if ($this->paymentFormNeedsOverpaymentAck())
                            <div class="mt-2 space-y-2 rounded-lg border-2 border-red-400 bg-red-50 p-3 text-sm text-red-950 dark:border-red-500/60 dark:bg-red-950/50 dark:text-red-50">
                                <p class="font-semibold">
                                    Nadpłata względem planu:
                                    {{ \App\Support\MoneyFormatter::format($this->paymentFormProjectedOverpaymentPln(), 'PLN') }}
                                </p>
                                <p class="text-xs opacity-90">
                                    Kwota wpłaty jest wyższa niż planowane. Potwierdź, aby oznaczyć pozycję jako opłaconą.
                                </p>
                                <label class="flex items-start gap-2 text-xs font-normal">
                                    <input
                                        type="checkbox"
                                        wire:model.live="paymentForm.acknowledge_overpayment"
                                        class="mt-0.5 h-4 w-4 rounded border-red-400 text-red-700 focus:ring-red-600"
                                    />
                                    <span>Potwierdzam, że nadpłata względem planowanych jest prawidłowa.</span>
                                </label>
                                @error('paymentForm.acknowledge_overpayment')
                                    <p class="text-xs text-rose-700">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif
                        <div class="flex gap-2 pt-1">
                            <x-filament::button size="sm" wire:click="savePayment" wire:loading.attr="disabled">
                                {{ $editingPaymentId ? 'Zapisz zmiany' : 'Zapisz wpłatę' }}
                            </x-filament::button>
                            <x-filament::button size="sm" color="gray" wire:click="$set('showPaymentForm', false); $set('editingPaymentId', null)">Anuluj</x-filament::button>
                        </div>
                    </div>
                @endif

                <section class="mb-4 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <h4 class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Planowane</h4>
                        @unless ($showPlanForm)
                            <button type="button" class="text-xs font-medium text-primary-600 hover:underline" wire:click="startEditPlan">
                                Edytuj
                            </button>
                        @endunless
                    </div>

                    @if ($showPlanForm)
                        <div class="mb-1 space-y-2 rounded-lg border border-primary-200 bg-primary-50/40 p-3 dark:border-primary-900/40 dark:bg-primary-950/20">
                            <div class="text-xs font-semibold uppercase text-primary-800 dark:text-primary-200">
                                {{ ! empty($selected['is_program_point']) ? 'Edycja kwoty planowanej' : 'Edycja planowanych' }}
                            </div>
                            @if (! empty($selected['is_program_point']))
                                @php
                                    $planForm = $planForm ?? $this->planForm;
                                    $currencyOptions = $this->currencyOptions;
                                    $groupSize = (int) ($planForm['group_size'] ?? 1);
                                    $unitPriceLabel = \App\Services\ProgramPointPricingCalculator::unitPriceLabel($groupSize);
                                    $planCurrencyId = $planForm['currency_id'] ?? null;
                                    $planCurrency = $planCurrencyId
                                        ? \App\Models\Currency::query()->find($planCurrencyId)
                                        : null;
                                    $planIsForeign = \App\Support\CurrencyAmountDisplay::isForeignCurrency($planCurrencyId);
                                    $planConvert = (bool) ($planForm['convert_to_pln'] ?? true);
                                    // Planowane = planned_price (edytowalny); calculated_price to podpowiedź z formuły.
                                    $planAmount = (float) ($planForm['planned_price'] ?? $planForm['calculated_price'] ?? 0);
                                    $planAmountPreview = $planAmount > 0
                                        ? \App\Support\CurrencyAmountDisplay::format(
                                            $planAmount,
                                            $planCurrency,
                                            $planIsForeign && $planConvert,
                                        )
                                        : null;
                                @endphp
                                <p class="text-[11px] text-primary-900/80 dark:text-primary-100/80">
                                    Tu ustawiasz <strong>kwotę planowaną</strong> (to, ile faktycznie planujecie zapłacić).
                                    Parametry poniżej tylko wyliczają podpowiedź — <strong>nie zmieniają ceny szablonu</strong> w tabeli.
                                </p>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <div class="sm:col-span-2">
                                        <label class="text-xs text-gray-600">Kwota planowana (suma)</label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            wire:model="planForm.planned_price"
                                            class="fi-input w-full rounded-lg border-gray-300 text-sm font-semibold dark:border-gray-600 dark:bg-gray-900"
                                        />
                                        @if ($planAmountPreview)
                                            <p class="mt-0.5 text-[11px] text-gray-600">Podgląd: <span class="font-medium tabular-nums">{{ $planAmountPreview }}</span></p>
                                        @endif
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-600">{{ $unitPriceLabel }} (podpowiedź ze szablonu)</label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            wire:model.blur="planForm.unit_price"
                                            wire:change="recalculateProgramPointPlanTotals"
                                            class="fi-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900"
                                        />
                                        @error('planForm.unit_price') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-600">Wielkość grupy</label>
                                        <input
                                            type="number"
                                            step="1"
                                            wire:model.blur="planForm.group_size"
                                            wire:change="recalculateProgramPointPlanTotals"
                                            class="fi-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900"
                                        />
                                        <p class="mt-0.5 text-[10px] text-gray-500">1 = za osobę, &gt;1 = za grupę, 0 = za sztukę</p>
                                        @error('planForm.group_size') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                                    </div>
                                    @if ($groupSize <= 0)
                                        <div>
                                            <label class="text-xs text-gray-600">Ilość (stała)</label>
                                            <input
                                                type="number"
                                                step="1"
                                                wire:model.blur="planForm.quantity"
                                                wire:change="recalculateProgramPointPlanTotals"
                                                class="fi-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900"
                                            />
                                            @error('planForm.quantity') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                                        </div>
                                    @endif
                                    <div>
                                        <label class="text-xs text-gray-600">Waluta</label>
                                        <select wire:model.live="planForm.currency_id" class="fi-select-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900">
                                            @foreach ($currencyOptions as $id => $label)
                                                <option value="{{ $id }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    @if ($planIsForeign)
                                        <div class="sm:col-span-2 space-y-1 pt-1">
                                            <div class="flex items-center gap-2">
                                                <input id="plan-convert-to-pln" type="checkbox" wire:model.live="planForm.convert_to_pln" class="rounded border-gray-300" />
                                                <label for="plan-convert-to-pln" class="text-xs text-gray-700 dark:text-gray-200">Przelicz planowane na PLN</label>
                                            </div>
                                            @if ($planAmountPreview)
                                                <p class="text-[11px] text-gray-600 dark:text-gray-300">
                                                    Podgląd planowanych: <span class="font-medium tabular-nums">{{ $planAmountPreview }}</span>
                                                    @if ($planConvert)
                                                        <span class="text-gray-500">· wchodzi do sum PLN</span>
                                                    @else
                                                        <span class="text-gray-500">· poza sumą PLN (tylko {{ \App\Support\CurrencyAmountDisplay::symbol($planCurrency) }})</span>
                                                    @endif
                                                </p>
                                            @endif
                                        </div>
                                    @endif
                                    <div class="sm:col-span-2 flex items-center gap-2">
                                        <input
                                            id="plan-include-gratis"
                                            type="checkbox"
                                            wire:model.live="planForm.include_gratis_in_cost"
                                            wire:change="recalculateProgramPointPlanTotals"
                                            class="rounded border-gray-300"
                                        />
                                        <label for="plan-include-gratis" class="text-xs text-gray-700 dark:text-gray-200">
                                            Liczyć z opiekunami / gratisami
                                        </label>
                                    </div>
                                    <div class="sm:col-span-2 flex items-center gap-2">
                                        <input
                                            id="plan-include-pilot"
                                            type="checkbox"
                                            wire:model.live="planForm.include_pilot_in_cost"
                                            wire:change="recalculateProgramPointPlanTotals"
                                            class="rounded border-gray-300"
                                        />
                                        <label for="plan-include-pilot" class="text-xs text-gray-700 dark:text-gray-200">
                                            Liczyć z pilotem
                                        </label>
                                    </div>
                                    <div class="sm:col-span-2 flex items-center gap-2">
                                        <input
                                            id="plan-include-driver"
                                            type="checkbox"
                                            wire:model.live="planForm.include_driver_in_cost"
                                            wire:change="recalculateProgramPointPlanTotals"
                                            class="rounded border-gray-300"
                                        />
                                        <label for="plan-include-driver" class="text-xs text-gray-700 dark:text-gray-200">
                                            Liczyć z kierowcą
                                        </label>
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-600">Płatnik planowanych</label>
                                        <select wire:model="planForm.paid_by" class="fi-select-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900">
                                            @foreach (\App\Models\EventSettlementCost::$paidByOptions as $k => $v)
                                                <option value="{{ $k }}">{{ $v }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="text-xs text-gray-600">Kontrahent</label>
                                        <select wire:model="planForm.contractor_id" class="fi-select-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900">
                                            <option value="">— bez kontrahenta —</option>
                                            @foreach ($this->contractorOptions as $id => $label)
                                                <option value="{{ $id }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-600">Termin</label>
                                        <input type="date" wire:model="planForm.due_date" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900" />
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="text-xs text-gray-600">Notatka</label>
                                        <textarea wire:model="planForm.notes" rows="2" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900"></textarea>
                                    </div>
                                </div>
                            @else
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <div>
                                        <label class="text-xs text-gray-600">Planowane PLN</label>
                                        <input type="number" step="0.01" wire:model="planForm.planned_amount_pln" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900" />
                                        @error('planForm.planned_amount_pln') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-600">Płatnik</label>
                                        <select wire:model="planForm.paid_by" class="fi-select-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900">
                                            @foreach (\App\Models\EventSettlementCost::$paidByOptions as $k => $v)
                                                <option value="{{ $k }}">{{ $v }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="text-xs text-gray-600">Kontrahent</label>
                                        <select wire:model="planForm.contractor_id" class="fi-select-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900">
                                            <option value="">— bez kontrahenta —</option>
                                            @foreach ($this->contractorOptions as $id => $label)
                                                <option value="{{ $id }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-600">Termin</label>
                                        <input type="date" wire:model="planForm.due_date" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900" />
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="text-xs text-gray-600">Notatka</label>
                                        <textarea wire:model="planForm.notes" rows="2" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900"></textarea>
                                    </div>
                                </div>
                            @endif
                            <div class="flex gap-2 pt-1">
                                <x-filament::button size="sm" wire:click="savePlan">Zapisz planowane</x-filament::button>
                                <x-filament::button size="sm" color="gray" wire:click="$set('showPlanForm', false)">Anuluj</x-filament::button>
                            </div>
                        </div>
                    @else
                        <div class="rounded-lg border border-gray-100 bg-gray-50/50 px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-800/40">
                            <div class="flex justify-between gap-2">
                                <span class="font-medium">Kwota planowana</span>
                                <span class="font-semibold tabular-nums text-primary-700 dark:text-primary-300">{{ $selected['planned_label'] }}</span>
                            </div>
                            @if (! empty($selected['is_program_point']))
                                <div class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-xs text-gray-600 dark:text-gray-300">
                                    @if (! empty($selected['plan_unit_price_label']))
                                        <div>
                                            <span class="text-gray-500">{{ $selected['plan_unit_price_label'] }}</span>
                                            <div class="font-medium tabular-nums text-gray-800 dark:text-gray-100">
                                                {{ number_format((float) ($selected['plan_unit_price'] ?? 0), 2, ',', ' ') }}
                                                {{ $selected['planned_currency_symbol'] ?? 'PLN' }}
                                            </div>
                                        </div>
                                    @endif
                                    <div>
                                        <span class="text-gray-500">Wielkość grupy</span>
                                        <div class="font-medium text-gray-800 dark:text-gray-100">
                                            @php $gs = (int) ($selected['plan_group_size'] ?? 1); @endphp
                                            @if ($gs <= 0)
                                                za sztukę
                                                @if (! empty($selected['plan_quantity']))
                                                    · {{ (int) $selected['plan_quantity'] }} szt.
                                                @endif
                                            @elseif ($gs === 1)
                                                1 (za osobę)
                                            @else
                                                {{ $gs }} os.
                                            @endif
                                        </div>
                                    </div>
                                    <div>
                                        <span class="text-gray-500">Waluta</span>
                                        <div class="font-medium text-gray-800 dark:text-gray-100">
                                            {{ $selected['planned_currency_symbol'] ?? 'PLN' }}
                                            @if (($selected['planned_currency_symbol'] ?? 'PLN') !== 'PLN')
                                                @if (! empty($selected['planned_convert_to_pln']))
                                                    · przeliczanie PLN
                                                @else
                                                    · bez przeliczenia PLN
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                    <div>
                                        <span class="text-gray-500">Szablon</span>
                                        <div class="font-medium tabular-nums text-gray-800 dark:text-gray-100">{{ $selected['calculation_label'] }}</div>
                                    </div>
                                </div>
                            @endif
                            <div class="mt-2 text-xs text-gray-500">
                                płatnik: <span class="font-medium text-gray-700 dark:text-gray-300">{{ $selected['paid_by_label'] }}</span>
                                @if (! empty($selected['contractor']))
                                    · {{ $selected['contractor'] }}
                                @endif
                                @if (! empty($selected['next_due_label']))
                                    · termin {{ \Illuminate\Support\Carbon::parse($selected['next_due_label'])->format('d.m.Y') }}
                                @endif
                            </div>
                            @if (! empty($selected['notes']))
                                <div class="mt-1 text-xs text-gray-500">{{ $selected['notes'] }}</div>
                            @endif
                            @if (! empty($selected['pricing_hint']))
                                <div class="mt-2 whitespace-pre-line text-[11px] leading-snug text-gray-500">{{ $selected['pricing_hint'] }}</div>
                            @endif
                        </div>
                    @endif
                </section>

                <section class="mb-4 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <h4 class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Wpłaty</h4>
                        @unless ($showPaymentForm)
                            <x-filament::button size="sm" wire:click="openNewPaymentForm">+ Dodaj</x-filament::button>
                        @endunless
                    </div>
                    @if (empty($selected['payments']))
                        <p class="text-sm text-gray-500">Brak wpłat. Dodaj zaliczkę lub płatność końcową.</p>
                    @else
                        <ul class="space-y-2">
                            @foreach ($selected['payments'] as $payment)
                                <li class="rounded-lg border border-gray-100 bg-gray-50/50 px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-800/40">
                                    <div class="flex justify-between gap-2">
                                        <span class="font-medium">
                                            {{ $payment['advance_type_label'] }}
                                            <span class="ml-1 text-[10px] font-normal text-gray-500">· {{ $payment['paid_by_label'] }}</span>
                                        </span>
                                        <span class="font-semibold tabular-nums text-emerald-700">{{ $payment['amount_label'] }}</span>
                                    </div>
                                    <div class="mt-0.5 text-xs text-gray-500">
                                        @if (! empty($payment['paid_at']))
                                            data {{ $payment['paid_at'] }}
                                        @elseif (! empty($payment['due_date']))
                                            do zapłaty · termin {{ $payment['due_date'] }}
                                        @else
                                            bez daty wpłaty
                                        @endif
                                        · {{ $payment['method_label'] }}
                                        @if (! empty($payment['paid_at']) && ! empty($payment['due_date']))
                                            · termin {{ $payment['due_date'] }}
                                        @endif
                                        @if (! empty($payment['document_number']))
                                            · FV {{ $payment['document_number'] }}
                                        @endif
                                        @if (! empty($payment['reservation_label']))
                                            · rez. {{ $payment['reservation_label'] }}
                                        @endif
                                        @if (! empty($payment['status_label']))
                                            · {{ $payment['status_label'] }}
                                        @endif
                                    </div>
                                    <div class="mt-1.5 flex gap-3 text-xs">
                                        <button type="button" class="text-primary-600 hover:underline" wire:click="startEditPayment({{ (int) $payment['id'] }})">Edytuj</button>
                                        <button type="button" class="text-rose-600 hover:underline" wire:click="deletePayment({{ (int) $payment['id'] }})" wire:confirm="Usunąć tę wpłatę?">Usuń</button>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                @if (! empty($selected['supports_reservation']) || ! empty($selected['is_program_point']))
                    @php
                        $drawerReservations = $this->drawerReservations;
                        $showReservationForm = $showReservationForm ?? $this->showReservationForm;
                        $reservationForm = $reservationForm ?? $this->reservationForm;
                    @endphp
                    <div class="mb-4 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <h4 class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Rezerwacja</h4>
                            <button type="button" class="text-xs font-medium text-primary-600 hover:underline" wire:click="startEditReservation">
                                {{ $drawerReservations->isNotEmpty() ? 'Edytuj' : '+ Dodaj' }}
                            </button>
                        </div>
                        <p class="mb-2 text-[11px] text-gray-500">Ta sama rezerwacja co w Operacje → Rezerwacje i na liście globalnej.</p>
                        @if (! empty($reservationForm['coverage_label']))
                            <p class="mb-2 text-[11px] font-medium text-gray-600 dark:text-gray-300">{{ $reservationForm['coverage_label'] }}</p>
                        @endif

                        @if ($showReservationForm)
                            <div class="mb-3 space-y-2 rounded-lg border border-emerald-200 bg-emerald-50/40 p-3 dark:border-emerald-900/40 dark:bg-emerald-950/20">
                                <div class="text-xs font-semibold uppercase text-emerald-800 dark:text-emerald-200">Rezerwacja u kontrahenta</div>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <div class="sm:col-span-2">
                                        <label class="text-xs text-gray-600">Kontrahent</label>
                                        @if (filled($reservationForm['contractor_id']) && filled($this->reservationContractorLabel))
                                            <div class="mt-0.5 flex items-center justify-between gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900">
                                                <span class="min-w-0 truncate">{{ $this->reservationContractorLabel }}</span>
                                                <button type="button" class="shrink-0 text-xs font-medium text-primary-600 hover:underline" wire:click="clearReservationContractor">
                                                    Zmień
                                                </button>
                                            </div>
                                        @else
                                            <div
                                                class="relative mt-0.5"
                                                x-data="{ open: @entangle('showReservationContractorSearchResults') }"
                                                @click.outside="open = false"
                                            >
                                                <div class="relative">
                                                    <input
                                                        type="search"
                                                        wire:model.live.debounce.300ms="reservationContractorSearch"
                                                        @focus="if (Object.keys($wire.reservationContractorSearchResults).length) { open = true }"
                                                        placeholder="Wyszukaj po nazwie, mieście, NIP…"
                                                        autocomplete="off"
                                                        class="fi-input w-full rounded-lg border-gray-300 py-2 pl-8 pr-3 text-sm dark:border-gray-600 dark:bg-gray-900"
                                                    />
                                                    <x-heroicon-m-magnifying-glass class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                                                </div>
                                                <p class="mt-0.5 text-[11px] text-gray-500">Wpisz min. 2 znaki — wyniki pojawią się automatycznie.</p>
                                                @if ($this->reservationContractorHasTypeFilter())
                                                    <label class="mt-1 inline-flex items-center gap-2 text-[11px] text-gray-600 dark:text-gray-300">
                                                        <input
                                                            type="checkbox"
                                                            wire:model.live="reservationContractorSearchAll"
                                                            class="rounded border-gray-400 text-primary-600 shadow-sm focus:ring-primary-500"
                                                        />
                                                        Szukaj we wszystkich kontrahentach
                                                    </label>
                                                @endif
                                                @if ($showReservationContractorSearchResults ?? $this->showReservationContractorSearchResults)
                                                    <div
                                                        x-show="open"
                                                        x-cloak
                                                        class="absolute z-40 mt-1 w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-900"
                                                    >
                                                        @if ($this->reservationContractorSearchResults !== [])
                                                            <ul class="max-h-56 divide-y divide-gray-100 overflow-y-auto dark:divide-gray-800" role="listbox">
                                                                @foreach ($this->reservationContractorSearchResults as $contractorId => $contractorLabel)
                                                                    <li>
                                                                        <button
                                                                            type="button"
                                                                            wire:click="selectReservationContractor({{ (int) $contractorId }})"
                                                                            class="flex w-full px-3 py-2 text-left text-sm text-gray-900 transition hover:bg-primary-50 dark:text-gray-100 dark:hover:bg-primary-950/40"
                                                                            role="option"
                                                                        >
                                                                            {{ $contractorLabel }}
                                                                        </button>
                                                                    </li>
                                                                @endforeach
                                                            </ul>
                                                        @else
                                                            <div class="px-3 py-2.5 text-sm text-gray-600 dark:text-gray-300">
                                                                Brak dopasowań dla „{{ $this->reservationContractorSearch }}”.
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="text-xs text-gray-600">Nr potwierdzenia dostawcy</label>
                                        <input type="text" wire:model="reservationForm.booking_reference" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900" />
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-600">Status</label>
                                        <select wire:model="reservationForm.status" class="fi-select-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900">
                                            @foreach (\App\Models\Reservation::$statuses as $k => $v)
                                                <option value="{{ $k }}">{{ $v }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-600">Kwota rezerwacji</label>
                                        <input type="number" step="0.01" min="0" wire:model="reservationForm.reserved_amount" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900" />
                                        @if (! empty($reservationForm['amount_hint']))
                                            <p class="mt-0.5 text-[11px] text-gray-500">Podstawiono: {{ $reservationForm['amount_hint'] }}</p>
                                        @endif
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-600">Potwierdzić do</label>
                                        <input type="date" wire:model="reservationForm.confirm_by" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900" />
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-600">Potwierdzono</label>
                                        <input type="date" wire:model="reservationForm.confirmed_at" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900" />
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-600">Zaliczka do</label>
                                        <input type="date" wire:model="reservationForm.deposit_due_at" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900" />
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-600">Zaliczka zapłacona</label>
                                        <input type="date" wire:model="reservationForm.deposit_paid_at" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900" />
                                        <p class="mt-0.5 text-[11px] text-gray-500">Jeśli nie ma jeszcze wpłat, zapisze pierwszą zaliczkę. Kolejne raty dodajesz w sekcji Wpłaty.</p>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="text-xs text-gray-600">Notatki biura</label>
                                        <textarea wire:model="reservationForm.office_notes" rows="3" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900"></textarea>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="text-xs text-gray-600">Pliki (PDF, JPG…)</label>
                                        <input type="file" multiple wire:model="reservationAttachmentFiles" class="block w-full text-sm" />
                                        @error('reservationAttachmentFiles') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                                        @error('reservationAttachmentFiles.*') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                                        <div wire:loading wire:target="reservationAttachmentFiles" class="text-xs text-gray-500">Wgrywanie…</div>
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                                    @if (! empty($reservationForm['reservation_id']))
                                        <x-filament::button
                                            size="sm"
                                            color="danger"
                                            wire:click="deleteReservation"
                                            wire:confirm="Usunąć tę rezerwację z punktu? Numer potwierdzenia będzie można wpisać na innym klocku."
                                            wire:loading.attr="disabled"
                                        >
                                            Usuń rezerwację
                                        </x-filament::button>
                                    @else
                                        <span></span>
                                    @endif
                                    <div class="flex gap-2">
                                        <x-filament::button size="sm" color="gray" wire:click="$set('showReservationForm', false)">Anuluj</x-filament::button>
                                        <x-filament::button size="sm" wire:click="saveReservation" wire:loading.attr="disabled">Zapisz rezerwację</x-filament::button>
                                    </div>
                                </div>
                            </div>
                        @elseif ($drawerReservations->isEmpty())
                            <p class="text-sm text-gray-500">Brak rezerwacji — dodaj potwierdzenie, terminy i załączniki w jednym miejscu.</p>
                        @else
                            <ul class="space-y-2">
                                @foreach ($drawerReservations as $reservation)
                                    @php
                                        $reservationOfficeNotes = trim(strip_tags((string) ($reservation->office_notes ?? '')));
                                    @endphp
                                    <li class="rounded-lg border border-gray-100 px-3 py-2 text-sm dark:border-gray-800">
                                        <div class="font-medium">
                                            {{ $reservation->booking_reference ?: ('#'.$reservation->id) }}
                                            · {{ \App\Models\Reservation::$statuses[$reservation->status] ?? $reservation->status }}
                                        </div>
                                        <div class="mt-0.5 text-xs text-gray-500">
                                            {{ $reservation->contractor?->name ?? 'Bez kontrahenta' }}
                                            @if ($reservation->reserved_amount !== null)
                                                · {{ \App\Support\ReservationPricingLabel::format($reservation) }}
                                            @endif
                                        </div>
                                        <div class="mt-0.5 text-xs text-gray-500">
                                            @foreach (\App\Support\Reservations\ReservationWorkflowDisplay::workflowLines($reservation) as $line)
                                                <div>{{ $line }}</div>
                                            @endforeach
                                        </div>
                                        @if ($reservationOfficeNotes !== '')
                                            <div class="mt-1.5 text-xs text-gray-700 dark:text-gray-300">
                                                <span class="font-medium text-gray-500">Notatki biura:</span>
                                                {{ \Illuminate\Support\Str::limit($reservationOfficeNotes, 160) }}
                                            </div>
                                        @endif
                                        <div class="mt-1.5 flex gap-3 text-xs">
                                            <button type="button" class="text-primary-600 hover:underline" wire:click="startEditReservation">Edytuj</button>
                                            <button
                                                type="button"
                                                class="text-rose-600 hover:underline"
                                                wire:click="deleteReservation({{ (int) $reservation->id }})"
                                                wire:confirm="Usunąć tę rezerwację z punktu? Numer potwierdzenia będzie można wpisać na innym klocku."
                                            >Usuń</button>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif

                <section class="mb-4 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <h4 class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Dokumenty / faktury</h4>
                        <button type="button" class="text-xs font-medium text-primary-600 hover:underline" wire:click="startAddDocument">+ plik</button>
                    </div>

                    @if ($showDocumentForm)
                        <div class="mb-3 space-y-2 rounded-lg border border-sky-200 bg-sky-50/50 p-3 dark:border-sky-900/40 dark:bg-sky-950/20">
                            <div class="text-xs font-semibold uppercase text-sky-800 dark:text-sky-200">Nowy dokument</div>
                            <div>
                                <label class="text-xs text-gray-600">Typ</label>
                                <select wire:model="documentForm.document_type" class="fi-select-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
                                    @foreach (\App\Models\EventSettlementDocument::$documentTypes as $k => $v)
                                        <option value="{{ $k }}">{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-xs text-gray-600">Numer (opcjonalnie)</label>
                                <input type="text" wire:model="documentForm.document_number" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800" />
                            </div>
                            <div>
                                <label class="text-xs text-gray-600">Pliki (PDF, JPG…)</label>
                                <input type="file" multiple wire:model="documentFiles" class="block w-full text-sm" />
                                @error('documentFiles') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                                @error('documentFiles.*') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                                <div wire:loading wire:target="documentFiles" class="text-xs text-gray-500">Wgrywanie…</div>
                            </div>
                            <div>
                                <label class="text-xs text-gray-600">Notatka</label>
                                <textarea wire:model="documentForm.notes" rows="2" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800"></textarea>
                            </div>
                            <div class="space-y-1.5 rounded-md border border-sky-100 bg-white/70 p-2 dark:border-sky-900/40 dark:bg-gray-900/40">
                                <div class="text-[11px] font-semibold uppercase tracking-wide text-sky-800 dark:text-sky-200">Udostępnij w pakietach</div>
                                <label class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-200">
                                    <input type="checkbox" wire:model="documentForm.attach_to_pilot_pdf" class="rounded border-gray-300 text-primary-600" />
                                    Pakiet pilota (panel + PDF)
                                </label>
                                <label class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-200">
                                    <input type="checkbox" wire:model="documentForm.attach_to_hotel_pdf" class="rounded border-gray-300 text-primary-600" />
                                    Pakiet hotelu
                                </label>
                                <label class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-200">
                                    <input type="checkbox" wire:model="documentForm.attach_to_driver_pdf" class="rounded border-gray-300 text-primary-600" />
                                    Pakiet kierowcy
                                </label>
                                <label class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-200">
                                    <input type="checkbox" wire:model="documentForm.attach_to_folder_pdf" class="rounded border-gray-300 text-primary-600" />
                                    Pakiet teczki
                                </label>
                            </div>
                            <div class="flex gap-2">
                                <x-filament::button size="sm" wire:click="saveDocument" wire:loading.attr="disabled">Zapisz dokument</x-filament::button>
                                <x-filament::button size="sm" color="gray" wire:click="$set('showDocumentForm', false)">Anuluj</x-filament::button>
                            </div>
                        </div>
                    @endif

                    @if (empty($selected['documents']))
                        <p class="text-sm text-gray-500">Brak pliku faktury / dowodu — dodaj PDF lub JPG gdy będzie dostępny.</p>
                    @else
                        <ul class="space-y-2">
                            @foreach ($selected['documents'] as $doc)
                                <li class="rounded-lg border border-gray-100 bg-gray-50/50 px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-800/40">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <div class="font-medium">{{ $doc['type_label'] }}</div>
                                            @if ($doc['number'])
                                                <div class="text-xs text-gray-500">Nr: {{ $doc['number'] }}</div>
                                            @endif
                                            @php
                                                $shareBadges = collect([
                                                    ! empty($doc['attach_to_pilot_pdf']) ? 'pilot' : null,
                                                    ! empty($doc['attach_to_hotel_pdf']) ? 'hotel' : null,
                                                    ! empty($doc['attach_to_driver_pdf']) ? 'kierowca' : null,
                                                    ! empty($doc['attach_to_folder_pdf']) ? 'teczka' : null,
                                                ])->filter()->values();
                                            @endphp
                                            @if ($shareBadges->isNotEmpty())
                                                <div class="mt-1 flex flex-wrap gap-1">
                                                    @foreach ($shareBadges as $badge)
                                                        <span class="inline-flex rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-medium text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">{{ $badge }}</span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                        <button
                                            type="button"
                                            class="text-xs text-rose-600 hover:underline"
                                            wire:click="deleteDocument({{ (int) $doc['id'] }})"
                                            wire:confirm="Usunąć dokument z tej pozycji?"
                                        >Usuń</button>
                                    </div>
                                    @if (! empty($doc['files']))
                                        <ul class="mt-1 space-y-0.5">
                                            @foreach ($doc['files'] as $file)
                                                <li>
                                                    <a href="{{ $file['url'] }}" target="_blank" class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-1.5 py-0.5 text-xs font-medium text-emerald-800 underline-offset-2 hover:bg-emerald-100 hover:underline dark:bg-emerald-950/40 dark:text-emerald-200">
                                                        {{ $file['name'] }}
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <p class="mt-1 text-xs text-amber-700">Dokument bez wgranego pliku.</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                <details class="rounded-lg border border-gray-100 p-3 text-sm dark:border-gray-800">
                    <summary class="cursor-pointer text-xs font-semibold uppercase tracking-wide text-gray-500">Zaawansowane</summary>
                    <dl class="mt-2 space-y-1 text-xs text-gray-600 dark:text-gray-400">
                        <div class="flex justify-between gap-2"><dt>ID kosztu</dt><dd>{{ $selected['cost_id'] }}</dd></div>
                        <div class="flex justify-between gap-2"><dt>Grupa</dt><dd>{{ $selected['finance_group_id'] ?? '—' }}</dd></div>
                        <div class="flex justify-between gap-2"><dt>Źródło</dt><dd>{{ $selected['source_type'] }}</dd></div>
                        <div class="flex justify-between gap-2"><dt>Różnica</dt><dd>{{ $selected['remaining_label'] }}</dd></div>
                    </dl>
                </details>
            </aside>
        </div>
@elseif ($this->selectedCostId)
        <div class="fixed inset-0 z-50 flex justify-end" wire:key="cost-drawer-missing-{{ $this->selectedCostId }}">
            <div class="absolute inset-0 bg-black/50" wire:click="closeCost"></div>
            <aside class="relative flex h-full w-full max-w-md flex-col overflow-y-auto border-l border-gray-200 bg-white p-4 shadow-xl dark:border-gray-700 dark:bg-gray-900">
                <div class="mb-3 flex items-start justify-between gap-2">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Płatności</h3>
                        <p class="text-xs text-gray-500">Nie udało się wczytać pozycji kosztu (#{{ $this->selectedCostId }}).</p>
                    </div>
                    <button type="button" wire:click="closeCost" class="text-xs text-gray-500 hover:text-gray-800">Zamknij</button>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Odśwież stronę albo otwórz tę pozycję w <strong>Finanse → Koszty</strong>.
                </p>
            </aside>
        </div>
@endif
