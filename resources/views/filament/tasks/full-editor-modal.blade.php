@php
    $editorKey = 'task-full-editor-'.($taskId ?? 'new').'-'.($defaultDueDate ?? 'none').'-'.($activeRelationManager ?? 'none');
@endphp

{{-- Bez overflow-y-auto: ucinało panele TipTapa (kolor / nagłówki). Scroll zapewnia kontener modala Filament. --}}
<div class="pe-1 task-full-editor-shell" wire:key="task-editor-shell">
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
