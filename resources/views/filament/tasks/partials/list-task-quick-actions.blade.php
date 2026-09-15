@php
    /** @var \App\Models\Task $record */
@endphp

<div
    class="task-split-row__actions"
    wire:click.stop
    x-on:click.stop
    x-on:mousedown.stop
>
    <button
        type="button"
        title="Dodaj komentarz"
        class="task-split-row__action task-split-row__action--comment"
        x-on:click.stop="$wire.openAddCommentModal({{ $record->id }})"
    >
        <x-heroicon-o-paper-airplane class="h-3.5 w-3.5" />
        Komentarz
    </button>
    <button
        type="button"
        title="Dodaj załącznik"
        class="task-split-row__action task-split-row__action--attach"
        x-on:click.stop="$wire.openAddAttachmentModal({{ $record->id }})"
    >
        <x-heroicon-o-paper-clip class="h-3.5 w-3.5" />
        Załącznik
    </button>
</div>
