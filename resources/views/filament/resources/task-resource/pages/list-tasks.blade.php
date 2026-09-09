<x-filament-panels::page>
    @include('filament.tasks.partials.tasks-split-scroll-script')

    <div class="mb-3 rounded-xl border border-gray-200 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-900">
        @include('filament.tasks.ownership-quick-filters', [
            'tasksScope' => $this->tasksScope,
            'dueFilter' => $this->dueFilter,
            'tasksOnlyUrgent' => $this->tasksOnlyUrgent,
            'showFinishedTasks' => $this->showFinishedTasks,
            'sourceFilter' => $this->sourceFilter,
            'showSource' => true,
            'showFinishedToggle' => false,
        ])
    </div>

    <div
        @class([
            'tasks-split-view',
            'tasks-split-view--has-selection' => filled($this->selectedTaskId),
        ])
        x-data
        x-init="
            const go = () => window.scrollTasksSplitEditorIntoView?.();
            if ($wire.selectedTaskId) {
                setTimeout(go, 100);
                setTimeout(go, 300);
            }
            $wire.$watch('selectedTaskId', (value) => {
                if (! value) return;
                queueMicrotask(go);
                setTimeout(go, 100);
                setTimeout(go, 300);
            });
        "
    >
        <div class="tasks-split-view__shell">
            <div class="tasks-split-view__list min-h-0 overflow-auto">
                @if ($this->activeTab !== 'manual')
                    <div class="tasks-split-view__list-toolbar">
                        <label class="tasks-split-view__sort">
                            <span class="tasks-split-view__sort-label">Sortuj</span>
                            <select wire:model.live="listSort" class="tasks-split-view__sort-select task-split-select">
                                @foreach ($this->listSortOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                @else
                    <div class="tasks-split-view__list-toolbar">
                        <span class="tasks-split-view__sort-label">Kolejność ręczna — przeciągnij wiersze</span>
                    </div>
                @endif

                {{ $this->table }}
            </div>

            <div class="tasks-split-view__detail" id="tasks-split-detail-panel">
                @include('filament.tasks.partials.task-split-detail')
            </div>
        </div>
    </div>
</x-filament-panels::page>
