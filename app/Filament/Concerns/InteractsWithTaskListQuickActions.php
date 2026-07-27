<?php

namespace App\Filament\Concerns;

use App\Models\TaskComment;
use App\Support\Tasks\TaskAttachmentStore;
use App\Support\Tasks\TaskListColumn;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

trait InteractsWithTaskListQuickActions
{
    public ?int $commentingTaskId = null;

    public ?int $attachingTaskId = null;

    public ?int $expandedCommentsTaskId = null;

    /** @var array<int, array<int, array{id: int, author: string, content: string, created_at: string}>> */
    public array $expandedCommentsCache = [];

    public function bootInteractsWithTaskListQuickActions(): void
    {
        $this->cacheAction($this->makeAddCommentAction());
        $this->cacheAction($this->makeAddAttachmentAction());
    }

    public function openAddCommentModal(int $taskId): void
    {
        $this->commentingTaskId = $taskId;
        $this->mountAction('addComment');
    }

    public function openAddAttachmentModal(int $taskId): void
    {
        $this->attachingTaskId = $taskId;
        $this->mountAction('addAttachment');
    }

    public function toggleExpandedComments(int $taskId): void
    {
        if ($this->expandedCommentsTaskId === $taskId) {
            $this->expandedCommentsTaskId = null;

            return;
        }

        $this->expandedCommentsTaskId = $taskId;

        if (isset($this->expandedCommentsCache[$taskId])) {
            return;
        }

        $this->expandedCommentsCache[$taskId] = TaskComment::query()
            ->where('task_id', $taskId)
            ->with('author')
            ->orderByDesc('created_at')
            ->get()
            ->map(static fn (TaskComment $comment): array => [
                'id' => (int) $comment->id,
                'author' => $comment->author?->name ?? '—',
                'content' => TaskListColumn::sanitizeTaskText($comment->content, 512),
                'created_at' => $comment->created_at?->format('d.m.Y H:i') ?? '—',
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, author: string, content: string, created_at: string}>
     */
    public function expandedCommentsFor(int $taskId): array
    {
        return $this->expandedCommentsCache[$taskId] ?? [];
    }

    protected function makeAddCommentAction(): Action
    {
        return Action::make('addComment')
            ->label('Dodaj komentarz')
            ->modalHeading('Dodaj komentarz')
            ->modalWidth('2xl')
            ->form([
                Forms\Components\Textarea::make('content')
                    ->label('Treść')
                    ->required()
                    ->rows(6)
                    ->columnSpanFull(),
            ])
            ->action(function (array $data): void {
                if (! $this->commentingTaskId) {
                    return;
                }

                TaskComment::query()->create([
                    'task_id' => $this->commentingTaskId,
                    'content' => $data['content'],
                    'user_id' => Auth::id(),
                ]);

                unset($this->expandedCommentsCache[$this->commentingTaskId]);
                if ($this->expandedCommentsTaskId === $this->commentingTaskId) {
                    $this->expandedCommentsTaskId = null;
                }

                $this->commentingTaskId = null;

                Notification::make()
                    ->title('Komentarz dodany')
                    ->success()
                    ->send();

                $this->afterTaskListQuickActionSaved();
            })
            ->after(function (): void {
                $this->commentingTaskId = null;
            });
    }

    protected function makeAddAttachmentAction(): Action
    {
        return Action::make('addAttachment')
            ->label('Dodaj załącznik')
            ->modalHeading('Dodaj załącznik')
            ->modalWidth('2xl')
            ->form([
                Forms\Components\FileUpload::make('files')
                    ->label('Pliki')
                    ->disk('public')
                    ->multiple()
                    ->directory('task-attachments')
                    ->preserveFilenames()
                    ->required()
                    ->columnSpanFull(),
            ])
            ->action(function (array $data): void {
                if (! $this->attachingTaskId) {
                    return;
                }

                $task = \App\Models\Task::query()->find($this->attachingTaskId);

                if ($task) {
                    TaskAttachmentStore::storeMany(
                        $task,
                        is_array($data['files'] ?? null) ? $data['files'] : [],
                        Auth::id(),
                    );
                }

                $this->attachingTaskId = null;

                Notification::make()
                    ->title('Załączniki dodane')
                    ->success()
                    ->send();

                $this->afterTaskListQuickActionSaved();
            })
            ->after(function (): void {
                $this->attachingTaskId = null;
            });
    }

    protected function afterTaskListQuickActionSaved(): void
    {
        if (method_exists($this, 'resetTable')) {
            $this->resetTable();
        }
    }
}
