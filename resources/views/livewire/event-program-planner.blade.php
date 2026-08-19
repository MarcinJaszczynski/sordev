<div class="space-y-3">
    <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
        Planer służy do <b>ustawiania godzin</b>: przeciągaj i rozciągaj bloki albo <b>kliknij blok</b>, aby wpisać godziny ręcznie.
        Pełną edycję punktów (nazwa, opis, notatki, cena) robisz w zakładce <b>Lista</b> lub <b>Dzień</b>.
        Pokazuje wyłącznie punkty <b>uwzględnione w programie</b>.
    </div>

    <div class="flex items-center gap-2">
        <x-filament::button wire:click="repairOrderNow" color="gray" size="xs">
            Napraw kolejność teraz
        </x-filament::button>
        <span class="text-xs text-gray-500">Przeciągnij lub rozciągnij blok, albo kliknij, aby wpisać godziny.</span>
    </div>

    @if(empty($plannerData['events']))
        <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-6 text-center text-sm text-gray-600">
            <p class="font-medium text-gray-800">Planer jest pusty</p>
            <p class="mt-1">Brak punktów oznaczonych jako „w programie”. Oznacz je w widoku <strong>Lista</strong> lub <strong>Dzień</strong>.</p>
        </div>
    @endif

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
            class="event-program-planner-surface min-h-[960px] rounded-lg border border-gray-200 bg-white p-2"
        ></div>
    </div>

    @if($showEditModal)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            wire:click.self="closeModals"
        >
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-2xl">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900">Godziny punktu</h3>
                    <button wire:click="closeModals" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <p class="mb-4 text-sm text-gray-700">
                    <span class="font-semibold">{{ $editingData['name'] }}</span>
                </p>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label for="planner-edit-start-time" class="mb-1 block text-xs font-medium text-gray-700">Godz. start</label>
                        <input
                            id="planner-edit-start-time"
                            type="time"
                            step="60"
                            wire:model="editingData.start_time"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                        >
                    </div>
                    <div>
                        <label for="planner-edit-end-time" class="mb-1 block text-xs font-medium text-gray-700">Godz. koniec</label>
                        <input
                            id="planner-edit-end-time"
                            type="time"
                            step="60"
                            wire:model="editingData.end_time"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                        >
                    </div>
                </div>

                <div class="mt-3 flex items-center gap-2">
                    <input
                        id="planner-edit-hide-times"
                        type="checkbox"
                        wire:model="editingData.hide_times"
                        class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                    >
                    <label for="planner-edit-hide-times" class="text-sm text-gray-700">Ukryj godziny w programie</label>
                </div>
                @error('editingData.end_time')
                    <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                @enderror

                <div class="mt-6 flex items-center justify-between gap-2">
                    <x-filament::button wire:click="requestRemoveEditingPoint" color="danger" size="sm">
                        Usuń z programu
                    </x-filament::button>

                    <div class="flex gap-2">
                        <x-filament::button wire:click="closeModals" color="gray" size="sm">
                            Anuluj
                        </x-filament::button>
                        <x-filament::button wire:click="saveEditingPoint" color="primary" size="sm">
                            Zapisz godziny
                        </x-filament::button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($showDeleteModal)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            wire:click.self="closeModals"
        >
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-2xl">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900">Usuń z programu</h3>
                    <button wire:click="closeModals" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <p class="text-sm text-gray-700">
                    Czy na pewno chcesz usunąć z programu punkt
                    <span class="font-semibold">{{ $deletingPointName }}</span>?
                    Punkt pozostanie na liście kosztów / poza programem — pełną edycję zrobisz w zakładce Lista lub Dzień.
                </p>

                <div class="mt-6 flex justify-end gap-2">
                    <x-filament::button wire:click="closeModals" color="gray" size="sm">
                        Anuluj
                    </x-filament::button>
                    <x-filament::button wire:click="confirmRemovePoint" color="danger" size="sm">
                        Usuń z programu
                    </x-filament::button>
                </div>
            </div>
        </div>
    @endif
</div>

