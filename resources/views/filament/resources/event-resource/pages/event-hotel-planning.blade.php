<x-filament-panels::page>
    @php
        $hotelServicesRm = \App\Filament\Resources\EventResource\RelationManagers\EventHotelServicesRelationManager::class;
        $occupancy = $this->occupancySummary();
        $headerMeta = $this->hotelHeaderMeta();
        $nightsLabel = $headerMeta['nights'] === 1 ? '1 noc' : $headerMeta['nights'].' nocy';
        $gratisLabel = \App\Support\EventParticipantGroupLabels::GRATIS;
        $programRows = $hotelTab === 'program' ? $this->hotelProgramOverviewRows() : [];
        $hotelReadiness = \App\Support\EventReadinessIndicators::forEvent($this->record);
        $hotelReadinessItem = collect($hotelReadiness)->firstWhere('key', 'hotel') ?? [];
        $readinessToneClass = match ($hotelReadinessItem['tone'] ?? 'muted') {
            'ok' => 'hotel-readiness-pill--success',
            'warn' => 'hotel-readiness-pill--warning',
            'danger' => 'hotel-readiness-pill--danger',
            default => 'hotel-readiness-pill--muted',
        };
    @endphp

    <div class="hotel-page-root space-y-0">
        @include('filament.resources.event-resource.pages.partials.hotel-page-styles')

        <div class="hotel-status-bar hotel-status-bar--compact">
            <div class="hotel-status-title">
                <span class="hotel-status-icon" aria-hidden="true">🛏️</span>
                <div class="min-w-0">
                    <p class="hotel-status-main">
                        Hotele — {{ $this->record->name ?? 'Impreza' }} · {{ $nightsLabel }}
                        <span class="hotel-status-inline-meta">· {{ $occupancy['required_beds_per_night'] }} miejsc/noc</span>
                    </p>
                </div>
            </div>

            <span
                class="hotel-readiness-pill {{ $readinessToneClass }}"
                title="{{ $hotelReadinessItem['title'] ?? 'Gotowość hotelu' }}"
            >
                Hotel: {{ $hotelReadinessItem['short'] ?? '—' }}
            </span>

            @if (count($headerMeta['night_hotels']) > 0)
                <div class="hotel-night-chips hotel-night-chips--inline">
                    @foreach ($headerMeta['night_hotels'] as $nightHotel)
                        <div class="hotel-night-chip hotel-night-chip--inline" title="{{ collect([
                            $nightHotel['name'] ?: 'Hotel nie wybrany',
                            $nightHotel['address'] ?? null,
                            $nightHotel['phone'] ? 'tel. '.$nightHotel['phone'] : null,
                            $nightHotel['email'] ?? null,
                        ])->filter()->implode(' · ') }}">
                            <span class="hotel-night-chip-day">N{{ $nightHotel['day'] }}@if ($nightHotel['date']) {{ $nightHotel['date'] }}@endif</span>
                            @if ($nightHotel['name'])
                                @if ($nightHotel['edit_url'])
                                    <a href="{{ $nightHotel['edit_url'] }}" class="hotel-night-chip-link" wire:navigate="false">{{ $nightHotel['name'] }}</a>
                                @else
                                    <span class="hotel-night-chip-name">{{ $nightHotel['name'] }}</span>
                                @endif
                                @if ($nightHotel['phone'])
                                    <span class="hotel-night-chip-meta">{{ $nightHotel['phone'] }}</span>
                                @endif
                            @else
                                <span class="hotel-night-chip-meta">brak hotelu</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="hotel-stat-grid">
            <div class="hotel-stat-card">
                <p class="hotel-stat-label">Uczestnicy / {{ strtolower($gratisLabel) }}</p>
                <p class="hotel-stat-value">{{ $occupancy['participants'] }} + {{ $occupancy['gratis'] }}</p>
            </div>
            <div class="hotel-stat-card">
                <p class="hotel-stat-label">Pilot/obsługa / kierowcy</p>
                <p class="hotel-stat-value">{{ $occupancy['pilot_staff'] }} / {{ $occupancy['drivers'] }}</p>
            </div>
            <div class="hotel-stat-card">
                <p class="hotel-stat-label">Suma noclegów</p>
                <p class="hotel-stat-value hotel-stat-value--money">{{ $headerMeta['lodging_total_label'] }}</p>
            </div>
            <div class="hotel-stat-card">
                <p class="hotel-stat-label">Potrzebne miejsca / noc</p>
                <p class="hotel-stat-value">{{ $occupancy['required_beds_per_night'] }}</p>
            </div>
        </div>

        <div class="hotel-subtabs" role="tablist" aria-label="Sekcje hoteli">
            <button type="button" role="tab" wire:click="setHotelTab('overview')" @class(['hotel-subtab', 'is-active' => $hotelTab === 'overview']) aria-selected="{{ $hotelTab === 'overview' ? 'true' : 'false' }}">
                Przegląd nocy
            </button>
            <button type="button" role="tab" wire:click="setHotelTab('planning')" @class(['hotel-subtab', 'is-active' => $hotelTab === 'planning']) aria-selected="{{ $hotelTab === 'planning' ? 'true' : 'false' }}">
                Planowanie pokoi
            </button>
            <button type="button" role="tab" wire:click="setHotelTab('program')" @class(['hotel-subtab', 'is-active' => $hotelTab === 'program']) aria-selected="{{ $hotelTab === 'program' ? 'true' : 'false' }}">
                Program przy hotelach
            </button>
            <button type="button" role="tab" wire:click="setHotelTab('services')" @class(['hotel-subtab', 'is-active' => $hotelTab === 'services']) aria-selected="{{ $hotelTab === 'services' ? 'true' : 'false' }}">
                Usługi i dokumenty
            </button>
        </div>

        <div @class(['hidden' => $hotelTab !== 'overview'])>
            @livewire('event-hotel-stays-finance-panel', [
                'eventId' => $this->record->id,
                'variant' => 'overview',
            ], key('hotel-stays-overview-'.$this->record->id))
        </div>

        <div @class(['hidden' => $hotelTab !== 'planning'])>
            @livewire('event-hotel-plan-editor', [
                'eventId' => $this->record->id,
            ], key('event-hotel-plan-'.$this->record->id))
        </div>

        <div @class(['hidden' => $hotelTab !== 'program'])>
            <div class="hotel-card">
                <div class="hotel-card-header">
                    <div>
                        <p class="hotel-card-title">Program powiązany z hotelami</p>
                        <p class="text-xs text-gray-500">Noclegi i usługi hotelowe — dzień, obiekt, punkt i kwota.</p>
                    </div>
                </div>

                @if (count($programRows) === 0)
                    <p class="text-sm text-gray-600">Brak punktów programu oznaczonych jako hotel / usługa hotelowa.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="hotel-overview-table">
                            <thead>
                                <tr>
                                    <th>Dzień</th>
                                    <th>Godz.</th>
                                    <th>Typ</th>
                                    <th>Punkt</th>
                                    <th>Hotel</th>
                                    <th>Kwota</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($programRows as $row)
                                    <tr wire:key="hotel-program-row-{{ $row['id'] }}">
                                        <td>{{ $row['day'] ?: '—' }}</td>
                                        <td>{{ $row['time'] ?: '—' }}</td>
                                        <td>{{ $row['kind'] }}</td>
                                        <td>{{ $row['name'] }}</td>
                                        <td @class(['is-muted' => blank($row['hotel'])])>{{ $row['hotel'] ?: '—' }}</td>
                                        <td class="tabular-nums">{{ $row['price_label'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div @class(['hidden' => $hotelTab !== 'services'])>
            <div class="hotel-services-columns">
                <div class="hotel-services-col">
                    <div class="hotel-card hotel-card--flush">
                        <div class="hotel-card-header">
                            <p class="hotel-card-title">Usługi hotelu</p>
                        </div>
                        @livewire($hotelServicesRm, [
                            'ownerRecord' => $this->record,
                            'pageClass' => \App\Filament\Resources\EventResource\Pages\EventHotelPlanning::class,
                        ], key('event-hotel-services-'.$this->record->id))
                    </div>
                </div>

                <div class="hotel-services-col">
                    @livewire('event-hotel-documents-panel', ['eventId' => $this->record->id], key('event-hotel-documents-'.$this->record->id))
                </div>

                <div class="hotel-services-col">
                    <div class="hotel-card">
                        <div class="hotel-card-header">
                            <p class="hotel-card-title">Notatki/ustalenia</p>
                        </div>
                        @include('filament.components.sticky-notes-stack', [
                            'notableType' => \App\Models\Event::class,
                            'notableId' => $this->record->id,
                            'title' => 'Notatki/ustalenia',
                            'compact' => true,
                            'filterCategory' => \App\Support\StickyNotes\StickyNoteCategory::HOTEL,
                        ])
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
