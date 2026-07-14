@php
    $editorKey = 'task-full-editor-'.($taskId ?? 'new').'-'.($defaultDueDate ?? 'none').'-'.($activeRelationManager ?? 'none');
@endphp

<div class="max-h-[75vh] overflow-y-auto pe-1" wire:key="task-editor-shell">
    @livewire(
        \App\Livewire\TaskFullEditor::class,
        [
            'taskId' => $taskId,
            'defaultDueDate' => $defaultDueDate ?? null,
            'activeRelationManager' => $activeRelationManager ?? null,
            'defaultFormData' => $defaultFormData ?? [],
        ],
        key($editorKey)
    )
</div>
