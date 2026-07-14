<x-filament-panels::page>
    <div class="mb-4">
        @include('filament.tasks.ownership-quick-filters', ['tasksScope' => $this->tasksScope])
    </div>

    {{ $this->table }}
</x-filament-panels::page>
