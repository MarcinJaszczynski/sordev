<x-filament-panels::page>

    <div class="mb-4 flex flex-wrap items-center gap-2">

        <div class="mr-2 flex flex-wrap items-center gap-2 rounded-xl border border-violet-200 bg-violet-50/60 px-3 py-2 dark:border-violet-800 dark:bg-violet-950/30">
            <span class="text-xs font-semibold uppercase tracking-wide text-violet-900 dark:text-violet-200">Filtry zadań</span>

            @include('filament.tasks.ownership-quick-filters', ['tasksScope' => $this->tasksScope])

            <label class="flex items-center gap-2 rounded-full border border-violet-300 bg-white px-3 py-1 text-sm dark:border-violet-700 dark:bg-gray-900">
                <input type="checkbox" wire:model.live="tasksOnlyUrgent" class="rounded border-gray-400" />
                <span class="text-violet-800 dark:text-violet-200">Tylko pilne</span>
            </label>

            <label class="flex items-center gap-2 rounded-full border border-violet-300 bg-white px-3 py-1 text-sm dark:border-violet-700 dark:bg-gray-900">
                <input type="checkbox" wire:model.live="showFinishedTasks" class="rounded border-gray-400" />
                <span class="text-violet-800 dark:text-violet-200">Pokaż zakończone i anulowane</span>
            </label>

            @if($this->hasActiveTaskFilters())
                <button
                    type="button"
                    wire:click="resetTaskFilters"
                    class="rounded-full border border-violet-400 bg-white px-3 py-1 text-xs font-semibold text-violet-800 hover:bg-violet-100 dark:border-violet-600 dark:bg-gray-900 dark:text-violet-200 dark:hover:bg-violet-900/40"
                >
                    Pokaż wszystkie zadania
                </button>
            @endif
        </div>

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

            <button

                type="button"

                wire:click="toggleType('{{ $type }}')"

                class="rounded-full px-3 py-1 text-sm font-medium border"

                style="border-color: {{ $meta['color'] }}; {{ in_array($type, $enabledTypes, true) ? 'background:'.$meta['color'].';color:#fff;' : 'background:#fff;color:'.$meta['color'].';' }}"

            >

                {{ $meta['label'] }}

            </button>

        @endforeach

    </div>



    <div
        id="operations-calendar-events"
        class="hidden"
        data-calendar-events='@json($this->calendarEvents)'
    ></div>

    <div

        id="operations-calendar"

        wire:ignore

        class="min-h-[32rem] rounded-xl border border-gray-200 bg-white p-2 dark:border-gray-700 dark:bg-gray-900"

    ></div>



    @include('filament.components.fullcalendar-boot', [
        'elementId' => 'operations-calendar',
        'livewireId' => $this->getId(),
    ])

    <style>
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
        .fc-event.task-has-preview .fc-event-title {
            font-weight: 700;
            line-height: 1.35;
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

