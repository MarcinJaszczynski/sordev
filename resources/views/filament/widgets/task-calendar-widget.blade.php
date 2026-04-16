<x-filament-widgets::widget>
    <x-filament::section>
        <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end">
            <div class="w-full lg:max-w-sm">
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Szukaj</label>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Tytuł lub opis zadania"
                    class="fi-input block w-full rounded-lg border px-3 py-2 text-sm"
                />
            </div>

            <div class="w-full lg:max-w-xs">
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Status</label>
                <select wire:model.live="statusId" class="fi-select-input block w-full rounded-lg border px-3 py-2 text-sm">
                    <option value="">Wszystkie statusy</option>
                    @foreach($statusOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="w-full lg:max-w-xs">
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Przypisany</label>
                <select wire:model.live="assigneeId" class="fi-select-input block w-full rounded-lg border px-3 py-2 text-sm">
                    <option value="">Wszyscy</option>
                    @foreach($assigneeOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="w-full lg:max-w-[11rem]">
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Od</label>
                <input type="date" wire:model.live="dateFrom" class="fi-input block w-full rounded-lg border px-3 py-2 text-sm" />
            </div>

            <div class="w-full lg:max-w-[11rem]">
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Do</label>
                <input type="date" wire:model.live="dateTo" class="fi-input block w-full rounded-lg border px-3 py-2 text-sm" />
            </div>
        </div>

        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                <input type="checkbox" wire:model.live="onlyMine" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                Tylko moje zadania
            </label>

            <div class="flex items-center gap-2">
                <span class="rounded-md bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                    Zdarzenia: {{ $eventsCount }}
                </span>
                <x-filament::button size="sm" color="gray" wire:click="resetFilters">Reset filtrów</x-filament::button>
            </div>
        </div>

        @php($eventsHash = md5(json_encode($calendarEvents)))
        <div
            wire:key="dashboard-task-calendar-{{ $eventsHash }}"
            x-data="dashboardTaskCalendar({ events: @js($calendarEvents) })"
            x-init="init()"
            class="rounded-lg border border-gray-200 bg-gray-50 p-2 dark:border-gray-700 dark:bg-gray-900/50"
        >
            <div x-ref="calendar" style="min-height: 520px;"></div>
        </div>
    </x-filament::section>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
    <script>
        function dashboardTaskCalendar(config) {
            return {
                calendar: null,
                events: config?.events ?? [],
                attempts: 0,
                init() {
                    this.initializeWhenReady();
                },
                initializeWhenReady() {
                    const element = this.$refs.calendar;

                    if (!element) {
                        return;
                    }

                    if (typeof window.FullCalendar === 'undefined') {
                        if (this.attempts < 30) {
                            this.attempts += 1;
                            setTimeout(() => this.initializeWhenReady(), 150);
                        }

                        return;
                    }

                    this.mount(element);
                },
                mount(element) {
                    if (!element || typeof window.FullCalendar === 'undefined') {
                        return;
                    }

                    if (this.calendar) {
                        this.calendar.destroy();
                    }

                    this.calendar = new window.FullCalendar.Calendar(element, {
                        initialView: 'dayGridMonth',
                        locale: 'pl',
                        firstDay: 1,
                        headerToolbar: {
                            left: 'prev,next today',
                            center: 'title',
                            right: 'dayGridMonth,timeGridWeek,timeGridDay',
                        },
                        buttonText: {
                            today: 'Dzisiaj',
                            month: 'Miesiąc',
                            week: 'Tydzień',
                            day: 'Dzień',
                        },
                        events: this.events,
                        height: 'auto',
                        navLinks: true,
                        eventTimeFormat: {
                            hour: '2-digit',
                            minute: '2-digit',
                            hour12: false,
                        },
                        eventClick: function(info) {
                            if (info.event.url) {
                                info.jsEvent.preventDefault();
                                window.location.href = info.event.url;
                            }
                        },
                    });

                    this.calendar.render();
                },
            };
        }
    </script>
</x-filament-widgets::widget>
