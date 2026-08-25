<x-filament-panels::page>
    @include('filament.resources.event-resource.components.finance-sub-navigation', ['record' => $this->record])
    @include('filament.components.filament-sortable-boot')

    @php
        $overview = $this->financeOverview;
        $totals = $overview['totals'] ?? [];
        $counts = $overview['counts'] ?? [];
        $groups = $overview['groups'] ?? [];
        $selected = $this->selectedRow;
        $hasSettlement = ($overview['settlement_id'] ?? null) !== null;
        $filters = [
            \App\Services\EventFinanceOverviewService::FILTER_ALL => ['label' => 'Wszystkie', 'tip' => 'Pokaż wszystkie pozycje kosztowe.'],
            \App\Services\EventFinanceOverviewService::FILTER_OFFICE => ['label' => 'Biuro', 'tip' => 'Koszty płatne przez biuro (nie przez pilota).'],
            \App\Services\EventFinanceOverviewService::FILTER_PILOT => ['label' => 'Pilot', 'tip' => 'Koszty opłacane z gotówki / przez pilota.'],
            \App\Services\EventFinanceOverviewService::FILTER_OVERDUE => ['label' => 'Po terminie', 'tip' => 'Pozycje z przekroczonym terminem płatności.'],
            \App\Services\EventFinanceOverviewService::FILTER_NO_DOCUMENT => ['label' => 'Bez dokumentu', 'tip' => 'Brak podpiętej faktury lub innego dokumentu kosztowego.'],
        ];
        $statusColors = [
            'paid' => 'bg-emerald-100 text-emerald-800',
            'partial' => 'bg-amber-100 text-amber-800',
            'advance' => 'bg-sky-100 text-sky-800',
            'due' => 'bg-blue-100 text-blue-800',
            'overdue' => 'bg-rose-100 text-rose-800',
            'review' => 'bg-red-200 text-red-900 ring-1 ring-red-400',
            'ok' => 'bg-emerald-100 text-emerald-800',
            'n/a' => 'bg-gray-100 text-gray-700',
        ];
    @endphp

    @if($hasSettlement)
        <div wire:ignore>
            @livewire(\App\Filament\Resources\EventResource\Widgets\EventFinanceMarginWidget::class, ['record' => $this->record], key('event-finance-margin-'.$this->record->getKey()))
        </div>
    @endif

    @unless($hasSettlement)
        <div class="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-10 text-center shadow-sm dark:border-gray-600 dark:bg-gray-900">
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Brak rozliczenia dla tej imprezy</h3>
            <p class="mx-auto mt-2 max-w-md text-sm text-gray-600 dark:text-gray-400">
                Przy tworzeniu z szablonu rozliczenie i koszty planu powstają automatycznie.
                Tu widać ten ekran tylko gdy rozliczenie nie istnieje (np. stara impreza albo usunięty draft).
            </p>
            <div class="mt-5">
                <x-filament::button wire:click="createSettlement" icon="heroicon-o-plus">
                    Utwórz rozliczenie
                </x-filament::button>
            </div>
        </div>
    @else
        <div
            class="w-full max-w-none space-y-5"
            wire:key="event-finance-root-{{ $this->record->getKey() }}-{{ $this->filter }}-{{ $this->groupFilter }}-{{ $this->hideZero ? '1' : '0' }}-{{ md5($this->search) }}-{{ $this->sortBy }}-{{ $this->sortDir }}"
            x-data="{
                initSortables() {
                    if (typeof Sortable === 'undefined') {
                        setTimeout(() => this.initSortables(), 50);
                        return;
                    }
                    document.querySelectorAll('[data-finance-group-body]').forEach((el) => {
                        if (el._sortable) {
                            el._sortable.destroy();
                        }
                        el._sortable = new Sortable(el, {
                            group: 'finance-costs',
                            animation: 150,
                            handle: '[data-drag-handle]',
                            draggable: 'tr[data-cost-id]',
                            ghostClass: 'opacity-40',
                            onAdd: (evt) => {
                                const costId = parseInt(evt.item.dataset.costId || '0', 10);
                                const groupId = parseInt(evt.to.dataset.groupId || '0', 10);
                                if (costId > 0) {
                                    $wire.moveCostToGroup(costId, groupId > 0 ? groupId : null);
                                }
                            },
                            onUpdate: () => {},
                        });
                    });
                }
            }"
            x-init="
                $nextTick(() => initSortables());
                Livewire.hook('morph.updated', () => { $nextTick(() => initSortables()); });
            "
        >
        {{-- Nagłówek: koszty + przychody klientów + gotówka pilota --}}
        <div class="grid w-full gap-3 sm:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-8">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900" title="Suma kosztów ze szablonu / programu (ekwiwalent PLN, także pozycje bez przeliczenia).">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Koszty (szablon)</div>
                <div class="mt-1 text-xl font-bold leading-snug text-gray-900 dark:text-gray-100">{{ $totals['calculation_label'] ?? '—' }}</div>
                <div class="mt-1 text-xs text-gray-500">z szablonu / programu</div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900" title="Planowane koszty do dostawców. Waluty bez przeliczenia pokazywane osobno (nie w sumie PLN).">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Koszty (planowane)</div>
                <div class="mt-1 text-xl font-bold leading-snug text-gray-900 dark:text-gray-100">{{ $totals['planned_label'] ?? '—' }}</div>
                <div class="mt-1 text-xs text-gray-500">ustalenia z podwykonawcami</div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900" title="Suma rzeczywistych wpłat kosztowych do dostawców.">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Zapłacono</div>
                <div class="mt-1 text-xl font-bold leading-snug text-emerald-700">{{ $totals['paid_label'] ?? '—' }}</div>
                <div class="mt-1 text-xs text-gray-500">zaliczki i płatności końcowe</div>
            </div>
            <div class="rounded-xl border border-rose-200 bg-rose-50/50 p-4 shadow-sm dark:border-rose-900/40 dark:bg-rose-950/20" title="Planowane PLN + waluty bez przeliczenia − już zapłacone dostawcom.">
                <div class="text-xs font-medium uppercase tracking-wide text-rose-700">Do zapłaty dostawcom</div>
                <div class="mt-1 text-xl font-bold leading-snug text-rose-700">{{ $totals['remaining_label'] ?? '—' }}</div>
                <div class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                    Po terminie: {{ (int) ($counts['overdue'] ?? 0) }}
                </div>
            </div>
            <div class="rounded-xl border border-sky-200 bg-sky-50/40 p-4 shadow-sm dark:border-sky-900/40 dark:bg-sky-950/20" title="Należności uczestników / umów (przychód).">
                <div class="text-xs font-medium uppercase tracking-wide text-sky-700">Należne od klientów</div>
                <div class="mt-1 text-xl font-bold leading-snug text-sky-900 dark:text-sky-100">{{ $totals['client_due_label'] ?? '—' }}</div>
                <div class="mt-1 text-xs text-gray-500">umowy / raty</div>
            </div>
            <div class="rounded-xl border border-sky-200 bg-sky-50/40 p-4 shadow-sm dark:border-sky-900/40 dark:bg-sky-950/20" title="Wpłaty uczestników zarejestrowane w systemie.">
                <div class="text-xs font-medium uppercase tracking-wide text-sky-700">Wpłacono od klientów</div>
                <div class="mt-1 text-xl font-bold leading-snug text-sky-900 dark:text-sky-100">{{ $totals['client_paid_label'] ?? '—' }}</div>
                <div class="mt-1 text-xs text-gray-500">przychód rzeczywisty</div>
            </div>
            @php
                $clientDuePln = (float) ($totals['client_due_pln'] ?? 0);
                $clientPaidPln = (float) ($totals['client_paid_pln'] ?? 0);
                $clientRemainingPln = round($clientDuePln - $clientPaidPln, 2);
                $clientRemainingTone = abs($clientRemainingPln) <= 0.009 ? 'ok' : ($clientRemainingPln < 0 ? 'over' : 'due');
                $clientRemainingLabel = match ($clientRemainingTone) {
                    'ok' => 'Saldo klientów',
                    'over' => 'Nadpłata od klientów',
                    default => 'Do dopłaty od klientów',
                };
                $clientRemainingValue = $clientRemainingTone === 'ok'
                    ? \App\Support\MoneyFormatter::format(0, 'PLN')
                    : (($clientRemainingTone === 'over' ? 'nadpłata ' : '').\App\Support\MoneyFormatter::format(abs($clientRemainingPln), 'PLN'));
            @endphp
            <div
                @class([
                    'rounded-xl border p-4 shadow-sm',
                    'border-emerald-200 bg-emerald-50/40 dark:border-emerald-900/40 dark:bg-emerald-950/20' => $clientRemainingTone === 'ok',
                    'border-amber-200 bg-amber-50/40 dark:border-amber-900/40 dark:bg-amber-950/20' => $clientRemainingTone === 'over',
                    'border-rose-200 bg-rose-50/40 dark:border-rose-900/40 dark:bg-rose-950/20' => $clientRemainingTone === 'due',
                ])
                title="Różnica: należne − wpłacone. Nadpłata gdy klienci wpłacili więcej."
            >
                <div @class([
                    'text-xs font-medium uppercase tracking-wide',
                    'text-emerald-700' => $clientRemainingTone === 'ok',
                    'text-amber-800' => $clientRemainingTone === 'over',
                    'text-rose-700' => $clientRemainingTone === 'due',
                ])>{{ $clientRemainingLabel }}</div>
                <div @class([
                    'mt-1 text-xl font-bold leading-snug',
                    'text-emerald-800 dark:text-emerald-200' => $clientRemainingTone === 'ok',
                    'text-amber-900 dark:text-amber-200' => $clientRemainingTone === 'over',
                    'text-rose-800 dark:text-rose-200' => $clientRemainingTone === 'due',
                ])>{{ $clientRemainingValue }}</div>
                <div class="mt-1 text-xs text-gray-500">należne − wpłacone</div>
            </div>
            @php $pilotCash = $overview['pilot_cash'] ?? []; @endphp
            <div
                class="rounded-xl border border-indigo-200 bg-indigo-50/40 p-4 shadow-sm dark:border-indigo-900/40 dark:bg-indigo-950/20"
                title="Suma kosztów z płatnikiem Pilot — tyle gotówki należy przygotować / wydać pilotowi (zakładka Gotówka dla pilota)."
            >
                <div class="text-xs font-medium uppercase tracking-wide text-indigo-700">Gotówka dla pilota</div>
                <div class="mt-1 text-xl font-bold leading-snug text-indigo-900 dark:text-indigo-100">
                    {{ $pilotCash['needed_label'] ?? ($overview['pilot_cash_label'] ?? '—') }}
                </div>
                <div class="mt-1 space-y-0.5 text-xs text-indigo-900/80 dark:text-indigo-200/80">
                    @if (empty($pilotCash['lines']) && empty($pilotCash['has_pilot_costs']))
                        <div>Brak pozycji z płatnikiem Pilot</div>
                    @endif
                    <div>Wypłacono z biura: {{ $overview['pilot_cash_label'] ?? '—' }}</div>
                    <div>Wydane gotówką: {{ $pilotCash['spent_label'] ?? '—' }}</div>
                    <a
                        href="{{ \App\Filament\Resources\EventResource::getUrl('finance-pilot-cash', ['record' => $this->record]) }}"
                        class="inline-block font-medium text-indigo-700 underline hover:text-indigo-900 dark:text-indigo-300"
                    >Gotówka dla pilota →</a>
                </div>
            </div>
        </div>
        @if (! empty($totals['calc_plan_hint']))
            <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
                {{ $totals['calc_plan_hint'] }}
                @if (($totals['calc_plan_delta_pln'] ?? 0) > 0.01)
                    · Różnica szablon − planowane PLN: {{ \App\Support\MoneyFormatter::format((float) $totals['calc_plan_delta_pln'], 'PLN') }}
                @endif
            </p>
        @endif

        {{-- Filtry statusowe --}}
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Filtr:</span>
            @foreach ($filters as $key => $filter)
                <button
                    type="button"
                    wire:click="setFilter('{{ $key }}')"
                    title="{{ $filter['tip'] }}"
                    @class([
                        'rounded-lg px-3 py-1.5 text-sm font-medium border transition',
                        'bg-primary-600 text-white border-primary-600' => $overview['filter'] === $key,
                        'bg-white text-gray-700 border-gray-200 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-200 dark:border-gray-700' => $overview['filter'] !== $key,
                    ])
                >
                    {{ $filter['label'] }}
                </button>
            @endforeach
            <button
                type="button"
                wire:click="toggleHideZero"
                title="Ukrywa pozycje z zerowym szablonem, planowanymi i zapłaconym (domyślnie włączone)."
                @class([
                    'rounded-lg px-3 py-1.5 text-sm font-medium border transition',
                    'bg-slate-800 text-white border-slate-800' => $this->hideZero,
                    'bg-white text-gray-700 border-gray-200 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-200 dark:border-gray-700' => ! $this->hideZero,
                ])
            >
                {{ $this->hideZero ? 'Ukryj zera' : 'Pokaż zera' }}
                @if (($overview['hidden_zero_count'] ?? 0) > 0 && $this->hideZero)
                    <span class="ml-1 opacity-80">({{ (int) $overview['hidden_zero_count'] }})</span>
                @endif
            </button>
        </div>

        <div class="flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
            <div class="min-w-[16rem] flex-1">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Szukaj</label>
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Pozycja, kontrahent, NIP, e-mail…"
                    class="fi-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800"
                />
            </div>
            @if ($this->search !== '')
                <button type="button" class="text-sm text-gray-600 hover:underline" wire:click="$set('search', '')">Wyczyść wyszukiwanie</button>
            @endif
        </div>

        {{-- Filtr grup + nowa grupa --}}
        <div class="flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Grupa:</span>
                <button
                    type="button"
                    wire:click="setGroupFilter('all')"
                    @class([
                        'rounded-lg px-3 py-1.5 text-sm font-medium border',
                        'bg-primary-600 text-white border-primary-600' => $this->groupFilter === 'all',
                        'bg-white border-gray-200 dark:bg-gray-800 dark:border-gray-600' => $this->groupFilter !== 'all',
                    ])
                >Wszystkie</button>
                @foreach ($groups as $g)
                    @if (($g['key'] ?? '') !== 'ungrouped')
                        <button
                            type="button"
                            wire:click="setGroupFilter('{{ (int) $g['id'] }}')"
                            @class([
                                'rounded-lg px-3 py-1.5 text-sm font-medium border',
                                'bg-primary-600 text-white border-primary-600' => (string) $this->groupFilter === (string) $g['id'],
                                'bg-white border-gray-200 dark:bg-gray-800 dark:border-gray-600' => (string) $this->groupFilter !== (string) $g['id'],
                            ])
                        >{{ $g['name'] }} ({{ (int) $g['count'] }})</button>
                    @endif
                @endforeach
            </div>
            <div class="ml-auto flex flex-wrap items-center gap-2">
                <input
                    type="text"
                    wire:model="newGroupName"
                    placeholder="Nowa grupa…"
                    class="fi-input rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800"
                    wire:keydown.enter="createGroup"
                />
                <x-filament::button size="sm" wire:click="createGroup">Dodaj grupę</x-filament::button>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500">
            <span>Przeciągnij wiersz (⋮⋮) między grupami. Checkbox = akcje masowe. OK = sprawdzone i kompletne. Klik wiersza = szczegóły.</span>
            <button type="button" class="text-primary-600 hover:underline" wire:click="selectAllVisible">Zaznacz widoczne</button>
            @if (count($this->selectedCostIds) > 0)
                <button type="button" class="text-gray-600 hover:underline" wire:click="clearSelection">Wyczyść zaznaczenie</button>
            @endif
        </div>

        @if (count($this->selectedCostIds) > 0)
            <div class="flex flex-wrap items-center gap-3 rounded-xl border border-primary-200 bg-primary-50/50 px-4 py-3 dark:border-primary-900/40 dark:bg-primary-950/20">
                <span class="text-sm font-semibold text-primary-900 dark:text-primary-100">
                    Zaznaczono: {{ count($this->selectedCostIds) }}
                </span>
                <select wire:model="bulkTargetGroupId" class="fi-select-input rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
                    <option value="">— wybierz grupę —</option>
                    @foreach ($groups as $g)
                        @if (($g['key'] ?? '') !== 'ungrouped')
                            <option value="{{ (int) $g['id'] }}">{{ $g['name'] }}</option>
                        @endif
                    @endforeach
                    <option value="0">Bez grupy</option>
                </select>
                <x-filament::button size="sm" wire:click="bulkMoveToGroup">Przenieś do grupy</x-filament::button>

                <span class="mx-1 hidden h-5 w-px bg-primary-200 sm:inline-block dark:bg-primary-800" aria-hidden="true"></span>

                <select wire:model="bulkPaidBy" class="fi-select-input rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800" title="Płatnik">
                    @foreach (\App\Models\EventSettlementCost::$paidByOptions as $k => $v)
                        <option value="{{ $k }}">{{ $v }}</option>
                    @endforeach
                </select>
                <x-filament::button size="sm" color="info" wire:click="bulkChangePaidBy">Ustaw płatnika</x-filament::button>
                <x-filament::button
                    size="sm"
                    color="warning"
                    wire:click="bulkReplaceInsuranceActual"
                    wire:confirm="Zastąpić rzeczywiste dla zaznaczonych ubezpieczeń? Zaksięguje pełną płatność (polisa/faktura) bez zmiany Planu."
                >
                    Zastąp rzeczywiste (ubezpieczenia)
                </x-filament::button>
            </div>
        @endif

        {{-- Gdy drawer z formularzem: nie renderuj tabeli ponownie (wire:ignore zostawia poprzedni DOM). --}}
        @php
            $drawerBusy = $showPaymentForm || $showPlanForm || $showCostForm || $showDocumentForm;
        @endphp
        <div
            class="w-full space-y-4"
            @if ($drawerBusy) wire:ignore @endif
        >
            @unless ($drawerBusy)
            @forelse ($groups as $group)
                @php
                    $gid = (int) $group['id'];
                    $collapsed = (bool) ($this->collapsedGroups[$gid] ?? false);
                    $showSection = $this->groupFilter === 'all'
                        || ((string) $this->groupFilter === (string) $gid)
                        || ($this->groupFilter === 'ungrouped' && ($group['key'] ?? '') === 'ungrouped');
                @endphp
                @if (! $showSection)
                    @continue
                @endif

                <div class="w-full overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="border-b border-gray-100 bg-gray-50 px-3 py-3 dark:border-gray-800 dark:bg-gray-800/80">
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" wire:click="toggleGroup({{ $gid }})" class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                {{ $collapsed ? '▸' : '▾' }}
                                @if ($renamingGroupId === $gid)
                                    <span class="inline-flex items-center gap-2" onclick="event.stopPropagation()">
                                        <input type="text" wire:model="renameGroupName" class="fi-input rounded border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900" />
                                        <button type="button" class="text-xs text-primary-600" wire:click.stop="saveRenameGroup">Zapisz</button>
                                    </span>
                                @else
                                    {{ $group['name'] }}
                                @endif
                                <span class="ml-1 font-normal text-gray-500">({{ (int) $group['count'] }} pozycji)</span>
                            </button>
                            <div class="ml-auto flex flex-wrap items-center gap-3 text-xs">
                                @if (($group['key'] ?? '') !== 'ungrouped')
                                    <button type="button" class="text-primary-600 hover:underline" wire:click.stop="startRenameGroup({{ $gid }}, @js($group['name']))">Zmień nazwę</button>
                                    @if (! ($group['is_system'] ?? false))
                                        <button type="button" class="text-rose-600 hover:underline" wire:click.stop="deleteGroup({{ $gid }})" wire:confirm="Usunąć grupę? Pozycje trafią do „Inne”.">Usuń</button>
                                    @endif
                                @endif
                            </div>
                        </div>
                        {{-- Podsumowanie finansowe grupy --}}
                        <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                            <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-900">
                                <div class="text-[10px] uppercase tracking-wide text-gray-500">Koszty (szablon)</div>
                                <div class="text-sm font-bold tabular-nums leading-snug">{{ $group['calculation_label'] }}</div>
                            </div>
                            <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-900">
                                <div class="text-[10px] uppercase tracking-wide text-gray-500">Koszty (planowane)</div>
                                <div class="text-sm font-bold tabular-nums leading-snug">{{ $group['planned_label'] }}</div>
                            </div>
                            <div class="rounded-lg border border-emerald-200 bg-emerald-50/60 px-3 py-2 dark:border-emerald-900/40 dark:bg-emerald-950/20">
                                <div class="text-[10px] uppercase tracking-wide text-emerald-700">Zapłacono</div>
                                <div class="text-sm font-bold tabular-nums leading-snug text-emerald-800">{{ $group['paid_label'] }}</div>
                            </div>
                            <div class="rounded-lg border border-rose-200 bg-rose-50/60 px-3 py-2 dark:border-rose-900/40 dark:bg-rose-950/20">
                                <div class="text-[10px] uppercase tracking-wide text-rose-700">Do zapłaty</div>
                                <div class="text-sm font-bold tabular-nums leading-snug text-rose-700">{{ $group['remaining_label'] ?? '—' }}</div>
                            </div>
                        </div>
                    </div>

                    @unless ($collapsed)
                        <div class="w-full overflow-x-auto">
                            @php
                                $sortableColumns = [
                                    \App\Services\EventFinanceOverviewService::SORT_NAME => 'Pozycja',
                                    \App\Services\EventFinanceOverviewService::SORT_CONTRACTOR => 'Kontrahent',
                                ];
                            @endphp
                            <table class="w-full min-w-[88rem] table-fixed divide-y divide-gray-200 text-sm dark:divide-gray-700">
                                <colgroup>
                                    <col style="width:2.25rem" />
                                    <col style="width:2.25rem" />
                                    <col style="width:3rem" />
                                    <col style="width:14%" />
                                    <col style="width:13%" />
                                    <col style="width:6rem" />
                                    <col style="width:10%" />
                                    <col style="width:10%" />
                                    <col style="width:10%" />
                                    <col style="width:7rem" />
                                    <col style="width:6.5rem" />
                                    <col style="width:7rem" />
                                </colgroup>
                                <thead class="bg-white text-left text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-900">
                                    <tr>
                                        <th class="px-2 py-2"></th>
                                        <th class="px-2 py-2" title="Zaznacz"></th>
                                        @foreach ($sortableColumns as $sortKey => $sortLabel)
                                            @php
                                                $isActiveSort = $this->sortBy === $sortKey;
                                                $sortArrow = $isActiveSort ? ($this->sortDir === 'asc' ? '↑' : '↓') : '';
                                            @endphp
                                            <th class="px-3 py-2">
                                                <button
                                                    type="button"
                                                    wire:click="setSort('{{ $sortKey }}')"
                                                    @class([
                                                        'inline-flex items-center gap-1 font-semibold uppercase tracking-wide transition hover:text-primary-600',
                                                        'text-primary-700' => $isActiveSort,
                                                    ])
                                                >
                                                    {{ $sortLabel }}
                                                    @if ($sortArrow !== '')
                                                        <span>{{ $sortArrow }}</span>
                                                    @endif
                                                </button>
                                            </th>
                                        @endforeach
                                        <th class="px-3 py-2">Płatnik</th>
                                        <th class="px-3 py-2 text-right">Szablon</th>
                                        @foreach ([
                                            \App\Services\EventFinanceOverviewService::SORT_PLANNED => 'Planowane',
                                            \App\Services\EventFinanceOverviewService::SORT_PAID => 'Zapłacono',
                                            \App\Services\EventFinanceOverviewService::SORT_STATUS => 'Status',
                                            \App\Services\EventFinanceOverviewService::SORT_DUE => 'Termin',
                                        ] as $sortKey => $sortLabel)
                                            @php
                                                $isActiveSort = $this->sortBy === $sortKey;
                                                $sortArrow = $isActiveSort ? ($this->sortDir === 'asc' ? '↑' : '↓') : '';
                                                $thAlign = in_array($sortKey, [\App\Services\EventFinanceOverviewService::SORT_PLANNED, \App\Services\EventFinanceOverviewService::SORT_PAID], true) ? 'text-right' : '';
                                            @endphp
                                            <th class="px-3 py-2 {{ $thAlign }}">
                                                <button
                                                    type="button"
                                                    wire:click="setSort('{{ $sortKey }}')"
                                                    @class([
                                                        'inline-flex items-center gap-1 font-semibold uppercase tracking-wide transition hover:text-primary-600',
                                                        'text-primary-700' => $isActiveSort,
                                                    ])
                                                >
                                                    {{ $sortLabel }}
                                                    @if ($sortArrow !== '')
                                                        <span>{{ $sortArrow }}</span>
                                                    @endif
                                                </button>
                                            </th>
                                        @endforeach
                                        <th class="px-3 py-2 text-center" title="Sprawdzone i kompletne">OK</th>
                                        <th class="px-3 py-2 text-center">Dok.</th>
                                    </tr>
                                </thead>
                                <tbody
                                    data-finance-group-body
                                    data-group-id="{{ $gid }}"
                                    class="divide-y divide-gray-100 dark:divide-gray-800"
                                    style="min-height: 3rem;"
                                >
                                    @forelse ($group['rows'] as $row)
                                        @php $isChecked = in_array((int) $row['cost_id'], array_map('intval', $this->selectedCostIds), true); @endphp
                                        <tr
                                            data-cost-id="{{ (int) $row['cost_id'] }}"
                                            wire:key="cost-row-{{ (int) $row['cost_id'] }}-{{ $row['paid_by'] ?? 'office' }}"
                                            @class([
                                                'hover:bg-amber-50/60 dark:hover:bg-amber-950/20',
                                                'bg-amber-50/80 dark:bg-amber-950/30' => $selected && (int) $selected['cost_id'] === (int) $row['cost_id'],
                                                'bg-red-50 dark:bg-red-950/40 ring-1 ring-inset ring-red-300' => ($row['ui_status'] ?? '') === 'review',
                                                'bg-amber-50/40 dark:bg-amber-950/15' => ($row['source_type'] ?? '') === 'manual'
                                                    && ! ($selected && (int) $selected['cost_id'] === (int) $row['cost_id'])
                                                    && ($row['ui_status'] ?? '') !== 'review',
                                            ])
                                        >
                                            <td class="px-2 py-2 text-gray-400 cursor-grab" data-drag-handle title="Przeciągnij do innej grupy">⋮⋮</td>
                                            <td class="px-2 py-2">
                                                <input
                                                    type="checkbox"
                                                    class="rounded border-gray-300"
                                                    @checked($isChecked)
                                                    wire:click.stop="toggleCostSelection({{ (int) $row['cost_id'] }})"
                                                />
                                            </td>
                                            <td class="px-3 py-2 cursor-pointer" wire:click="openCost({{ (int) $row['cost_id'] }})">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ $row['name'] }}</div>
                                                    @if (($row['source_type'] ?? '') === 'manual')
                                                        <span class="inline-flex items-center rounded-full bg-amber-200 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-950 dark:bg-amber-800 dark:text-amber-50">
                                                            Nieplanowany
                                                        </span>
                                                    @endif
                                                </div>
                                                <div class="text-xs text-gray-500">{{ $row['source_label'] }}</div>
                                            </td>
                                            <td class="px-3 py-2 cursor-pointer" wire:click="openCost({{ (int) $row['cost_id'] }})">
                                                @if (! empty($row['contractor']))
                                                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ $row['contractor'] }}</div>
                                                    @if (! empty($row['contractor_source_label']))
                                                        <div class="text-[11px] text-gray-400">{{ $row['contractor_source_label'] }}</div>
                                                    @endif
                                                    @if (! empty($row['contractor_details']) && count($row['contractor_details']) > 1)
                                                        <div class="mt-0.5 text-[11px] leading-snug text-gray-400" title="{{ implode(' · ', $row['contractor_details']) }}">
                                                            {{ collect($row['contractor_details'])->skip(1)->take(2)->implode(' · ') }}
                                                        </div>
                                                    @endif
                                                @else
                                                    <span class="text-xs text-gray-400">—</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap" onclick="event.stopPropagation()">
                                                @php
                                                    $rowPaidBy = ($row['paid_by'] ?? 'office') === 'pilot' ? 'pilot' : 'office';
                                                    $rowPaidByLabel = \App\Models\EventSettlementCost::$paidByOptions[$rowPaidBy] ?? $rowPaidBy;
                                                @endphp
                                                <label class="relative inline-flex cursor-pointer" title="Płatnik pozycji (odpowiada za pozostałą kwotę)" wire:key="payer-{{ (int) $row['cost_id'] }}-{{ $rowPaidBy }}">
                                                    <select
                                                        class="absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0"
                                                        x-on:change="$wire.changeCostPaidBy({{ (int) $row['cost_id'] }}, $event.target.value)"
                                                    >
                                                        @foreach (\App\Models\EventSettlementCost::$paidByOptions as $k => $v)
                                                            <option value="{{ $k }}" @selected($rowPaidBy === $k)>{{ $v }}</option>
                                                        @endforeach
                                                    </select>
                                                    <span @class([
                                                        'pointer-events-none inline-flex items-center gap-0.5 rounded-full px-2 py-0.5 text-xs font-semibold',
                                                        'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' => $rowPaidBy === 'office',
                                                        'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200' => $rowPaidBy === 'pilot',
                                                    ])>
                                                        {{ $rowPaidByLabel }}
                                                        <svg class="h-3 w-3 opacity-60" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                                        </svg>
                                                    </span>
                                                </label>
                                            </td>
                                            <td class="px-3 py-2 text-right text-xs leading-snug tabular-nums cursor-pointer" wire:click="openCost({{ (int) $row['cost_id'] }})">{{ $row['calculation_label'] }}</td>
                                            <td class="px-3 py-2 text-right text-xs leading-snug tabular-nums font-medium cursor-pointer" wire:click="openCost({{ (int) $row['cost_id'] }})">{{ $row['planned_label'] }}</td>
                                            <td class="px-3 py-2 text-right text-xs leading-snug tabular-nums cursor-pointer" wire:click="openCost({{ (int) $row['cost_id'] }})">
                                                <div class="text-emerald-700">{{ $row['paid_label'] }}</div>
                                                @if (! empty($row['payment_hint']))
                                                    <div class="text-[11px] font-normal text-sky-700 dark:text-sky-300" title="{{ $row['payment_hint'] }}">
                                                        {{ $row['payment_hint'] }}
                                                    </div>
                                                @endif
                                                @if (! empty($row['savings_label']))
                                                    <div class="text-[11px] font-medium text-emerald-800 dark:text-emerald-200" title="Planowane − zapłacona faktura">
                                                        {{ $row['savings_label'] }}
                                                    </div>
                                                @elseif (! empty($row['overpayment_label']))
                                                    <div class="text-[11px] font-semibold text-red-800 dark:text-red-200">
                                                        {{ $row['overpayment_label'] }}
                                                    </div>
                                                @elseif (($row['remaining_pln'] ?? 0) > 0.01 || (
                                                    ($row['planned_currency_symbol'] ?? 'PLN') !== 'PLN'
                                                    && ($row['ui_status'] ?? '') !== 'paid'
                                                    && ($row['ui_status'] ?? '') !== 'n/a'
                                                    && ($row['ui_status'] ?? '') !== 'review'
                                                ))
                                                    <div class="text-[11px] font-normal text-rose-700 dark:text-rose-300" title="Różnica do zapłaty">
                                                        Różnica {{ $row['remaining_label'] }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 cursor-pointer" wire:click="openCost({{ (int) $row['cost_id'] }})">
                                                <span @class(['inline-flex rounded-full px-2 py-0.5 text-xs font-semibold', $statusColors[$row['ui_status']] ?? 'bg-gray-100 text-gray-700'])>
                                                    {{ $row['ui_status_label'] }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap text-xs cursor-pointer" wire:click="openCost({{ (int) $row['cost_id'] }})">{{ $row['next_due_label'] ?? '—' }}</td>
                                            <td class="px-2 py-2 text-center" onclick="event.stopPropagation()">
                                                <input
                                                    type="checkbox"
                                                    class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                                                    title="Sprawdzone i kompletne"
                                                    @checked($row['is_approved'] ?? false)
                                                    wire:click.stop="toggleCostApproval({{ (int) $row['cost_id'] }})"
                                                />
                                            </td>
                                            <td class="px-3 py-2 text-center text-xs cursor-pointer" wire:click="openCost({{ (int) $row['cost_id'] }})" title="{{ $row['document_status_label'] ?? '' }}">
                                                    @if (! empty($row['has_uploaded_file']))
                                                    @php
                                                        $docUrl = (string) ($row['document_first_url'] ?? '');
                                                        $docHint = (string) ($row['document_hint'] ?? 'Plik');
                                                        $isPolicy = str_contains(mb_strtolower($docHint), 'polisa')
                                                            || ($row['source_type'] ?? '') === 'insurance_day';
                                                    @endphp
                                                    @if ($docUrl !== '')
                                                        <a
                                                            href="{{ $docUrl }}"
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            class="inline-flex max-w-[10rem] items-center justify-center gap-1 truncate rounded-md bg-emerald-50 px-1.5 py-0.5 font-medium text-emerald-800 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-200"
                                                            title="{{ $row['document_status_label'] ?? $docHint }}"
                                                            onclick="event.stopPropagation()"
                                                        >
                                                            <span aria-hidden="true">📎</span>
                                                            <span>{{ $isPolicy && ! str_contains(mb_strtolower($docHint), 'polisa') ? 'Polisa' : $docHint }}</span>
                                                        </a>
                                                    @else
                                                        <span class="inline-flex max-w-[10rem] items-center gap-1 truncate rounded-md bg-emerald-50 px-1.5 py-0.5 font-medium text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">
                                                            <span aria-hidden="true">📎</span>
                                                            {{ $docHint }}
                                                        </span>
                                                    @endif
                                                @else
                                                    <span @class([
                                                        'inline-flex max-w-[10rem] truncate rounded-md px-1.5 py-0.5 font-medium',
                                                        'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200' => str_contains((string) ($row['document_hint'] ?? ''), 'bez pliku'),
                                                        'bg-gray-50 text-gray-500 dark:bg-gray-800 dark:text-gray-400' => ! str_contains((string) ($row['document_hint'] ?? ''), 'bez pliku'),
                                                    ])>
                                                        {{ $row['document_hint'] ?? 'Brak pliku' }}
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endunless
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 p-8 text-center space-y-3">
                    <p class="text-gray-600 dark:text-gray-300">Brak pozycji kosztów (wykonanie).</p>
                    <p class="text-sm text-gray-500">Dodaj wydatek nieprzewidziany albo koszt programu ze szablonu.</p>
                    <div class="flex flex-wrap justify-center gap-2">
                        <x-filament::button wire:click="startAddManualCost" icon="heroicon-o-plus">
                            Dodaj wydatek
                        </x-filament::button>
                        <x-filament::button wire:click="startAddCost" color="gray" icon="heroicon-o-map">
                            Koszt programu
                        </x-filament::button>
                        <x-filament::button
                            tag="a"
                            :href="\App\Filament\Resources\EventResource::getUrl('calculation', ['record' => $this->record])"
                            color="gray"
                            icon="heroicon-o-calculator"
                        >
                            Otwórz kalkulację
                        </x-filament::button>
                    </div>
                </div>
            @endforelse
            @endunless
        </div>

    {{-- Drawer: nowy koszt (bez zaznaczenia) --}}
    @if ($showCostForm && ! $selected)
        <div class="fixed inset-0 z-40 flex justify-end" wire:key="cost-create-drawer">
            <div class="absolute inset-0 bg-black/30" wire:click="$set('showCostForm', false)"></div>
            <aside class="relative z-50 flex h-full w-full max-w-md flex-col overflow-y-auto border-l border-gray-200 bg-white p-4 shadow-xl dark:border-gray-700 dark:bg-gray-900">
                <div class="mb-3 flex items-start justify-between gap-2">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ $this->costFormMode === 'manual' ? 'Nowy wydatek' : 'Nowy koszt programu' }}
                        </h3>
                        <p class="text-xs text-gray-500">
                            @if ($this->costFormMode === 'manual')
                                Tylko w Finansach — bez dodawania do programu imprezy.
                            @else
                                Zapisze punkt w Programie i pozycję w Finansach.
                            @endif
                        </p>
                    </div>
                    <button type="button" wire:click="$set('showCostForm', false)" class="text-xs text-gray-500 hover:text-gray-800">Zamknij</button>
                </div>
                @include('filament.resources.event-resource.pages.partials.event-finance-cost-form')
            </aside>
        </div>
    @endif

    @include('filament.resources.event-resource.pages.partials.event-finance-cost-drawer')


        </div>
    @endunless
</x-filament-panels::page>
