<x-filament-panels::page>
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

    {{ $this->table }}
</x-filament-panels::page>
