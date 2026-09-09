<?php

namespace App\Livewire;

use App\Enums\TaskPriority;
use App\Filament\Concerns\DispatchesTopbarNotificationRefresh;
use App\Filament\Resources\TaskResource;
use App\Filament\Resources\TaskResource\RelationManagers;
use App\Models\Task;
use App\Models\TaskComment;
use App\Services\NotificationService;
use App\Support\Tasks\TaskAttachmentStore;
use App\Support\Tasks\TaskContextRegistry;
use App\Support\Tasks\TaskDueDates;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\HasRelationManagers;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class TaskFullEditor extends Component implements HasForms
{
    use DispatchesTopbarNotificationRefresh;
    use HasRelationManagers;
    use InteractsWithForms;

    public ?int $taskId = null;

    public ?string $defaultDueDate = null;

    public Task $record;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public ?string $activeRelationManager = null;

    public string $newCommentContent = '';

    public bool $showEarlierComments = false;

    public function mount(
        ?int $taskId = null,
        ?string $defaultDueDate = null,
        ?int $activeRelationManager = null,
        array $defaultFormData = [],
    ): void {
        $this->taskId = $taskId;
        $this->defaultDueDate = $defaultDueDate;
        $this->activeRelationManager = $activeRelationManager !== null ? (string) $activeRelationManager : null;

        if ($taskId) {
            $this->record = Task::query()
                ->with(['taskable', 'parent', 'status', 'assignee', 'comments.author'])
                ->findOrFail($taskId);
            $this->form->fill($this->record->attributesToArray());

            return;
        }

        $this->record = new Task;

        $resolvedDueDate = $defaultDueDate
            ?? TaskDueDates::defaultForNew()->format('Y-m-d H:i:s');

        $this->form->fill(array_merge([
            'due_date' => $resolvedDueDate,
            'status_id' => Task::getDefaultStatusId(),
            'priority' => TaskPriority::Normal->value,
            'assignee_id' => Auth::id(),
            'pending_attachments' => [],
        ], $defaultFormData));
    }

    public function form(Form $form): Form
    {
        return TaskResource::modalEditorForm(
            $form
                ->model($this->record)
                ->statePath('data')
        );
    }

    public function getRecord(): Task
    {
        return $this->record;
    }

    public static function getResource(): string
    {
        return TaskResource::class;
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return false;
    }

    /**
     * @return array<int, class-string>
     */
    public function getInlineRelationManagers(): array
    {
        if (! $this->record->exists) {
            return [];
        }

        return [
            RelationManagers\AttachmentsRelationManager::class,
            RelationManagers\SubtasksRelationManager::class,
        ];
    }

    #[Computed]
    public function comments(): Collection
    {
        if (! $this->record->exists) {
            return collect();
        }

        return $this->record
            ->comments()
            ->with('author')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    #[Computed]
    public function latestComment(): ?TaskComment
    {
        return $this->comments->last();
    }

    #[Computed]
    public function earlierComments(): Collection
    {
        return $this->comments->count() > 1
            ? $this->comments->slice(0, -1)->values()
            : collect();
    }

    public function toggleEarlierComments(): void
    {
        $this->showEarlierComments = ! $this->showEarlierComments;
    }

    public function addComment(): void
    {
        if (! $this->record->exists) {
            return;
        }

        $validated = $this->validate([
            'newCommentContent' => ['required', 'string', 'max:10000'],
        ]);

        $comment = TaskComment::query()->create([
            'task_id' => $this->record->id,
            'content' => trim($validated['newCommentContent']),
            'user_id' => Auth::id(),
        ]);

        NotificationService::clearCacheForTaskCommentStakeholders($comment);

        $this->newCommentContent = '';
        $this->showEarlierComments = false;
        $this->record->load(['comments.author']);
        unset($this->comments, $this->latestComment, $this->earlierComments);

        Notification::make()
            ->title('Komentarz dodany')
            ->success()
            ->send();

        $this->dispatch('task-full-editor-updated', taskId: $this->record->id);
        $this->dispatch('comment-added');
        $this->dispatchTopbarNotificationRefresh();
    }

    public function openParentTask(): void
    {
        $this->record->loadMissing('parent');

        $parentId = (int) ($this->record->parent_id ?? 0);

        if ($parentId <= 0) {
            return;
        }

        // Rodzic Livewire (ListTasks / Event tasks / Kanban) otworzy modal nadrzędnego.
        $this->dispatch('open-edit-task-modal', taskId: $parentId);
    }

    /**
     * @return array<int, array{label: string, url: string, icon?: string}>
     */
    public function contextLinks(): array
    {
        if (! $this->record->exists) {
            return [];
        }

        return TaskContextRegistry::linksForTask($this->record);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $pendingAttachments = $state['pending_attachments'] ?? [];
        unset($state['pending_attachments']);

        if ($this->record->exists) {
            $this->record->update($state);
            $this->record->refresh();
            $message = 'Zadanie zapisane';
        } else {
            $state['author_id'] = Auth::id();
            $this->record = Task::create($state);
            $this->taskId = $this->record->id;
            $this->form->model($this->record);
            TaskAttachmentStore::storeMany($this->record, $pendingAttachments, Auth::id());
            $this->record->load(['taskable', 'parent', 'status', 'assignee']);
            $this->form->fill($this->record->attributesToArray());
            $message = count(array_filter($pendingAttachments)) > 0
                ? 'Zadanie utworzone wraz z załącznikami'
                : 'Zadanie utworzone — możesz dodać podzadania, pliki i komentarze poniżej';
        }

        $userId = Auth::id();
        if ($userId) {
            \App\Services\NotificationService::clearCacheForUser($userId);
        }

        Notification::make()
            ->title($message)
            ->success()
            ->send();

        $this->dispatch('task-full-editor-updated', taskId: $this->record->id);
        $this->dispatchTopbarNotificationRefresh();
    }

    public function updatedDataStatusId($value): void
    {
        if (! $this->record->exists || ! filled($value)) {
            return;
        }

        $this->record->update(['status_id' => (int) $value]);
        $this->record->refresh();

        Notification::make()
            ->title('Status zapisany')
            ->success()
            ->send();

        $this->dispatch('task-full-editor-updated', taskId: $this->record->id);
        $this->dispatchTopbarNotificationRefresh();
    }

    public function render(): View
    {
        return view('livewire.task-full-editor');
    }

    /**
     * @return array<int|string, string>
     */
    protected function getForms(): array
    {
        return [
            'form',
        ];
    }
}
