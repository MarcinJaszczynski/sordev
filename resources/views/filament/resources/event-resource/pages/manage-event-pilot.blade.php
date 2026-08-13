<x-filament-panels::page
    @class([
        'fi-resource-edit-record-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
        'fi-resource-record-' . $record->getKey(),
    ])
>
    @include('filament.resources.event-resource.components.operations-sub-navigation', ['record' => $record])

    @capture($form)
        <x-filament-panels::form
            id="form"
            :wire:key="$this->getId() . '.forms.' . $this->getFormStatePath()"
            wire:submit="save"
        >
            {{ $this->form }}

            <x-filament-panels::form.actions
                :actions="$this->getCachedFormActions()"
                :full-width="$this->hasFullWidthFormActions()"
            />
        </x-filament-panels::form>
    @endcapture

    {{ $form() }}

    <div class="mt-8 space-y-8">
        <x-filament::section
            icon="heroicon-o-clipboard-document-check"
            heading="Checklista"
            description="Biuro wybiera szablon; pilot tylko odznacza punkty."
        >
            <div class="mb-3">
                <a
                    href="{{ \App\Filament\Resources\ChecklistTemplateResource::getUrl('index') }}"
                    target="_blank"
                    rel="noopener"
                    class="text-sm font-medium text-primary-600 hover:underline"
                >Szablony checklisty →</a>
            </div>
            @livewire('pilot-event-checklist', ['eventId' => $record->id], key('admin-pilot-checklist-'.$record->id))
        </x-filament::section>

        <div id="pilot-cash-desk">
            <x-filament::section
                icon="heroicon-o-banknotes"
                heading="Rozliczenie — gotówka i wymiana walut"
                description="Te same formularze co w Finanse → Gotówka dla pilota. Przełączniki „Portal: wymiana / zbiórka” chowają odpowiednie sekcje tak jak w portalu pilota."
            >
                @livewire('pilot-cash-desk', [
                    'event' => $record,
                    'context' => 'admin',
                    'compact' => false,
                    'respectPortalVisibility' => true,
                ], key('admin-pilot-cash-'.$record->id))
            </x-filament::section>
        </div>

        <x-filament::section
            icon="heroicon-o-table-cells"
            heading="Rozliczenie — koszty pilota"
            description="Tylko pozycje z płatnikiem Pilot (ta sama logika co Finanse)."
        >
            @php
                $pilotOverview = app(\App\Services\EventFinanceOverviewService::class)->forEvent(
                    $record,
                    \App\Services\EventFinanceOverviewService::FILTER_PILOT,
                );
                $pilotRows = $pilotOverview['rows'] ?? [];
            @endphp

            @if (empty($pilotRows))
                <p class="text-sm text-gray-500">Brak pozycji z płatnikiem Pilot.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-left text-gray-500">
                                <th class="py-2 pr-3">Nazwa</th>
                                <th class="py-2 pr-3">Kalkulacja</th>
                                <th class="py-2 pr-3">Plan</th>
                                <th class="py-2 pr-3">Zapłacone</th>
                                <th class="py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pilotRows as $row)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="py-2 pr-3 font-medium">{{ $row['name'] ?? '—' }}</td>
                                    <td class="py-2 pr-3">{{ $row['calculation_label'] ?? $row['calculation_display'] ?? '—' }}</td>
                                    <td class="py-2 pr-3">{{ $row['planned_label'] ?? $row['planned_display'] ?? '—' }}</td>
                                    <td class="py-2 pr-3">{{ $row['paid_label'] ?? $row['paid_display'] ?? '—' }}</td>
                                    <td class="py-2">{{ \App\Services\EventFinanceOverviewService::$uiStatusLabels[$row['ui_status'] ?? ''] ?? ($row['ui_status'] ?? '—') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-2 text-xs text-gray-500">
                    Pełna edycja:
                    <a
                        href="{{ \App\Filament\Resources\EventResource::getUrl('finance', ['record' => $record]) }}"
                        class="text-primary-600 underline"
                    >Finanse imprezy</a>
                </p>
            @endif
        </x-filament::section>

        @if ($record->showsPilotBusCollections())
            <div id="bus-collections">
                <x-filament::section
                    icon="heroicon-o-banknotes"
                    heading="Zbiórki w autokarze"
                >
                    @livewire('event-bus-collections', ['event' => $record], key('admin-bus-collections-'.$record->id))
                </x-filament::section>
            </div>
        @endif
    </div>

    <x-filament-panels::page.unsaved-data-changes-alert />
</x-filament-panels::page>
