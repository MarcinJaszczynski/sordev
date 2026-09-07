<x-filament-panels::page
    @class([
        'fi-resource-edit-record-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
        'fi-resource-record-' . $record->getKey(),
    ])
>
    @php
        /** @var array<string, mixed> $summary */
        $summary = $this->pilotPageSummary();
        $contactBits = array_values(array_filter([
            $summary['pilot_phone'] ?? null,
            $summary['pilot_email'] ?? null,
        ]));
    @endphp

    <div class="space-y-0 pilot-page-root" data-tab="{{ $this->pilotTab }}">
        @include('filament.resources.event-resource.pages.partials.pilot-page-styles')

        <div class="pilot-status-bar pilot-status-bar--rich">
            <div class="pilot-status-main-col">
                <div class="pilot-status-title">
                    <span class="pilot-status-icon" aria-hidden="true">🧑‍✈️</span>
                    <div class="min-w-0">
                        <p class="pilot-status-main">
                            Pilot — {{ $summary['pilot_name'] }}
                            @if ($summary['contractor_url'])
                                <a
                                    href="{{ $summary['contractor_url'] }}"
                                    class="pilot-contractor-link"
                                    title="Otwórz kartę kontrahenta"
                                >Kontrahent →</a>
                            @endif
                        </p>
                        <p class="pilot-status-sub">
                            Uczestnicy: {{ $summary['participants_label'] }}
                            @if (! empty($summary['pilot_settlement_form_label']))
                                <span class="pilot-status-sep">·</span>
                                {{ $summary['pilot_settlement_form_label'] }}
                            @endif
                            @if ($contactBits !== [])
                                <span class="pilot-status-sep">·</span>
                                {{ implode(' · ', $contactBits) }}
                            @endif
                        </p>
                        @if ($summary['shared_at_label'] || $summary['email_sent_label'])
                            <p class="pilot-status-meta">
                                @if ($summary['shared_at_label'])
                                    Udostępniono {{ $summary['shared_at_label'] }}
                                @endif
                                @if ($summary['email_sent_label'])
                                    @if ($summary['shared_at_label'])
                                        <span class="pilot-status-sep">·</span>
                                    @endif
                                    E-mail {{ $summary['email_sent_label'] }}
                                @endif
                            </p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="pilot-status-actions">
                @livewire('pilot-portal-settings-toolbar', [
                    'eventId' => $record->id,
                    'variant' => 'share',
                ], key('pilot-portal-share-'.$record->id))
            </div>
        </div>

        <div class="pilot-stat-grid">
            <div class="pilot-stat-card">
                <p class="pilot-stat-label">Status rozliczenia</p>
                <p class="pilot-stat-value">{{ $summary['settlement_status_label'] }}</p>
            </div>
            <div class="pilot-stat-card">
                <p class="pilot-stat-label">Potrzeba z programu</p>
                <p class="pilot-stat-value">{{ $summary['needed_label'] ?? $summary['plan_label'] ?? '—' }}</p>
                <p class="pilot-stat-hint">Gotówka do wydania u dostawców (koszty „płaci pilot”).</p>
            </div>
            <div class="pilot-stat-card pilot-stat-card--wide">
                <p class="pilot-stat-label">Skąd gotówka</p>
                <p class="pilot-stat-value">{{ $summary['funding_sources_label'] }}</p>
                @if (! empty($summary['bus_surplus_label']))
                    <p class="pilot-stat-hint">{{ $summary['bus_surplus_label'] }}</p>
                @endif
            </div>
            <div @class(['pilot-stat-card', 'pilot-stat-card--wide', 'pilot-stat-card--danger' => $summary['cash_to_pay_danger']])>
                <p class="pilot-stat-label">Wypłać z biura</p>
                @if ($summary['bus_covers_need'] ?? false)
                    <p class="pilot-stat-value">{{ $summary['cash_to_pay_label'] }}</p>
                @elseif (($summary['payout_exchange_label'] ?? '—') !== '—' || ($summary['payout_natura_label'] ?? '—') !== '—')
                    <p class="pilot-stat-value">
                        Wypłać i kup walutę: {{ $summary['payout_exchange_label'] }}
                    </p>
                    @if (! empty($summary['payout_exchange_detail']))
                        <p class="pilot-stat-hint">{{ $summary['payout_exchange_detail'] }}</p>
                    @endif
                    @if (($summary['payout_natura_label'] ?? '—') !== '—'
                        && ($summary['payout_natura_label'] ?? '') !== ($summary['payout_exchange_label'] ?? ''))
                        <p class="pilot-stat-subvalue">
                            albo wypłać waluty: {{ $summary['payout_natura_label'] }}
                        </p>
                    @endif
                @else
                    <p class="pilot-stat-value">{{ $summary['cash_to_pay_label'] }}</p>
                @endif
            </div>
            <div class="pilot-stat-card">
                <p class="pilot-stat-label">Gotówka — wypłacono</p>
                <p class="pilot-stat-value">{{ $summary['paid_label'] }}</p>
            </div>
            <div @class(['pilot-stat-card', 'pilot-stat-card--danger' => $summary['return_danger']])>
                <p class="pilot-stat-label">Do zwrotu</p>
                <p class="pilot-stat-value">{{ $summary['return_label'] }}</p>
            </div>
            <div @class(['pilot-stat-card', 'pilot-stat-card--danger' => $summary['pay_pilot_danger']])>
                <p class="pilot-stat-label">Do dopłaty pilotowi</p>
                <p class="pilot-stat-value">{{ $summary['pay_pilot_label'] }}</p>
            </div>
            <div @class(['pilot-stat-card', 'pilot-stat-card--danger' => $summary['fee_remaining_danger']])>
                <p class="pilot-stat-label">Wynagrodzenie — pozostało</p>
                <p class="pilot-stat-value">{{ $summary['fee_remaining_label'] }}</p>
            </div>
            <div class="pilot-stat-card">
                <p class="pilot-stat-label">Checklista</p>
                <p class="pilot-stat-value">{{ $summary['checklist_done'] }} / {{ $summary['checklist_total'] }}</p>
            </div>
        </div>

        <div class="pilot-subtabs" role="tablist" aria-label="Sekcje pilota">
            <button type="button" role="tab" wire:click="setPilotTab('briefing')" @class(['pilot-subtab', 'is-active' => $this->pilotTab === 'briefing']) aria-selected="{{ $this->pilotTab === 'briefing' ? 'true' : 'false' }}">
                Odprawa i pilot
            </button>
            <button type="button" role="tab" wire:click="setPilotTab('cash')" @class(['pilot-subtab', 'is-active' => $this->pilotTab === 'cash']) aria-selected="{{ $this->pilotTab === 'cash' ? 'true' : 'false' }}">
                Gotówka
            </button>
            <button type="button" role="tab" wire:click="setPilotTab('settlement')" @class(['pilot-subtab', 'is-active' => $this->pilotTab === 'settlement']) aria-selected="{{ $this->pilotTab === 'settlement' ? 'true' : 'false' }}">
                Rozliczenie
            </button>
            <button type="button" role="tab" wire:click="setPilotTab('checklist')" @class(['pilot-subtab', 'is-active' => $this->pilotTab === 'checklist']) aria-selected="{{ $this->pilotTab === 'checklist' ? 'true' : 'false' }}">
                Checklista
            </button>
        </div>

        @capture($form)
            <x-filament-panels::form
                id="form"
                :wire:key="$this->getId() . '.forms.' . $this->getFormStatePath()"
                wire:submit="save"
            >
                {{ $this->form }}

                <div @class(['pilot-sticky-actions', 'hidden' => $this->pilotTab === 'checklist'])>
                    <x-filament-panels::form.actions
                        :actions="$this->getCachedFormActions()"
                        :full-width="$this->hasFullWidthFormActions()"
                    />
                </div>
            </x-filament-panels::form>
        @endcapture

        {{ $form() }}

        <div @class(['pilot-panel-livewire', 'hidden' => $this->pilotTab !== 'cash'])>
            <div class="pilot-settlement-toolbar mb-3">
                @livewire('pilot-portal-settings-toolbar', [
                    'eventId' => $record->id,
                    'variant' => 'modules',
                ], key('pilot-portal-modules-'.$record->id))
            </div>

            <div class="pilot-cash-layout">
                <div id="pilot-cash-desk" tabindex="-1" class="pilot-cash-layout-main">
                    <p class="pilot-step-heading">Rozliczenie — wypłata, saldo, zwrot, wydatki</p>
                    @livewire('pilot-cash-desk', [
                        'event' => $record,
                        'context' => 'admin',
                        'compact' => false,
                        'respectPortalVisibility' => true,
                        'collapseExpenses' => true,
                        'includeCurrencyExchange' => false,
                    ], key('admin-pilot-cash-'.$record->id))
                </div>

                <div class="pilot-cash-layout-side space-y-3">
                    <div id="pilot-currency-exchange" class="pilot-side-block">
                        @livewire('pilot-cash-desk', [
                            'event' => $record,
                            'context' => 'admin',
                            'compact' => false,
                            'respectPortalVisibility' => true,
                            'showOfficePayoutBlock' => false,
                            'includeCurrencyExchange' => true,
                            'focus' => 'exchange',
                        ], key('admin-pilot-exchange-'.$record->id))
                    </div>

                    <div id="bus-collections" class="pilot-card pilot-card--tight">
                        <div class="pilot-card-header">
                            <div>
                                <p class="pilot-card-title">Zbiórki w autokarze</p>
                                <p class="pilot-card-meta">
                                    Plan → zebrano → do biura. Zebrane zasila saldo gotówki.
                                    @unless ($record->showsPilotBusCollections())
                                        <span class="text-amber-800 dark:text-amber-200">Portal: zbiórka wyłączona — pilot nie widzi formularza.</span>
                                    @endunless
                                </p>
                            </div>
                        </div>
                        @livewire('event-bus-collections', ['event' => $record], key('admin-bus-collections-'.$record->id))
                    </div>
                </div>
            </div>
        </div>

        <div @class(['pilot-panel-livewire', 'hidden' => $this->pilotTab !== 'checklist'])>
            <div class="pilot-card pilot-card--tight">
                <div class="pilot-card-header">
                    <div>
                        <p class="pilot-card-title">Checklista</p>
                        <p class="pilot-card-meta">Biuro ładuje szablon; pilot odznacza punkty.</p>
                    </div>
                    <span class="pilot-card-meta">{{ $summary['checklist_done'] }} / {{ $summary['checklist_total'] }}</span>
                </div>
                <div class="mb-3">
                    <a
                        href="{{ \App\Filament\Resources\ChecklistTemplateResource::getUrl('index') }}"
                        target="_blank"
                        rel="noopener"
                        class="text-sm font-medium"
                        style="color: var(--pilot-accent-text);"
                    >Szablony checklisty →</a>
                </div>
                @livewire('pilot-event-checklist', ['eventId' => $record->id], key('admin-pilot-checklist-'.$record->id))
            </div>
        </div>
    </div>

    <x-filament-panels::page.unsaved-data-changes-alert />
</x-filament-panels::page>
