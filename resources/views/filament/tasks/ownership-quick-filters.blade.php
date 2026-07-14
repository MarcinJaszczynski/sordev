@php
    $activeScope = $tasksScope ?? 'assigned';
@endphp

<div class="flex flex-wrap items-center gap-2">
    <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Zakres</span>

    @foreach([
        'assigned' => 'Moje zadania',
        'authored' => 'Moje zlecenia',
        'all' => 'Wszystkie',
    ] as $scope => $label)
        <button
            type="button"
            wire:click="setTasksScope('{{ $scope }}')"
            class="rounded-full border px-3 py-1 text-sm font-medium transition {{ $activeScope === $scope
                ? 'border-primary-600 bg-primary-600 text-white'
                : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800' }}"
        >
            {{ $label }}
        </button>
    @endforeach
</div>
