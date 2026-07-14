<?php

namespace App\Filament\Concerns;

use App\Models\Task;
use Filament\Actions\Action;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;

trait InteractsWithTaskEditModal
{
    public ?int $editingTaskId = null;

    public ?int $editingTaskActiveRelationManager = null;

    public function bootInteractsWithTaskEditModal(): void
    {
        $this->cacheAction($this->makeEditTaskAction());
    }

    public function mountInteractsWithTaskEditModal(): void
    {
        $this->openDeepLinkedTaskIfPresent();
    }

    public function openEditTaskModal(int $taskId, ?int $activeRelationManager = null): void
    {
        $this->editingTaskId = $taskId;
        $this->editingTaskActiveRelationManager = $activeRelationManager;
        $this->mountAction('editTask');
    }

    #[On('task-full-editor-saved')]
    public function handleTaskFullEditorSaved(int $taskId): void
    {
        $this->editingTaskId = null;
        $this->editingTaskActiveRelationManager = null;

        $task = Task::query()->find($taskId);

        if ($task) {
            $this->afterTaskModalSaved($task);
        }

        $this->unmountAction('editTask');
        $this->unmountAction('createTask');
    }

    protected function openDeepLinkedTaskIfPresent(): void
    {
        $taskId = (int) request()->query('editTask', 0);

        if ($taskId <= 0) {
            return;
        }

        $activeRelationManager = request()->has('activeRelationManager')
            ? (int) request()->query('activeRelationManager')
            : null;

        $this->openEditTaskModal($taskId, $activeRelationManager);
    }

    protected function afterTaskModalSaved(Task $task): void
    {
        //
    }

    protected function makeCreateTaskAction(?callable $defaultDueDate = null, ?callable $defaultFormData = null): Action
    {
        return Action::make('createTask')
            ->label('Dodaj zadanie')
            ->icon('heroicon-m-plus')
            ->modalHeading('Nowe zadanie')
            ->modalWidth('7xl')
            ->modalContent(function () use ($defaultDueDate, $defaultFormData): View {
                $dueDate = $defaultDueDate ? $defaultDueDate() : null;
                $formDefaults = $defaultFormData ? $defaultFormData() : [];

                return view('filament.tasks.full-editor-modal', [
                    'taskId' => null,
                    'defaultDueDate' => $dueDate?->format('Y-m-d H:i:s'),
                    'defaultFormData' => $formDefaults,
                    'activeRelationManager' => null,
                ]);
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Zamknij');
    }

    protected function makeEditTaskAction(): Action
    {
        return Action::make('editTask')
            ->label('Edytuj zadanie')
            ->modalHeading(fn (): string => Task::query()->find($this->editingTaskId)?->title ?? 'Edytuj zadanie')
            ->modalWidth('7xl')
            ->modalContent(fn (): View => view('filament.tasks.full-editor-modal', [
                'taskId' => $this->editingTaskId,
                'defaultDueDate' => null,
                'defaultFormData' => [],
                'activeRelationManager' => $this->editingTaskActiveRelationManager,
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Zamknij')
            ->action(function (): void {
                $this->editingTaskId = null;
                $this->editingTaskActiveRelationManager = null;
            });
    }
}
