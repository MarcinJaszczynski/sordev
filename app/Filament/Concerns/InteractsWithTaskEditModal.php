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
    use EnsuresFilamentActionModalVisible;

    public ?int $editingTaskId = null;

    public ?int $editingTaskActiveRelationManager = null;

    /** @var array<string, mixed> */
    public array $pendingCreateFormData = [];

    public ?string $pendingCreateDueDate = null;

    /** Livewire woła mount{Trait} automatycznie — ręczne mountInteractsWithTaskEditModal() dawało podwójny modal. */
    protected bool $taskEditModalDeepLinkHandled = false;

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
        if ($this->taskEditModalDeepLinkHandled) {
            return;
        }

        $this->taskEditModalDeepLinkHandled = true;

        $this->openDeepLinkedTaskIfPresent();
        $this->openDeepLinkedCreateTaskIfPresent();
    }

    public function openEditTaskModal(int $taskId, ?int $activeRelationManager = null): void
    {
        $this->editingTaskId = $taskId;
        $this->editingTaskActiveRelationManager = $activeRelationManager;
        $this->clearConflictingMountedTableAction();
        $this->markOpenedTaskNotificationsAsRead($taskId);
        $this->mountAction('editTask');
        $this->ensureMountedActionModalVisible();
    }

    /**
     * Oznacza zadanie i komentarze jako przeczytane przy otwarciu z dowolnego poziomu
     * (lista, impreza, topbar) — nie dopiero przy zamknięciu modala.
     */
    protected function markOpenedTaskNotificationsAsRead(int $taskId): void
    {
        $userId = auth()->id();
        if (! $userId || $taskId <= 0) {
            return;
        }

        $task = Task::query()->find($taskId);
        if (! $task) {
            return;
        }

        NotificationService::markTaskAsRead((int) $userId, $task);
        NotificationService::markTaskCommentNotificationsAsRead((int) $userId, $taskId);
        $this->dispatchTopbarNotificationRefresh();
    }

    #[On('open-edit-task-modal')]
    public function handleOpenEditTaskModalFromChild(int $taskId): void
    {
        $this->openEditTaskModal($taskId);
    }

    public function openCreateTaskModal(array $defaultFormData = [], ?string $defaultDueDate = null): void
    {
        $this->pendingCreateFormData = $defaultFormData;
        $this->pendingCreateDueDate = $defaultDueDate;
        $this->clearConflictingMountedTableAction();
        $this->mountAction('createTask');
        $this->ensureMountedActionModalVisible();
    }

    public function openCreateTaskForProgramPoint(int $programPointId): void
    {
        $this->openCreateTaskModal([
            'taskable_type' => \App\Models\EventProgramPoint::class,
            'taskable_id' => $programPointId,
        ]);
    }

    protected function clearConflictingMountedTableAction(): void
    {
        if (! method_exists($this, 'unmountTableAction')) {
            return;
        }

        if (! empty($this->mountedTableActions ?? [])) {
            $this->unmountTableAction(shouldCancelParentActions: false, shouldCloseModal: false);
        }
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

        // Odśwież listę/tablicę zadań (np. licznik i preview komentarzy na Kanban).
        if ($this->editingTaskId) {
            $task = Task::query()->find($this->editingTaskId);

            if ($task) {
                $this->afterTaskModalSaved($task);
            }
        }
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

        // mountAction() w mount() dispatchuje open-modal zanim Alpine zdąży
        // zarejestrować listener → modal zostaje w DOM z isShown=false.
        // W przeglądarce otwieramy po hydracji; w testach Livewire od razu.
        if ($this->shouldDeferTaskModalDeepLink()) {
            $this->editingTaskId = $taskId;
            $this->editingTaskActiveRelationManager = $activeRelationManager;
            $armJs = $activeRelationManager === null ? 'null' : (string) $activeRelationManager;
            $this->js("queueMicrotask(() => \$wire.call('openEditTaskModal', {$taskId}, {$armJs}))");

            return;
        }

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

        if ($this->shouldDeferTaskModalDeepLink()) {
            // Nie wołaj openCreateTaskModal() — nadpisałoby pending* pustymi defaultami.
            $this->js("queueMicrotask(() => \$wire.call('mountDeferredCreateTaskModal'))");

            return;
        }

        $this->clearConflictingMountedTableAction();
        $this->mountAction('createTask');
    }

    public function mountDeferredCreateTaskModal(): void
    {
        $this->clearConflictingMountedTableAction();
        $this->mountAction('createTask');
        $this->ensureMountedActionModalVisible();
    }

    /**
     * Livewire Feature/Unit nie wykonuje JS z $this->js() — tam montujemy synchronicznie.
     */
    protected function shouldDeferTaskModalDeepLink(): bool
    {
        return ! app()->runningUnitTests();
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
