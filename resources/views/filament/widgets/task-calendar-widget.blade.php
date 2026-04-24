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
                <span class="text-xs text-gray-500 dark:text-gray-400">Kliknij dzień, aby dodać zadanie</span>
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

        <x-filament::modal id="dashboard-quick-task-modal" width="2xl">
            <x-slot name="header">
                <x-filament::modal.heading>
                    Dodaj zadanie z kalendarza
                </x-filament::modal.heading>
            </x-slot>

            <div class="space-y-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Tytuł zadania *</label>
                    <input
                        wire:model="quickTaskTitle"
                        type="text"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                        placeholder="Np. Wizyta u dentysty"
                    />
                    @error('quickTaskTitle') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Opis</label>
                    <textarea
                        wire:model="quickTaskDescription"
                        rows="3"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                        placeholder="Opcjonalny opis"
                    ></textarea>
                    @error('quickTaskDescription') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Termin</label>
                        <input
                            wire:model="quickTaskDueDate"
                            type="datetime-local"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                        />
                        @error('quickTaskDueDate') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Priorytet</label>
                        <select
                            wire:model="quickTaskPriority"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                        >
                            <option value="low">Niski</option>
                            <option value="medium">Średni</option>
                            <option value="high">Wysoki</option>
                        </select>
                        @error('quickTaskPriority') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Przypisz do</label>
                    <select
                        wire:model="quickTaskAssigneeId"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                    >
                        <option value="">Nie przypisano</option>
                        @foreach($assigneeOptions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('quickTaskAssigneeId') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                </div>
            </div>

            <x-slot name="footer">
                <div class="flex justify-end gap-2">
                    <x-filament::button color="gray" x-on:click="isOpen = false">Anuluj</x-filament::button>
                    <x-filament::button color="primary" wire:click="createQuickTask">Utwórz zadanie</x-filament::button>
                </div>
            </x-slot>
        </x-filament::modal>
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
                        dateClick: (info) => {
                            this.$wire.openQuickAddModal(info.dateStr);
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
