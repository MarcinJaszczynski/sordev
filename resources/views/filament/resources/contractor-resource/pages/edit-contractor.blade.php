<x-filament-panels::page
    @class([
        'fi-resource-edit-record-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
        'fi-resource-record-' . $record->getKey(),
    ])
>
    @php
        /** @var array<string, mixed> $summary */
        $summary = $this->contractorPageSummary();
        $overview = $summary['overview'];
        $archive = $summary['archive'];
        $contactBits = array_values(array_filter([
            $summary['nip'] ? 'NIP '.$summary['nip'] : null,
            $summary['phone'] ?? null,
            $summary['email'] ?? null,
        ]));
        $daneRelationManagers = $this->daneRelationManagers();
        $archiveRelationManagers = $this->archiveRelationManagers();
        $hasArchiveTab = $this->hasArchiveTab();
        $rows = $this->filteredInvolvementRows();
        $totalRows = count($overview['rows'] ?? []);
        $roleOptions = $this->involvementRoleOptions();
        $archiveTotal = (int) ($archive['total'] ?? 0);
    @endphp

    <div class="space-y-0 contractor-page-root">
        @include('filament.resources.contractor-resource.pages.partials.contractor-page-styles')

        <div class="contractor-status-bar">
            <div class="contractor-status-main-col">
                <div class="contractor-status-title">
                    <span class="contractor-status-icon" aria-hidden="true">🏢</span>
                    <div class="min-w-0">
                        <p class="contractor-status-main">{{ $summary['name'] }}</p>
                        @if ($contactBits !== [])
                            <p class="contractor-status-sub">{{ implode(' · ', $contactBits) }}</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="contractor-badges">
                <span @class([
                    'contractor-badge',
                    'contractor-badge--success' => $summary['status'] === 'active',
                    'contractor-badge--muted' => $summary['status'] !== 'active',
                ])>{{ $summary['status_label'] }}</span>
                @foreach ($summary['types'] as $typeName)
                    <span class="contractor-badge contractor-badge--accent">{{ $typeName }}</span>
                @endforeach
            </div>
        </div>

        <div class="contractor-stat-grid">
            <button
                type="button"
                wire:click="setInvolvementKind('event')"
                @class(['contractor-stat-card', 'contractor-stat-card--clickable', 'is-active' => $this->involvementKind === 'event' && $this->contractorTab === 'udzial'])
            >
                <p class="contractor-stat-label">Imprezy (bieżące)</p>
                <p class="contractor-stat-value">{{ $overview['summary']['events_count'] }}</p>
            </button>
            <button
                type="button"
                wire:click="setInvolvementKind('point')"
                @class(['contractor-stat-card', 'contractor-stat-card--clickable', 'is-active' => $this->involvementKind === 'point' && $this->contractorTab === 'udzial'])
            >
                <p class="contractor-stat-label">Punkty programu</p>
                <p class="contractor-stat-value">{{ $overview['summary']['points_count'] }}</p>
            </button>
            <button
                type="button"
                wire:click="setInvolvementKind('cost')"
                @class(['contractor-stat-card', 'contractor-stat-card--clickable', 'is-active' => $this->involvementKind === 'cost' && $this->contractorTab === 'udzial'])
            >
                <p class="contractor-stat-label">Koszty (planowane)</p>
                <p class="contractor-stat-value">{{ $overview['summary']['planned_pln_label'] }}</p>
            </button>
            <button
                type="button"
                wire:click="setInvolvementKind('invoice')"
                @class([
                    'contractor-stat-card',
                    'contractor-stat-card--clickable',
                    'contractor-stat-card--danger' => $overview['summary']['due_danger'],
                    'is-active' => $this->involvementKind === 'invoice' && $this->contractorTab === 'udzial',
                ])
            >
                <p class="contractor-stat-label">Do zapłaty / faktury</p>
                <p class="contractor-stat-value">{{ $overview['summary']['due_pln_label'] }}</p>
            </button>
        </div>

        @if ($hasArchiveTab && $archiveTotal > 0)
            <div class="contractor-stat-grid" style="margin-top:-4px;">
                <button
                    type="button"
                    wire:click="setContractorTab('archiwalne')"
                    @class(['contractor-stat-card', 'contractor-stat-card--clickable', 'is-active' => $this->contractorTab === 'archiwalne'])
                >
                    <p class="contractor-stat-label">Archiwum — imprezy</p>
                    <p class="contractor-stat-value">{{ $archive['total'] }}</p>
                    <p class="contractor-stat-label" style="margin-top:4px;">okres {{ $archive['years_label'] }}</p>
                </button>
                <div class="contractor-stat-card" style="background:var(--contractor-success-bg);">
                    <p class="contractor-stat-label">Pojechało (zakończone)</p>
                    <p class="contractor-stat-value">{{ $archive['completed'] }}</p>
                    <p class="contractor-stat-label" style="margin-top:4px;">{{ number_format($archive['participants_completed'], 0, ',', ' ') }} osób</p>
                </div>
                <div class="contractor-stat-card contractor-stat-card--danger">
                    <p class="contractor-stat-label">Anulowane</p>
                    <p class="contractor-stat-value">{{ $archive['cancelled'] }}</p>
                    <p class="contractor-stat-label" style="margin-top:4px;">inne statusy: {{ $archive['other'] }}</p>
                </div>
                <div class="contractor-stat-card">
                    <p class="contractor-stat-label">Osoby łącznie (archiwum)</p>
                    <p class="contractor-stat-value">{{ number_format($archive['participants_all'], 0, ',', ' ') }}</p>
                    <p class="contractor-stat-label" style="margin-top:4px;">
                        zam. {{ $archive['as_purchaser'] ?? 0 }} · wyk. {{ $archive['as_executor'] ?? 0 }}
                    </p>
                </div>
            </div>
        @endif

        <div class="contractor-subtabs" role="tablist" aria-label="Sekcje kontrahenta">
            <button type="button" role="tab" wire:click="setContractorTab('dane')" @class(['contractor-subtab', 'is-active' => $this->contractorTab === 'dane']) aria-selected="{{ $this->contractorTab === 'dane' ? 'true' : 'false' }}">
                Dane
            </button>
            <button type="button" role="tab" wire:click="setContractorTab('udzial')" @class(['contractor-subtab', 'is-active' => $this->contractorTab === 'udzial']) aria-selected="{{ $this->contractorTab === 'udzial' ? 'true' : 'false' }}">
                Udział
                <span class="text-xs opacity-70">({{ $totalRows }})</span>
            </button>
            @if ($hasArchiveTab)
                <button type="button" role="tab" wire:click="setContractorTab('archiwalne')" @class(['contractor-subtab', 'is-active' => $this->contractorTab === 'archiwalne']) aria-selected="{{ $this->contractorTab === 'archiwalne' ? 'true' : 'false' }}">
                    Imprezy archiwalne
                    @if ($archiveTotal > 0)
                        <span class="text-xs opacity-70">({{ $archiveTotal }})</span>
                    @endif
                </button>
            @endif
        </div>

        <div @class(['hidden' => $this->contractorTab !== 'dane'])>
            <x-filament-panels::form
                id="form"
                :wire:key="$this->getId() . '.forms.' . $this->getFormStatePath()"
                wire:submit="save"
            >
                {{ $this->form }}

                <div class="contractor-sticky-actions">
                    <x-filament-panels::form.actions
                        :actions="$this->getCachedFormActions()"
                        :full-width="$this->hasFullWidthFormActions()"
                    />
                </div>
            </x-filament-panels::form>

            @if ($this->contractorTab === 'dane' && count($daneRelationManagers))
                <div class="mt-4" wire:key="contractor-dane-rms">
                    <x-filament-panels::resources.relation-managers
                        :active-locale="isset($activeLocale) ? $activeLocale : null"
                        :active-manager="$this->activeRelationManager ?? array_key_first($daneRelationManagers)"
                        :managers="$daneRelationManagers"
                        :owner-record="$record"
                        :page-class="static::class"
                    />
                </div>
            @endif
        </div>

        @if ($this->contractorTab === 'archiwalne')
            <div wire:key="contractor-archive-rms">
                @if (count($archiveRelationManagers))
                    <x-filament-panels::resources.relation-managers
                        :active-locale="isset($activeLocale) ? $activeLocale : null"
                        :active-manager="array_key_first($archiveRelationManagers)"
                        :managers="$archiveRelationManagers"
                        :owner-record="$record"
                        :page-class="static::class"
                    />
                @else
                    <div class="contractor-empty">Brak tabeli imprez archiwalnych.</div>
                @endif
            </div>
        @endif

        <div @class(['hidden' => $this->contractorTab !== 'udzial'])>
            <div class="contractor-toolbar">
                <div class="contractor-toolbar-search">
                    <input
                        type="search"
                        wire:model.live.debounce.300ms="involvementSearch"
                        placeholder="Szukaj: nazwa, kod, impreza, status…"
                        class="contractor-input"
                    />
                </div>

                <div class="contractor-toolbar-filters">
                    <div class="contractor-filter-chips" role="group" aria-label="Typ pozycji">
                        @foreach ([
                            'all' => 'Wszystkie',
                            'event' => 'Imprezy',
                            'point' => 'Punkty',
                            'cost' => 'Koszty',
                            'invoice' => 'Faktury',
                        ] as $kind => $label)
                            <button
                                type="button"
                                wire:click="$set('involvementKind', '{{ $kind }}')"
                                @class(['contractor-filter-chip', 'is-active' => $this->involvementKind === $kind])
                            >{{ $label }}</button>
                        @endforeach
                    </div>

                    <select wire:model.live="involvementRole" class="contractor-select" aria-label="Powiązanie">
                        @foreach ($roleOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>

                    <select wire:model.live="involvementStatus" class="contractor-select" aria-label="Status płatności">
                        <option value="all">Każdy status</option>
                        <option value="open">Do zapłaty / otwarte</option>
                        <option value="paid">Opłacone</option>
                    </select>
                </div>

                <p class="contractor-toolbar-count">
                    {{ count($rows) }} / {{ $totalRows }}
                </p>
            </div>

            @if ($rows === [])
                <div class="contractor-empty">Brak pozycji dla wybranych filtrów.</div>
            @else
                <div class="contractor-table-wrap">
                    <table class="contractor-table">
                        <thead>
                            <tr>
                                <th>Typ</th>
                                <th>Nazwa</th>
                                <th>Impreza</th>
                                <th>Powiązanie</th>
                                <th>Status</th>
                                <th class="text-right">Kwota</th>
                                <th>Data</th>
                                <th class="text-right"> </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr wire:key="involvement-{{ $row['id'] }}">
                                    <td>
                                        <span class="contractor-badge contractor-badge--{{ $row['kind_variant'] }}">{{ $row['kind_label'] }}</span>
                                    </td>
                                    <td>
                                        <div class="contractor-table-title">
                                            @if ($row['url'])
                                                <a href="{{ $row['url'] }}">{{ $row['title'] }}</a>
                                            @else
                                                {{ $row['title'] }}
                                            @endif
                                        </div>
                                        @if (! empty($row['subtitle']))
                                            <div class="contractor-table-sub">{{ $row['subtitle'] }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @if (! empty($row['event_name']) && ($row['kind'] ?? '') !== 'event')
                                            @if ($row['event_url'])
                                                <a href="{{ $row['event_url'] }}" class="contractor-table-link">{{ $row['event_name'] }}</a>
                                            @else
                                                {{ $row['event_name'] }}
                                            @endif
                                            @if (! empty($row['event_code']))
                                                <div class="contractor-table-sub">{{ $row['event_code'] }}</div>
                                            @endif
                                        @elseif (($row['kind'] ?? '') === 'event' && ! empty($row['event_code']))
                                            <span class="contractor-table-sub">{{ $row['event_code'] }}</span>
                                        @else
                                            <span class="contractor-table-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="contractor-chip-row">
                                            @forelse ($row['relation_labels'] ?? [] as $relLabel)
                                                <span class="contractor-badge contractor-badge--muted">{{ $relLabel }}</span>
                                            @empty
                                                <span class="contractor-table-muted">—</span>
                                            @endforelse
                                        </div>
                                    </td>
                                    <td>
                                        <span
                                            class="contractor-badge contractor-badge--{{ $row['status_variant'] }}"
                                            @if (! empty($row['status_tooltip']))
                                                title="{{ $row['status_tooltip'] }}"
                                            @endif
                                        >{{ $row['status_label'] }}</span>
                                    </td>
                                    <td class="text-right contractor-table-amount">{{ $row['amount_label'] }}</td>
                                    <td class="contractor-table-date">{{ $row['date_label'] }}</td>
                                    <td class="text-right">
                                        @if ($row['url'])
                                            <a href="{{ $row['url'] }}" class="contractor-row-link" title="Otwórz">→</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <x-filament-panels::page.unsaved-data-changes-alert />
</x-filament-panels::page>
