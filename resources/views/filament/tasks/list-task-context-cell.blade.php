@php
    /** @var \App\Models\Task|null $record */
    $record = $getRecord();
@endphp

@if ($record)
    @php
        $contextType = $record->taskable_type_label ?: 'Wolne / nieprzypisane';
        $contextRecord = $record->taskable_label;
    @endphp

    <table class="border-collapse min-w-[160px]">
        <tr>
            <td class="pr-2 py-px text-[0.72rem] text-gray-400 align-top whitespace-nowrap">Nadrzędne:</td>
            <td class="text-[0.78rem] leading-snug text-gray-900 dark:text-gray-100">
                @if ($record->parent)
                    <button
                        type="button"
                        class="text-left text-primary-600 hover:underline dark:text-primary-400"
                        x-on:click.stop="$wire.openEditTaskModal({{ $record->parent_id }})"
                    >
                        {{ $record->parent->title }}
                    </button>
                @else
                    —
                @endif
            </td>
        </tr>
        <tr>
            <td class="pr-2 py-px text-[0.72rem] text-gray-400 align-top whitespace-nowrap">Kontekst:</td>
            <td class="text-[0.78rem] leading-snug text-gray-900 dark:text-gray-100">
                {{ $contextType }} /
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
            </td>
        </tr>
    </table>
@endif
