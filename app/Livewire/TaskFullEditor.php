<?php

namespace App\Livewire;

use App\Enums\TaskPriority;
use App\Filament\Resources\TaskResource;
use App\Filament\Resources\TaskResource\RelationManagers;
use App\Models\Task;
use App\Models\TaskComment;
use App\Support\Tasks\TaskAttachmentStore;
use App\Support\Tasks\TaskContextRegistry;
use App\Services\NotificationService;
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
    use HasRelationManagers;
    use InteractsWithForms;

    public ?int $taskId = null;

    public ?string $defaultDueDate = null;

    public Task $record;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public ?string $activeRelationManager = null;

    public bool $showCommentComposer = false;

    public string $newCommentContent = '';

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

            if ($userId = Auth::id()) {
                NotificationService::markTaskCommentNotificationsAsRead($userId, $taskId);
            }

            return;
        }

        $this->record = new Task;

        $this->form->fill(array_merge([
            'due_date' => $defaultDueDate,
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
            ->get();
    }

    public function toggleCommentComposer(): void
    {
        $this->showCommentComposer = ! $this->showCommentComposer;

        if (! $this->showCommentComposer) {
            $this->newCommentContent = '';
            $this->resetErrorBag('newCommentContent');
        }
    }

    public function addComment(): void
    {
        if (! $this->record->exists) {
            return;
        }

        $validated = $this->validate([
            'newCommentContent' => ['required', 'string', 'max:10000'],
        ]);

        TaskComment::query()->create([
            'task_id' => $this->record->id,
            'content' => trim($validated['newCommentContent']),
            'user_id' => Auth::id(),
        ]);

        $this->newCommentContent = '';
        $this->showCommentComposer = false;
        $this->record->load(['comments.author']);
        unset($this->comments);

        Notification::make()
            ->title('Komentarz dodany')
            ->success()
            ->send();

        $this->dispatch('task-full-editor-updated', taskId: $this->record->id);
        $this->dispatch('comment-added');
        $this->dispatch('refresh-notifications');
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
        $this->dispatch('refresh-notifications');
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
        $this->dispatch('refresh-notifications');
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