@assets
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css">
    <style>
        .event-program-planner-surface .fc-event.event-program-child {
            border-left: 2px solid rgba(255, 255, 255, 0.35) !important;
            box-shadow: none;
        }
    </style>
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

        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        const badgeClassMap = {
            red: 'bg-red-100 text-red-700 border-red-300',
            green: 'bg-emerald-100 text-emerald-700 border-emerald-300',
            amber: 'bg-amber-100 text-amber-800 border-amber-300',
            orange: 'bg-orange-100 text-orange-700 border-orange-300',
            gray: 'bg-gray-100 text-gray-700 border-gray-300',
            blue: 'bg-blue-100 text-blue-700 border-blue-300',
            indigo: 'bg-indigo-100 text-indigo-700 border-indigo-300',
            slate: 'bg-slate-100 text-slate-700 border-slate-300',
        };

        const renderStatusBadge = (badge, fallbackCode, fallbackTooltip) => {
            const color = badge?.color || 'gray';
            const classes = badgeClassMap[color] || badgeClassMap.gray;
            const code = escapeHtml(badge?.code || fallbackCode);
            const tooltip = escapeHtml(badge?.tooltip || fallbackTooltip);

            return `<span class="event-program-badge inline-flex h-5 min-w-5 items-center justify-center rounded-full border px-1 text-[10px] font-bold leading-none ${classes}" title="${tooltip}">${code}</span>`;
        };

        const buildEventContent = (event) => {
            const isChild = Boolean(event.extendedProps?.isChild);
            const title = escapeHtml(event.title || 'Punkt programu');
            const notesPreview = escapeHtml(event.extendedProps?.notesPreview || '');
            const payment = renderStatusBadge(event.extendedProps?.payment, '$', 'Platnosc: brak danych.');
            const invoice = renderStatusBadge(event.extendedProps?.invoice, 'F', 'Faktura: brak dokumentu.');
            const payer = renderStatusBadge(event.extendedProps?.payer, 'B', 'Kto placi: brak danych.');
            const reservation = renderStatusBadge(event.extendedProps?.reservation, 'R', 'Rezerwacja: brak danych.');
            const titleClass = isChild
                ? 'truncate text-[12px] font-semibold leading-5 text-white/95 pl-1'
                : 'truncate text-[14px] font-bold leading-5 text-white';

            return `<div class="event-program-card flex w-full items-start justify-between gap-2 overflow-hidden ${isChild ? 'event-program-card-child' : ''}">
                <div class="min-w-0 flex-1">
                    <div class="${titleClass}">${title}</div>
                    ${notesPreview ? `<div class="mt-0.5 max-h-10 overflow-hidden text-[12px] font-semibold leading-5 text-white/95" title="${notesPreview}">${notesPreview}</div>` : ''}
                </div>
                <div class="shrink-0 flex flex-row items-center gap-1">${payment}${invoice}${payer}${reservation}</div>
            </div>`;
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

        const calendarOptions = () => ({
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
            selectable: false,
            selectMirror: false,
            slotMinTime: '00:00:00',
            slotMaxTime: '24:00:00',
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
            eventContent: (arg) => {
                const wrapper = document.createElement('div');
                wrapper.innerHTML = buildEventContent(arg.event);

                return { domNodes: [wrapper.firstElementChild || wrapper] };
            },
            eventDrop: (info) => syncEvent(info.event),
            eventResize: (info) => syncEvent(info.event),
            eventClick: (info) => {
                const component = window.Livewire?.find(componentId);
                if (!component) {
                    return;
                }

                component.call('openEditModal', Number(info.event.id));
            },
        });

        const createCalendar = (el) => {
            if (typeof window.SorFullCalendar !== 'undefined' && window.SorFullCalendarPlugins?.timeGrid) {
                return new window.SorFullCalendar(el, {
                    plugins: [
                        window.SorFullCalendarPlugins.timeGrid,
                        window.SorFullCalendarPlugins.interaction,
                    ],
                    locale: window.SorFullCalendarLocalePl,
                    ...calendarOptions(),
                });
            }

            if (typeof window.FullCalendar !== 'undefined') {
                return new window.FullCalendar.Calendar(el, calendarOptions());
            }

            return null;
        };

        const renderOrUpdate = () => {
            const el = document.getElementById(plannerElementId);
            if (!el) {
                return false;
            }

            if (typeof window.SorFullCalendar === 'undefined' && typeof window.FullCalendar === 'undefined') {
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
                const calendar = createCalendar(el);
                if (!calendar) {
                    return false;
                }

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

        const ensureFullCalendarAndRender = () => {
            if (renderOrUpdate()) {
                return;
            }

            if (!window.__eventProgramPlannerCdnRequested) {
                window.__eventProgramPlannerCdnRequested = true;
                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js';
                script.onload = () => renderOrUpdate();
                document.head.appendChild(script);
            }
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

        const bootPlanner = () => {
            ensureFullCalendarAndRender();

            let attempts = 0;
            const interval = setInterval(() => {
                attempts++;
                if (renderOrUpdate() || attempts > 80) {
                    clearInterval(interval);
                }
            }, 100);
        };

        bootPlanner();

        window.addEventListener('event-program-planner-init', () => {
            setTimeout(() => {
                ensureFullCalendarAndRender();
                renderOrUpdate();
                const instance = window.__eventProgramPlannerRegistry[componentId];
                if (instance) {
                    instance.updateSize();
                }
            }, 120);
        });
    })();
</script>
@endscript
