<x-filament-panels::page>

    <div class="mb-4 space-y-3">
        <div class="flex flex-wrap gap-2">
            <x-filament::button
                size="sm"
                :color="$layoutMode === 'calendar' ? 'primary' : 'gray'"
                wire:click="setLayoutMode('calendar')"
            >Miesiąc / lista</x-filament::button>
            <x-filament::button
                size="sm"
                :color="$layoutMode === 'resources' ? 'primary' : 'gray'"
                wire:click="setLayoutMode('resources')"
            >Zasoby (Gantt-lite)</x-filament::button>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
            <div class="mb-2 flex items-center gap-2">
                <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Typy wpisów</span>
                <span class="text-xs text-gray-400 dark:text-gray-500">kliknij, aby włączyć / wyłączyć</span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @foreach([
                    'events' => ['label' => 'Imprezy', 'color' => '#2563eb'],
                    'tasks' => ['label' => 'Zadania', 'color' => '#7c3aed'],
                    'ksef' => ['label' => 'KSeF', 'color' => '#dc2626'],
                    'payments' => ['label' => 'Płatności', 'color' => '#ea580c'],
                    'pilots' => ['label' => 'Zaliczki pilota', 'color' => '#0d9488'],
                    'reservations' => ['label' => 'Rezerwacje', 'color' => '#0891b2'],
                    'transport' => ['label' => 'Transport', 'color' => '#4f46e5'],
                    'hotels' => ['label' => 'Hotele', 'color' => '#be185d'],
                ] as $type => $meta)
                    @php
                        $isEnabled = in_array($type, $enabledTypes, true);
                    @endphp
                    <button
                        type="button"
                        wire:click="toggleType('{{ $type }}')"
                        title="{{ $isEnabled ? 'Wyłącz z kalendarza' : 'Pokaż w kalendarzu' }}: {{ $meta['label'] }}"
                        class="rounded-full px-3 py-1 text-sm font-medium border transition {{ $isEnabled ? '' : 'opacity-45' }}"
                        style="border-color: {{ $meta['color'] }}; {{ $isEnabled ? 'background:'.$meta['color'].';color:#fff;' : 'background:#fff;color:'.$meta['color'].';' }}"
                        @if($isEnabled) aria-pressed="true" @else aria-pressed="false" @endif
                    >
                        {{ $meta['label'] }}
                    </button>
                @endforeach
            </div>
        </div>

        <div class="rounded-xl border border-violet-200 bg-violet-50/60 p-3 dark:border-violet-800 dark:bg-violet-950/30">
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold uppercase tracking-wide text-violet-900 dark:text-violet-200">Filtry zadań</span>
                    <span class="text-xs text-violet-700/80 dark:text-violet-300/80">dotyczą tylko fioletowych wpisów</span>
                </div>

                @if($this->hasActiveTaskFilters())
                    <button
                        type="button"
                        wire:click="resetTaskFilters"
                        class="rounded-full border border-violet-400 bg-white px-3 py-1 text-xs font-semibold text-violet-800 hover:bg-violet-100 dark:border-violet-600 dark:bg-gray-900 dark:text-violet-200 dark:hover:bg-violet-900/40"
                    >
                        Reset filtrów zadań
                    </button>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @include('filament.tasks.ownership-quick-filters', ['tasksScope' => $this->tasksScope])

                <label class="flex items-center gap-2 rounded-full border border-violet-300 bg-white px-3 py-1 text-sm dark:border-violet-700 dark:bg-gray-900">
                    <input type="checkbox" wire:model.live="tasksOnlyUrgent" class="rounded border-gray-400" />
                    <span class="text-violet-800 dark:text-violet-200">Tylko pilne</span>
                </label>

                <label class="flex items-center gap-2 rounded-full border border-violet-300 bg-white px-3 py-1 text-sm dark:border-violet-700 dark:bg-gray-900">
                    <input type="checkbox" wire:model.live="showFinishedTasks" class="rounded border-gray-400" />
                    <span class="text-violet-800 dark:text-violet-200">Pokaż zakończone i anulowane</span>
                </label>
            </div>
        </div>
    </div>

    @if($layoutMode === 'resources')
        @php $timeline = $this->resourceTimeline; @endphp
        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2">Zasób</th>
                        <th class="px-3 py-2">Przypisania</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse(($timeline['resources'] ?? []) as $resource)
                        @php
                            $rows = collect($timeline['events'] ?? [])
                                ->where('resourceId', $resource['id'] ?? null)
                                ->values();
                        @endphp
                        <tr>
                            <td class="px-3 py-2 align-top font-semibold text-gray-900 dark:text-gray-100 whitespace-nowrap">
                                {{ $resource['title'] ?? '—' }}
                            </td>
                            <td class="px-3 py-2">
                                @if($rows->isEmpty())
                                    <span class="text-gray-400">—</span>
                                @else
                                    <ul class="space-y-1">
                                        @foreach($rows as $row)
                                            <li>
                                                <span class="inline-flex rounded px-2 py-0.5 text-xs font-medium text-white"
                                                      style="background: {{ $row['backgroundColor'] ?? '#64748b' }}">
                                                    {{ $row['start'] ?? '' }}@if(!empty($row['end'])) → {{ $row['end'] }}@endif
                                                    · {{ $row['title'] ?? '' }}
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-3 py-6 text-center text-gray-500">Brak zasobów w zakresie dat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        @if(count($this->calendarEvents) === 0)
            <div class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
                Brak wpisów dla wybranych filtrów w załadowanym okresie. Zmień typy / filtry zadań albo przejdź do innego miesiąca strzałkami kalendarza.
            </div>
        @endif

        <script
            type="application/json"
            id="operations-calendar-events"
            wire:key="operations-calendar-events-{{ md5(json_encode([
                $enabledTypes,
                $this->tasksScope,
                $this->tasksOnlyUrgent,
                $this->showFinishedTasks,
                $this->visibleFrom,
                $this->visibleTo,
                count($this->calendarEvents),
            ])) }}"
        >@json($this->calendarEvents)</script>

        <div
            id="operations-calendar"
            wire:ignore
            class="operations-calendar-shell min-h-[32rem] rounded-xl border border-gray-200 bg-white p-2 dark:border-gray-700 dark:bg-gray-900"
        ></div>

        @include('filament.components.fullcalendar-boot', [
            'elementId' => 'operations-calendar',
            'livewireId' => $this->getId(),
            'initialDate' => $this->initialCalendarDate,
            'syncVisibleRange' => true,
        ])
    @endif

    <style>
        .operations-calendar-shell .fc-daygrid-day-frame {
            overflow: hidden;
            min-height: 6.5rem;
        }
        .operations-calendar-shell .fc-daygrid-day-events {
            margin: 0;
            min-height: 0;
        }
        .operations-calendar-shell .fc-daygrid-event {
            max-width: 100%;
            margin: 1px 2px !important;
            overflow: hidden;
        }
        .operations-calendar-shell .fc-daygrid-event .fc-event-main,
        .operations-calendar-shell .fc-daygrid-event .fc-event-title {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: block;
            line-height: 1.25;
        }
        .operations-calendar-shell .fc-daygrid-block-event .fc-event-main {
            padding: 1px 4px;
        }
        .operations-calendar-completed .fc-event-title {
            text-decoration: line-through;
            opacity: 0.55;
        }
        .operations-calendar-urgent .fc-event-title {
            font-weight: 800;
        }
        .fc-event.task-has-preview {
            cursor: pointer;
        }
        .fc-event,
        .fc-event .fc-event-main {
            cursor: pointer;
        }
        .fc-event {
            text-decoration: none !important;
        }
    </style>
</x-filament-panels::page>
