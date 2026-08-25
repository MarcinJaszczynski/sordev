<x-filament-panels::page
    @class([
        'fi-resource-edit-record-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
        'fi-resource-record-' . $record->getKey(),
    ])
>
    <div class="space-y-6 transport-page-root">
        @include('filament.resources.event-resource.pages.partials.transport-page-styles')

        @php
            /** @var \App\Models\Event $record */
            $statusLabel = \App\Models\Event::getStatusOptions()[$record->status] ?? (string) $record->status;
            $statusColor = \App\Models\Event::statusBadgeColor($record->status);

            $driverSent = method_exists($record, 'isDriverPickupInfoSent') ? $record->isDriverPickupInfoSent() : false;
            $driverBadgeLabel = $driverSent ? 'Wysłano do kierowcy' : 'Nie wysłano do kierowcy';

            $driverBadgeClass = $driverSent ? 'transport-badge--success' : 'transport-badge--warning';

            $statusBadgeClass = match ($statusColor) {
                'success' => 'transport-badge--success',
                'warning' => 'transport-badge--warning',
                'danger' => 'transport-badge--danger',
                default => 'transport-badge--muted',
            };

            $start = $record->start_date?->format('d.m.Y');
            $end = $record->end_date?->format('d.m.Y');
            $range = $start
                ? ($end && $end !== $start ? "{$start}–{$end}" : $start)
                : null;

            $busName = $record->bus?->name;
            $from = $record->startPlace?->name;
            $to = $record->endPlace?->name;
            $routeLine = $busName && $from && $to
                ? "{$busName} · {$from} → {$to}"
                : ($busName ? $busName : '—');
        @endphp

        {{-- 1) Pasek statusu --}}
        <div class="transport-status-bar">
            <div class="transport-status-title">
                <span class="transport-status-icon">🚌</span>
                <div>
                    <p class="transport-status-main">{{ $record->name ?? 'Impreza' }}</p>
                    <p class="transport-status-sub">{{ $routeLine }}</p>
                </div>
            </div>

            <div class="transport-badges">
                @if ($range)
                    <span class="transport-badge transport-badge--muted" title="Data od – do">{{ $range }}</span>
                @endif
                <span class="transport-badge {{ $driverBadgeClass }}">{{ $driverBadgeLabel }}</span>
                <span class="transport-badge {{ $statusBadgeClass }}">{{ $statusLabel }}</span>
            </div>
        </div>

        {{-- Panel finansów poza formularzem EditRecord — nested Livewire w Placeholderze psuje akcje Filament. --}}
        @livewire('settlement-aggregate-finance-panel', [
            'eventId' => $this->record->getKey(),
            'aggregateType' => 'transport',
            'heading' => 'Finanse transportu',
        ], key('transport-finance-'.$this->record->getKey()))

        @capture($form)
            <x-filament-panels::form
                id="form"
                :wire:key="$this->getId() . '.forms.' . $this->getFormStatePath()"
                wire:submit="save"
            >
                {{ $this->form }}

                {{-- 7) Sticky pasek akcji na dole (bez zmiany samych akcji Filament) --}}
                <div class="transport-sticky-actions">
                    <x-filament-panels::form.actions
                        :actions="$this->getCachedFormActions()"
                        :full-width="$this->hasFullWidthFormActions()"
                    />
                </div>
            </x-filament-panels::form>
        @endcapture

        {{ $form() }}
    </div>

    <x-filament-panels::page.unsaved-data-changes-alert />
</x-filament-panels::page>
