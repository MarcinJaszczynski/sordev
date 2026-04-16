<div class="space-y-3">
    <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
        W plannerze widzisz tylko elementy oznaczone jako <b>uwzględnione w programie</b>.
        Przeciągaj i rozciągaj bloki, aby zmieniać kolejność i czas — zmiany są zapisywane automatycznie.
        <b>Kliknij blok</b>, aby otworzyć okno edycji. <b>Zaznacz pusty obszar</b> w kalendarzu, aby dodać nowy punkt programu.
    </div>

    <div class="rounded-lg border border-blue-200 bg-blue-50 p-3">
            <h4 class="mb-2 text-sm font-semibold text-blue-900">Elementy poza programem (w rozpisce)</h4>

            @if($backlogPoints->isNotEmpty())
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <select wire:model="selectedBacklogPointId" class="w-full rounded border-gray-300 text-sm">
                        <option value="">Wybierz element do dodania...</option>
                        @php
                            $backlogDisplayOrderPerDay = [];
                        @endphp

                        @foreach($backlogPoints as $point)
                            @php
                                $backlogDay = max(1, (int) ($point->day ?? 1));
                                $backlogDisplayOrderPerDay[$backlogDay] = ($backlogDisplayOrderPerDay[$backlogDay] ?? 0) + 1;
                                $backlogDisplayOrder = $backlogDisplayOrderPerDay[$backlogDay];
                            @endphp

                            <option value="{{ $point->id }}">
                                Dzień {{ $backlogDay }} • {{ sprintf('%02d', $backlogDisplayOrder) }}. {{ $point->templatePoint?->name ?? $point->name ?? ('Punkt #' . $point->id) }}
                            </option>
                        @endforeach
                    </select>

                    <x-filament::button wire:click="addSelectedPointToProgram" color="primary" size="sm">
                        Dodaj do programu
                    </x-filament::button>
                </div>
            @else
                <p class="text-xs text-blue-800">Brak elementów poza programem.</p>
            @endif
        </div>

    <div class="flex items-center gap-2">
        <x-filament::button wire:click="repairOrderNow" color="gray" size="xs">
            Napraw kolejność teraz
        </x-filament::button>
        <span class="text-xs text-gray-500">Kliknij blok w kalendarzu, aby go edytować. Zaznacz pusty obszar, aby dodać nowy punkt.</span>
    </div>

    <div class="flex items-center justify-between gap-2">
        <span class="text-xs text-gray-500">Jeśli plan jest szerszy niż ekran, użyj strzałek lub przewijania poziomego.</span>
        <div class="flex items-center gap-1">
            <button
                type="button"
                class="rounded border border-gray-300 bg-white px-2 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                onclick="window.__scrollEventProgramPlanner?.('{{ $this->getId() }}', 'left')"
            >
                ← W lewo
            </button>
            <button
                type="button"
                class="rounded border border-gray-300 bg-white px-2 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                onclick="window.__scrollEventProgramPlanner?.('{{ $this->getId() }}', 'right')"
            >
                W prawo →
            </button>
        </div>
    </div>

    <div id="event-program-planner-scroll-{{ $this->getId() }}" class="event-program-planner-scroll w-full overflow-x-auto overflow-y-hidden">
        <div
            wire:ignore
            id="event-program-planner-{{ $this->getId() }}"
            class="event-program-planner-surface min-h-[680px] rounded-lg border border-gray-200 bg-white p-2"
        ></div>
    </div>

    {{-- Modal edycji punktu --}}
    @if($showEditModal)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            wire:click.self="closeModals"
        >
            <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-2xl">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900">Edycja punktu programu</h3>
                    <button wire:click="closeModals" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="space-y-4">
                    {{-- Nazwa --}}
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700">Nazwa punktu</label>
                        @if($editingData['is_custom'])
                            <input
                                type="text"
                                wire:model="editingData.custom_name"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                            >
                        @else
                            <p class="rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-700">{{ $editingData['name'] }}</p>
                        @endif
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700">Opis punktu</label>
                        <textarea
                            wire:model="editingData.description"
                            rows="3"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                        ></textarea>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700">Punkt nadrzędny (opcjonalnie)</label>
                        <select
                            wire:model="editingData.parent_id"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                        >
                            <option value="">Brak punktu nadrzędnego</option>
                            @foreach($parentPointOptions as $parentId => $parentLabel)
                                @if((int) $parentId !== (int) $editingPointId)
                                    <option value="{{ $parentId }}">{{ $parentLabel }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>

                    {{-- Dzień + czasy --}}
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-700">Dzień</label>
                            <input
                                type="number"
                                wire:model="editingData.day"
                                min="1"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                            >
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-700">Godz. start</label>
                            <input
                                type="time"
                                wire:model="editingData.start_time"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                            >
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-700">Godz. koniec</label>
                            <input
                                type="time"
                                wire:model="editingData.end_time"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                            >
                        </div>
                    </div>

                    {{-- Notatki biura --}}
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700">Notatki biura</label>
                        <textarea
                            wire:model="editingData.office_notes"
                            rows="2"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                        ></textarea>
                    </div>

                    {{-- Notatki pilota --}}
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700">Notatki pilota</label>
                        <textarea
                            wire:model="editingData.pilot_notes"
                            rows="2"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                        ></textarea>
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-between gap-2">
                    <x-filament::button wire:click="removeEditingPoint" color="danger" size="sm">
                        Usuń z programu
                    </x-filament::button>

                    <div class="flex gap-2">
                        <x-filament::button wire:click="closeModals" color="gray" size="sm">
                            Anuluj
                        </x-filament::button>
                        <x-filament::button wire:click="saveEditingPoint" color="primary" size="sm">
                            Zapisz zmiany
                        </x-filament::button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal dodawania nowego punktu --}}
    @if($showAddModal)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            wire:click.self="closeModals"
        >
            <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-2xl">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900">Dodaj punkt programu</h3>
                    <button wire:click="closeModals" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="space-y-4">
                    {{-- Wyszukiwanie w bibliotece --}}
                    <div class="relative">
                        <label class="mb-1 block text-xs font-medium text-gray-700">Szukaj w bibliotece punktów (opcjonalne)</label>
                        @if($newPointData['template_id'])
                            <div class="flex items-center justify-between rounded-lg border border-green-300 bg-green-50 px-3 py-2">
                                <span class="text-sm font-medium text-green-800">
                                    <svg class="mr-1 inline h-4 w-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    {{ $newPointData['name'] }}
                                </span>
                                <button wire:click="clearPlannerTemplate" class="ml-2 text-green-600 hover:text-red-500" title="Wyczyść wybór">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        @else
                            <input
                                type="text"
                                wire:model.live="plannerSearch"
                                placeholder="Wpisz nazwę aby wyszukać w bibliotece..."
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                            >
                            @if(count($templateResults) > 0)
                                <div class="absolute z-20 mt-1 max-h-52 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-xl">
                                    @foreach($templateResults as $result)
                                        <button
                                            wire:click="selectPlannerTemplate({{ $result['id'] }})"
                                            class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-primary-50"
                                        >
                                            <span class="font-medium text-gray-800">{{ $result['name'] }}</span>
                                            <span class="ml-2 shrink-0 text-xs text-gray-400">
                                                @if($result['unit_price']) {{ number_format($result['unit_price'], 2) }} PLN @endif
                                                @if($result['group_size']) · {{ $result['group_size'] }} os. @endif
                                            </span>
                                        </button>
                                    @endforeach
                                </div>
                            @elseif(strlen($plannerSearch) >= 2)
                                <div class="absolute z-20 mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-xl">
                                    <p class="text-xs text-gray-500">Brak wyników — wypełnij pola poniżej, aby stworzyć nowy punkt.</p>
                                </div>
                            @endif
                        @endif
                    </div>

                    {{-- Nazwa --}}
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700">Nazwa punktu <span class="text-red-500">*</span></label>
                        <input
                            type="text"
                            wire:model="newPointData.name"
                            placeholder="np. Zwiedzanie muzeum"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                            autofocus
                        >
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700">Opis punktu (dla tej imprezy)</label>
                        <textarea
                            wire:model="newPointData.description"
                            rows="3"
                            placeholder="Opis widoczny w programie imprezy..."
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                        ></textarea>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700">Punkt nadrzędny (opcjonalnie)</label>
                        <select
                            wire:model="newPointData.parent_id"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                        >
                            <option value="">Brak punktu nadrzędnego</option>
                            @foreach($parentPointOptions as $parentId => $parentLabel)
                                <option value="{{ $parentId }}">{{ $parentLabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Dzień + godziny --}}
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-700">Dzień</label>
                            <input
                                type="number"
                                wire:model="newPointData.day"
                                min="1"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                            >
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-700">Godz. start</label>
                            <input
                                type="time"
                                wire:model="newPointData.start_time"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                            >
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-700">Godz. koniec</label>
                            <input
                                type="time"
                                wire:model="newPointData.end_time"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                            >
                        </div>
                    </div>

                    {{-- Cena, ilość, wielkość grupy --}}
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-700">Cena jedn. (PLN)</label>
                            <input
                                type="number"
                                wire:model="newPointData.unit_price"
                                step="0.01"
                                min="0"
                                placeholder="0.00"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                            >
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-700">Ilość</label>
                            <input
                                type="number"
                                wire:model="newPointData.quantity"
                                min="1"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                            >
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-700">Wielkość grupy</label>
                            <input
                                type="number"
                                wire:model="newPointData.group_size"
                                min="1"
                                placeholder="—"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                            >
                        </div>
                    </div>

                    {{-- Uwzględnij w kalkulacji --}}
                    <div class="flex items-center gap-2">
                        <input
                            id="planner-include-in-calc"
                            type="checkbox"
                            wire:model="newPointData.include_in_calculation"
                            class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                        >
                        <label for="planner-include-in-calc" class="text-sm text-gray-700">Uwzględnij w kalkulacji</label>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <x-filament::button wire:click="closeModals" color="gray" size="sm">
                        Anuluj
                    </x-filament::button>
                    <x-filament::button wire:click="saveNewPoint" color="success" size="sm">
                        Dodaj do programu
                    </x-filament::button>
                </div>
            </div>
        </div>
    @endif
