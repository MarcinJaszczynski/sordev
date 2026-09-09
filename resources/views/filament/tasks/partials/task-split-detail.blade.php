@php
    $taskId = $this->selectedTaskId;
    $canDelete = false;
    if ($taskId) {
        $task = $this->selectedTaskRecord();
        $canDelete = $task && \App\Support\Tasks\TaskAuthorization::canDelete(auth()->user(), $task);
    }
@endphp

<div class="task-split-detail">
    @if (! $taskId)
        <div class="task-split-detail__empty">
            Wybierz zadanie z listy, aby edytować je tutaj — z opisem, dyskusją i załącznikami.
        </div>
    @else
        <div class="task-split-detail__toolbar">
            <button
                type="button"
                wire:click="clearSelectedTask"
                class="task-split-detail__toolbar-back"
            >
                <x-heroicon-o-arrow-left class="h-4 w-4" />
                <span class="lg:hidden">Lista</span>
                <span class="hidden lg:inline">Zamknij</span>
            </button>

            <div class="task-split-detail__toolbar-actions">
                <button
                    type="button"
                    wire:click="openAddAttachmentModal({{ $taskId }})"
                    class="task-split-detail__toolbar-btn"
                >
                    Załącznik
                </button>
                @if ($canDelete)
                    <button
                        type="button"
                        class="task-split-detail__toolbar-btn is-danger"
                        wire:click="deleteSelectedTask"
                        wire:confirm="Usunąć to zadanie?"
                    >
                        Usuń
                    </button>
                @endif
            </div>
        </div>

        <div class="task-split-detail__editor">
            @livewire(
                \App\Livewire\TaskFullEditor::class,
                [
                    'taskId' => $taskId,
                    'activeRelationManager' => $this->selectedTaskActiveRelationManager,
                ],
                key('split-task-editor-'.$taskId.'-'.($this->selectedTaskActiveRelationManager ?? 'none')),
            )
        </div>
    @endif
</div>
