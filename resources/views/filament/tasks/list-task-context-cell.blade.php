@php
    /** @var \App\Models\Task|null $record */
    $record = $getRecord();
@endphp

@if ($record)
    @php
        $contextType = $record->taskable_type_label ?: 'Wolne / nieprzypisane';
        $contextRecord = $record->taskable_label;
        $showContextRecord = $showContextRecord ?? true;
    @endphp

    <div class="task-list-context-cell space-y-1 text-[0.78rem] leading-snug">
        @if ($showContextRecord)
            <div class="text-[0.7rem] text-gray-400">{{ $contextType }}</div>
            <div class="min-w-0 break-words text-gray-900 dark:text-gray-100">
                @if ($record->taskable_type && $record->taskable_id)
                    <button
                        type="button"
                        class="text-left text-primary-600 hover:underline dark:text-primary-400"
                        x-on:click.stop="$wire.openEditTaskModal({{ $record->id }})"
                    >
                        {{ $contextRecord }}
                    </button>
                @else
                    {{ $contextRecord }}
                @endif
            </div>
        @elseif ($record->parent)
            <div class="text-[0.7rem] text-gray-400">Nadrzędne</div>
            <button
                type="button"
                class="min-w-0 break-words text-left text-primary-600 hover:underline dark:text-primary-400"
                x-on:click.stop="$wire.openEditTaskModal({{ $record->parent_id }})"
            >
                {{ $record->parent->title }}
            </button>
        @else
            <span class="text-gray-400">—</span>
        @endif
    </div>
@endif
