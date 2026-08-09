<?php

namespace App\Filament\Concerns;

use App\Models\Task;
use App\Services\NotificationService;
use Filament\Actions\Action;
use Filament\Tables;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;

trait InteractsWithTaskEditModal
{
    use DispatchesTopbarNotificationRefresh;

    public ?int $editingTaskId = null;

    public ?int $editingTaskActiveRelationManager = null;

    /** @var array<string, mixed> */
    public array $pendingCreateFormData = [];

    public ?string $pendingCreateDueDate = null;

    public function bootInteractsWithTaskEditModal(): void
    {
        $this->cacheAction($this->makeEditTaskAction());
        $this->cacheAction($this->makeCreateTaskAction(
            defaultDueDate: fn (): mixed => $this->createTaskDefaultDueDate(),
            defaultFormData: fn (): array => array_merge(
                $this->createTaskDefaultFormData(),
                $this->pendingCreateFormData,
            ),
        ));
    }

    public function mountInteractsWithTaskEditModal(): void
    {
        $this->openDeepLinkedTaskIfPresent();
        $this->openDeepLinkedCreateTaskIfPresent();
    }

    public function openEditTaskModal(int $taskId, ?int $activeRelationManager = null): void
    {
        $this->editingTaskId = $taskId;
        $this->editingTaskActiveRelationManager = $activeRelationManager;
        $this->mountAction('editTask');
    }

    public function openCreateTaskModal(array $defaultFormData = [], ?string $defaultDueDate = null): void
    {
        $this->pendingCreateFormData = $defaultFormData;
        $this->pendingCreateDueDate = $defaultDueDate;
        $this->mountAction('createTask');
    }

    #[On('task-full-editor-saved')]
    #[On('task-full-editor-updated')]
    public function handleTaskFullEditorSaved(int $taskId): void
    {
        $task = Task::query()->find($taskId);

        if ($task) {
            $this->afterTaskModalSaved($task);
        }
    }

    #[On('comment-added')]
    public function handleTaskCommentAddedForTopbar(): void
    {
        $this->dispatchTopbarNotificationRefresh();
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

    protected function openDeepLinkedCreateTaskIfPresent(): void
    {
        if (! request()->boolean('createTask')) {
            return;
        }

        $this->pendingCreateFormData = array_filter([
            'taskable_type' => request()->query('taskable_type'),
            'taskable_id' => request()->has('taskable_id') ? (int) request()->query('taskable_id') : null,
        ], fn ($value): bool => $value !== null && $value !== '');

        $this->pendingCreateDueDate = request()->query('dueDate');

        $this->mountAction('createTask');
    }

    /**
     * @return array<string, mixed>
     */
    protected function createTaskDefaultFormData(): array
    {
        return [];
    }

    protected function createTaskDefaultDueDate(): mixed
    {
        return $this->pendingCreateDueDate;
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
            ->modalContent(fn (): View => $this->createTaskModalView($defaultDueDate, $defaultFormData))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Zamknij')
            ->after(function (): void {
                $this->pendingCreateFormData = [];
                $this->pendingCreateDueDate = null;
            });
    }

    protected function makeCreateTaskTableAction(?callable $defaultDueDate = null, ?callable $defaultFormData = null): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('createTask')
            ->label('Dodaj zadanie')
            ->icon('heroicon-m-plus')
            ->action(fn () => $this->mountAction('createTask'));
    }

    /**
     * @param  callable(): mixed|null  $defaultDueDate
     * @param  callable(): array<string, mixed>|null  $defaultFormData
     */
    protected function createTaskModalView(?callable $defaultDueDate = null, ?callable $defaultFormData = null): View
    {
        $dueDate = $defaultDueDate ? $defaultDueDate() : null;
        $formDefaults = $defaultFormData ? $defaultFormData() : [];

        return view('filament.tasks.full-editor-modal', [
            'taskId' => null,
            'defaultDueDate' => is_string($dueDate) ? $dueDate : $dueDate?->format('Y-m-d H:i:s'),
            'defaultFormData' => $formDefaults,
            'activeRelationManager' => null,
        ]);
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
                if ($userId = auth()->id()) {
                    $taskId = $this->editingTaskId;

                    if ($taskId) {
                        NotificationService::markTaskCommentNotificationsAsRead($userId, $taskId);
                    }
                }

                $this->editingTaskId = null;
                $this->editingTaskActiveRelationManager = null;
                $this->dispatchTopbarNotificationRefresh();
            });
    }
}