</div>

@assets
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
@endassets

@script
<script>
    (() => {
        const componentId = @js($this->getId());
        const plannerData = @js($plannerData);
        const plannerElementId = 'event-program-planner-{{ $this->getId() }}';
        const plannerScrollId = 'event-program-planner-scroll-{{ $this->getId() }}';

        window.__eventProgramPlannerRegistry = window.__eventProgramPlannerRegistry || {};
        window.__eventProgramPlannerListeners = window.__eventProgramPlannerListeners || {};

        const formatLocalDateTime = (date) => {
            const pad = (value) => String(value).padStart(2, '0');

            return [
                date.getFullYear(),
                '-',
                pad(date.getMonth() + 1),
                '-',
                pad(date.getDate()),
                'T',
                pad(date.getHours()),
                ':',
                pad(date.getMinutes()),
                ':',
                pad(date.getSeconds()),
            ].join('');
        };

        const syncEvent = (eventApi) => {
            const eventEnd = eventApi.end || new Date(eventApi.start.getTime() + (60 * 60 * 1000));
            const component = window.Livewire?.find(componentId);

            if (!component) {
                return;
            }

            component.call(
                'updatePointSchedule',
                Number(eventApi.id),
                formatLocalDateTime(eventApi.start),
                formatLocalDateTime(eventEnd),
            );
        };

        const ensurePlannerMinWidth = () => {
            const el = document.getElementById(plannerElementId);
            const scrollEl = document.getElementById(plannerScrollId);

            if (!el) {
                return;
            }

            const durationDays = Math.max(1, Number(plannerData.durationDays || 1));
            const viewportWidth = scrollEl ? scrollEl.clientWidth : 0;
            const minWidth = Math.max(1200, viewportWidth, durationDays * 360 + 160);

            el.style.minWidth = `${minWidth}px`;
            el.style.width = `${minWidth}px`;

            if (scrollEl) {
                scrollEl.style.overflowX = 'auto';
                scrollEl.style.maxWidth = '100%';
            }

            const scrollGrid = el.querySelector('.fc-scrollgrid');
            if (scrollGrid instanceof HTMLElement) {
                scrollGrid.style.minWidth = `${minWidth - 16}px`;
                scrollGrid.style.width = `${minWidth - 16}px`;
            }
        };

        window.__scrollEventProgramPlanner = window.__scrollEventProgramPlanner || ((id, direction) => {
            const target = document.getElementById(`event-program-planner-scroll-${id}`);
            if (!target) {
                return;
            }

            const step = Math.max(320, Math.floor(target.clientWidth * 0.7));
            target.scrollBy({
                left: direction === 'left' ? -step : step,
                behavior: 'smooth',
            });
        });

        const renderOrUpdate = () => {
            const el = document.getElementById(plannerElementId);
            if (!el || typeof window.FullCalendar === 'undefined') {
                return false;
            }

            ensurePlannerMinWidth();

            const existing = window.__eventProgramPlannerRegistry[componentId];
            if (existing) {
                existing.removeAllEvents();
                existing.addEventSource(plannerData.events || []);
                existing.render();
                existing.updateSize();
            } else {
                const calendar = new window.FullCalendar.Calendar(el, {
                    initialDate: plannerData.initialDate,
                    initialView: 'programTimeGrid',
                    locale: 'pl',
                    firstDay: 1,
                    allDaySlot: false,
                    editable: true,
                    eventStartEditable: true,
                    eventDurationEditable: true,
                    eventResizableFromStart: true,
                    nowIndicator: true,
                    selectable: true,
                    slotMinTime: '06:00:00',
                    slotMaxTime: '23:00:00',
                    slotDuration: '00:30:00',
                    snapDuration: '00:15:00',
                    height: 'auto',
                    validRange: {
                        start: plannerData.initialDate,
                        end: plannerData.maxDate,
                    },
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'programTimeGrid,timeGridDay',
                    },
                    views: {
                        programTimeGrid: {
                            type: 'timeGrid',
                            duration: { days: plannerData.durationDays || 1 },
                            buttonText: 'Plan imprezy',
                        },
                    },
                    events: plannerData.events || [],
                    eventDrop: (info) => syncEvent(info.event),
                    eventResize: (info) => syncEvent(info.event),
                    eventClick: (info) => {
                        const component = window.Livewire?.find(componentId);
                        if (!component) {
                            return;
                        }

                        component.call('openEditModal', Number(info.event.id));
                    },
                    select: (info) => {
                        const component = window.Livewire?.find(componentId);
                        if (!component) {
                            return;
                        }

                        component.call(
                            'openAddModal',
                            formatLocalDateTime(info.start),
                            formatLocalDateTime(info.end),
                        );
                        calendar.unselect();
                    },
                });

                calendar.render();
                calendar.updateSize();
                window.__eventProgramPlannerRegistry[componentId] = calendar;
            }

            if (!window.__eventProgramPlannerListeners[componentId] && window.Livewire) {
                window.__eventProgramPlannerListeners[componentId] = true;
                window.Livewire.on('planner-data-updated-' + componentId, (payload) => {
                    const data = Array.isArray(payload) ? (payload[0] || {}) : (payload || {});
                    const events = data.events || [];
                    const instance = window.__eventProgramPlannerRegistry[componentId];

                    if (!instance) {
                        return;
                    }

                    instance.removeAllEvents();
                    instance.addEventSource(events);
                });
            }

            return true;
        };

        window.__eventProgramPlannerResizeBound = window.__eventProgramPlannerResizeBound || {};
        if (!window.__eventProgramPlannerResizeBound[componentId]) {
            window.__eventProgramPlannerResizeBound[componentId] = true;
            window.addEventListener('resize', () => {
                ensurePlannerMinWidth();
                const instance = window.__eventProgramPlannerRegistry[componentId];
                if (instance) {
                    instance.updateSize();
                }
            });
        }

        if (!renderOrUpdate()) {
            let attempts = 0;
            const interval = setInterval(() => {
                attempts++;
                if (renderOrUpdate() || attempts > 60) {
                    clearInterval(interval);
                }
            }, 100);
        }
    })();
</script>
@endscript
