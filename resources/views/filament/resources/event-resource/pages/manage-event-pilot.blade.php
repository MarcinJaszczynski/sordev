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
                                    wire:navigate="false"
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
                <p class="pilot-stat-label">Status odprawy</p>
                <p class="pilot-stat-value">{{ $summary['check_in_label'] }}</p>
            </div>
            <div class="pilot-stat-card">
                <p class="pilot-stat-label">Checklista</p>
                <p class="pilot-stat-value">{{ $summary['checklist_done'] }} / {{ $summary['checklist_total'] }}</p>
            </div>
            <div class="pilot-stat-card">
                <p class="pilot-stat-label">Wypłacono pilotowi</p>
                <p class="pilot-stat-value">{{ $summary['paid_label'] }}</p>
            </div>
            <div @class(['pilot-stat-card', 'pilot-stat-card--danger' => $summary['return_danger']])>
                <p class="pilot-stat-label">Do zwrotu</p>
                <p class="pilot-stat-value">{{ $summary['return_label'] }}</p>
            </div>
        </div>

        <div class="pilot-subtabs" role="tablist" aria-label="Sekcje pilota">
            <button type="button" role="tab" wire:click="setPilotTab('briefing')" @class(['pilot-subtab', 'is-active' => $this->pilotTab === 'briefing']) aria-selected="{{ $this->pilotTab === 'briefing' ? 'true' : 'false' }}">
                Odprawa i pilot
            </button>
            <button type="button" role="tab" wire:click="setPilotTab('cash')" @class(['pilot-subtab', 'is-active' => $this->pilotTab === 'cash']) aria-selected="{{ $this->pilotTab === 'cash' ? 'true' : 'false' }}">
                Gotówka i rozliczenia
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
                    @if ($record->showsPilotCurrencyExchange())
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
                    @else
                        <div class="pilot-card pilot-card--tight pilot-card--muted">
                            <p class="pilot-card-title">Wymiana walut</p>
                            <p class="pilot-card-meta mt-1">Włącz „Portal: wymiana” powyżej, żeby pokazać formularz tutaj i w panelu pilota.</p>
                        </div>
                    @endif

                    @if ($record->showsPilotBusCollections())
                        <div id="bus-collections" class="pilot-card pilot-card--tight">
                            <div class="pilot-card-header">
                                <div>
                                    <p class="pilot-card-title">Zbiórki w autokarze</p>
                                    <p class="pilot-card-meta">Przy wymianie — ta sama gotówka operacyjna.</p>
                                </div>
                            </div>
                            @livewire('event-bus-collections', ['event' => $record], key('admin-bus-collections-'.$record->id))
                        </div>
                    @else
                        <div class="pilot-card pilot-card--tight pilot-card--muted">
                            <p class="pilot-card-title">Zbiórki w autokarze</p>
                            <p class="pilot-card-meta mt-1">Włącz „Portal: zbiórka” powyżej, żeby pokazać formularz tutaj i w panelu pilota.</p>
                        </div>
                    @endif
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
