<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use App\Models\Task;
use App\Models\TaskStatus;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Filament\Actions\Action;
use Livewire\Attributes\Computed;
use Filament\Notifications\Notification;

class KanbanTasks extends Page
{
    protected static string $resource = TaskResource::class;
    protected static string $view = 'filament.resources.task-resource.pages.kanban-tasks';
    protected static ?string $slug = '/kanban';
    protected static ?string $title = 'Kanban - Zarządzanie zadaniami';

    public $filterBy = '';
    public $priorityFilter = '';
    public $searchTerm = '';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Dodaj zadanie')
                ->icon('heroicon-m-plus')
                ->color('primary')
                ->url(TaskResource::getUrl('create')),
            Action::make('list')
                ->label('Widok listy')
                ->icon('heroicon-m-list-bullet')
                ->color('gray')
                ->url(TaskResource::getUrl('index')),
            Action::make('stats')
                ->label('Statystyki')
                ->icon('heroicon-m-chart-bar')
                ->color('info')
                ->action(fn() => $this->showStats()),
        ];
    }

    public function mount(): void
    {
        static::authorizeResourceAccess();
    }

    #[Computed]
    public function tasks()
    {
        $query = Task::query()
            ->with(['status', 'assignee', 'author', 'subtasks', 'attachments', 'comments'])
            ->orderBy('order');

        // Apply filters
        if ($this->filterBy === 'author') {
            $query->where('author_id', Auth::id());
        } elseif ($this->filterBy === 'assignee') {
            $query->where('assignee_id', Auth::id());
        }

        if ($this->priorityFilter) {
            $query->where('priority', $this->priorityFilter);
        }

        if ($this->searchTerm) {
            $query->where(function ($q) {
                $q->where('title', 'like', '%' . $this->searchTerm . '%')
                    ->orWhere('description', 'like', '%' . $this->searchTerm . '%');
            });
        }

        return $query->get();
    }

    #[Computed]
    public function statuses()
    {
        return TaskStatus::orderBy('order')->get();
    }

    public function updateTaskStatus($taskId, $statusId, $order = null)
    {
        try {
            $task = Task::findOrFail($taskId);

            // Security check - can user modify this task?
            if (!$this->canModifyTask($task)) {
                $this->addError('task', 'Nie masz uprawnień do modyfikacji tego zadania.');
                return;
            }

            $oldStatusId = $task->status_id;
            $task->status_id = $statusId;

            if ($order !== null) {
                $task->order = $order;
            }

            $task->save();

            // Log the change if status changed
            if ($oldStatusId != $statusId) {
                $oldStatus = TaskStatus::find($oldStatusId);
                $newStatus = TaskStatus::find($statusId);

                // You could add audit logging here
                Log::info("Task {$task->id} moved from {$oldStatus?->name} to {$newStatus?->name} by user " . Auth::id());
            }

            Notification::make()
                ->title('Zadanie zaktualizowane')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Log::error('Error updating task status: ' . $e->getMessage());

            Notification::make()
                ->title('Błąd podczas aktualizacji zadania')
                ->danger()
                ->send();
        }
    }

    public function updateTaskOrder($taskId, $order)
    {
        try {
            $task = Task::findOrFail($taskId);

            if (!$this->canModifyTask($task)) {
                return;
            }

            $task->order = $order;
            $task->save();
        } catch (\Exception $e) {
            Log::error('Error updating task order: ' . $e->getMessage());
        }
    }

    public function deleteTask($taskId)
    {
        try {
            $task = Task::findOrFail($taskId);

            if (!$this->canDeleteTask($task)) {
                Notification::make()
                    ->title('Nie masz uprawnień do usunięcia tego zadania')
                    ->danger()
                    ->send();
                return;
            }

            $task->delete();

            Notification::make()
                ->title('Zadanie zostało usunięte')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Log::error('Error deleting task: ' . $e->getMessage());

            Notification::make()
                ->title('Błąd podczas usuwania zadania')
                ->danger()
                ->send();
        }
    }

    public function refreshBoard()
    {
        $this->reset(['filterBy', 'priorityFilter', 'searchTerm']);

        Notification::make()
            ->title('Tablica została odświeżona')
            ->success()
            ->send();
    }

    public function showStats()
    {
        $stats = [
            'total_tasks' => $this->tasks()->count(),
            'my_tasks' => $this->tasks()->where('assignee_id', Auth::id())->count(),
            'overdue_tasks' => $this->tasks()->filter(fn($task) => $task->due_date && $task->due_date->isPast())->count(),
            'high_priority' => $this->tasks()->where('priority', 'high')->count(),
        ];

        Notification::make()
            ->title('Statystyki zadań')
            ->body("Łącznie: {$stats['total_tasks']} | Moje: {$stats['my_tasks']} | Przeterminowane: {$stats['overdue_tasks']} | Wysokie: {$stats['high_priority']}")
            ->info()
            ->persistent()
            ->send();
    }

    protected function canModifyTask(Task $task): bool
    {
        $user = Auth::user();

        // Admin can modify all tasks
        if ($user->roles && $user->roles->contains('name', 'admin')) {
            return true;
        }

        // User can modify tasks they authored or are assigned to
        return $task->author_id === $user->id || $task->assignee_id === $user->id;
    }

    protected function canDeleteTask(Task $task): bool
    {
        $user = Auth::user();

        // Admin can delete all tasks
        if ($user->roles && $user->roles->contains('name', 'admin')) {
            return true;
        }

        // Only author can delete task
        return $task->author_id === $user->id;
    }

    protected function getViewData(): array
    {
        return [
            'tasks' => $this->tasks(),
            'statuses' => $this->statuses(),
            'currentUser' => Auth::user(),
            'taskStats' => [
                'total' => $this->tasks()->count(),
                'overdue' => $this->tasks()->filter(fn($task) => $task->due_date && $task->due_date->isPast())->count(),
                'high_priority' => $this->tasks()->where('priority', 'high')->count(),
            ]
        ];
    }
}
