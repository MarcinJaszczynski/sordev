<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaskComment;
use App\Models\User;
use Filament\Actions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;
use Livewire\Attributes\Computed;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;

class TasksKanbanBoardPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = TaskResource::class;
    protected static string $view = 'filament.resources.task-resource.pages.tasks-kanban-board-page';
    protected static ?string $slug = '/';
    protected static ?string $title = 'Kanban - Zarządzanie zadaniami';

    // Właściwości filtrowania
    public $filterBy = '';
    public $priorityFilter = '';
    public $searchTerm = '';
    
    // Sortowanie kolumn
    public $columnSorts = [];

    // Modal editing
    public $editingTask = null;
    public $editModalData = [];

    // Modal states for additional features
    public $showingSubtasks = false;
    public $showingComments = false;
    public $showingAttachments = false;
    public $currentTaskForDetails = null;
    public $newComment = '';
    public $newSubtaskTitle = '';
    
    // Quick add task
    public $showingQuickAdd = false;
    public $quickAddStatusId = null;
    public $quickTaskTitle = '';
    public $quickTaskDescription = '';
    public $quickTaskPriority = 'medium';
    public $quickTaskAssigneeId = null;

    // --- Subtask editing ---
    public $editingSubtask = false;
    public $editSubtaskId = null;
    public $editSubtaskData = [
        'title' => '',
        'description' => '',
        'priority' => 'medium',
        'assignee_id' => null,
        'status_id' => null,
        'due_date' => null,
    ];

    public function mount(): void
    {
        static::authorizeResourceAccess();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create')
                ->label('Dodaj zadanie')
                ->icon('heroicon-m-plus')
                ->color('primary')
                ->url(TaskResource::getUrl('create')),
            
            Actions\Action::make('refresh')
                ->label('Odśwież')
                ->icon('heroicon-m-arrow-path')
                ->color('gray')
                ->action(fn () => $this->refreshBoard()),
        ];
    }

    #[Computed]
    public function tasks()
    {
        $query = Task::query()
            ->with(['status', 'assignee', 'author', 'subtasks', 'attachments', 'comments']);

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

        $tasks = $query->get();
        
        // Apply column-specific sorting
        $sortedTasks = collect();
        
        foreach ($this->statuses() as $status) {
            $statusTasks = $tasks->where('status_id', $status->id);
            
            if (isset($this->columnSorts[$status->id])) {
                $sortType = $this->columnSorts[$status->id];
                
                switch ($sortType) {
                    case 'priority_desc':
                        $statusTasks = $statusTasks->sortByDesc(function ($task) {
                            return ['high' => 3, 'medium' => 2, 'low' => 1][$task->priority] ?? 0;
                        });
                        break;
                    case 'priority_asc':
                        $statusTasks = $statusTasks->sortBy(function ($task) {
                            return ['high' => 3, 'medium' => 2, 'low' => 1][$task->priority] ?? 0;
                        });
                        break;
                    case 'due_date_asc':
                        $statusTasks = $statusTasks->sortBy('due_date');
                        break;
                    case 'due_date_desc':
                        $statusTasks = $statusTasks->sortByDesc('due_date');
                        break;
                    case 'title_asc':
                        $statusTasks = $statusTasks->sortBy('title');
                        break;
                    case 'title_desc':
                        $statusTasks = $statusTasks->sortByDesc('title');
                        break;
                    case 'created_desc':
                        $statusTasks = $statusTasks->sortByDesc('created_at');
                        break;
                    case 'created_asc':
                        $statusTasks = $statusTasks->sortBy('created_at');
                        break;
                    default:
                        $statusTasks = $statusTasks->sortBy('order');
                        break;
                }
            } else {
                $statusTasks = $statusTasks->sortBy('order');
            }
            
            $sortedTasks = $sortedTasks->merge($statusTasks->values());
        }
        
        return $sortedTasks;
    }

    #[Computed]
    public function statuses()
    {
        return TaskStatus::orderBy('order')->get();
    }

    #[Computed]
    public function users()
    {
        return User::orderBy('name')->get();
    }

    public function editTask($taskId)
    {
        $task = Task::findOrFail($taskId);
        
        if (!$this->canModifyTask($task)) {
            Notification::make()
                ->title('Brak uprawnień')
                ->body('Nie masz uprawnień do edycji tego zadania.')
                ->danger()
                ->send();
            return;
        }

        $this->editingTask = $task;
        $this->editModalData = $task->toArray();
        $this->dispatch('open-modal', id: 'edit-task-modal');
    }

    public function saveTask()
    {
        if (!$this->editingTask || !$this->canModifyTask($this->editingTask)) {
            return;
        }

        try {
            $this->editingTask->update($this->editModalData);

            Notification::make()
                ->title('Zadanie zaktualizowane')
                ->body('Zmiany zostały pomyślnie zapisane.')
                ->success()
                ->send();

            $this->editingTask = null;
            $this->editModalData = [];
            $this->dispatch('close-modal', id: 'edit-task-modal');

        } catch (\Exception $e) {
            Log::error('Error updating task: ' . $e->getMessage());
            
            Notification::make()
                ->title('Błąd podczas aktualizacji')
                ->body('Wystąpił błąd podczas zapisywania zmian.')
                ->danger()
                ->send();
        }
    }

    public function updateTaskStatus($taskId, $statusId, $order = null)
    {
        try {
            $task = Task::findOrFail($taskId);
            
            if (!$this->canModifyTask($task)) {
                return;
            }

            $oldStatusId = $task->status_id;
            $task->status_id = $statusId;
            
            if ($order !== null) {
                $task->order = $order;
            }
            
            $task->save();

            // Clear cached tasks to refresh counters
            unset($this->tasks);

            // Log the change
            if ($oldStatusId != $statusId) {
                $oldStatus = TaskStatus::find($oldStatusId);
                $newStatus = TaskStatus::find($statusId);
                
                Log::info("Task {$task->id} moved from {$oldStatus?->name} to {$newStatus?->name} by user " . Auth::id());
                
                Notification::make()
                    ->title('Status zadania zmieniony')
                    ->body("Zadanie przeniesiono do kolumny: {$newStatus?->name}")
                    ->success()
                    ->send();
            }

        } catch (\Exception $e) {
            Log::error('Error updating task status: ' . $e->getMessage());
            
            Notification::make()
                ->title('Błąd podczas przenoszenia')
                ->body('Wystąpił błąd podczas zmiany statusu zadania.')
                ->danger()
                ->send();
        }
    }

    public function deleteTask($taskId)
    {
        try {
            $task = Task::findOrFail($taskId);
            
            if (!$this->canDeleteTask($task)) {
                Notification::make()
                    ->title('Brak uprawnień')
                    ->body('Nie masz uprawnień do usunięcia tego zadania.')
                    ->danger()
                    ->send();
                return;
            }

            $task->delete();

            Notification::make()
                ->title('Zadanie usunięte')
                ->body('Zadanie zostało pomyślnie usunięte.')
                ->success()
                ->send();

        } catch (\Exception $e) {
            Log::error('Error deleting task: ' . $e->getMessage());
            
            Notification::make()
                ->title('Błąd podczas usuwania')
                ->body('Wystąpił błąd podczas usuwania zadania.')
                ->danger()
                ->send();
        }
    }

    public function refreshBoard()
    {
        $this->reset(['filterBy', 'priorityFilter', 'searchTerm', 'columnSorts']);
        
        // Clear computed properties
        unset($this->tasks);
        
        Notification::make()
            ->title('Tablica odświeżona')
            ->body('Filtry i sortowanie zostały zresetowane.')
            ->success()
            ->send();
    }

    public function sortColumn($statusId, $sortType)
    {
        $this->columnSorts[$statusId] = $sortType;
        
        // Clear computed property to force refresh
        unset($this->tasks);
        
        $sortNames = [
            'priority_desc' => 'Priorytet (wysoki-niski)',
            'priority_asc' => 'Priorytet (niski-wysoki)', 
            'due_date_asc' => 'Data (najwcześniej)',
            'due_date_desc' => 'Data (najpóźniej)',
            'title_asc' => 'Tytuł (A-Z)',
            'title_desc' => 'Tytuł (Z-A)',
            'created_desc' => 'Najnowsze',
            'created_asc' => 'Najstarsze',
        ];
        
        $status = TaskStatus::find($statusId);
        
        Notification::make()
            ->title('Sortowanie zastosowane')
            ->body("Kolumna '{$status->name}' posortowana według: " . ($sortNames[$sortType] ?? $sortType))
            ->success()
            ->send();
    }

    public function openQuickAddModal($statusId)
    {
        $this->quickAddStatusId = $statusId;
        $this->reset(['quickTaskTitle', 'quickTaskDescription', 'quickTaskPriority', 'quickTaskAssigneeId']);
        $this->showingQuickAdd = true;
        
        $this->dispatch('open-modal', id: 'quick-add-modal');
    }

    public function createQuickTask()
    {
        $this->validate([
            'quickTaskTitle' => 'required|string|max:255',
            'quickTaskDescription' => 'nullable|string',
            'quickTaskPriority' => 'required|in:low,medium,high',
            'quickTaskAssigneeId' => 'nullable|exists:users,id',
        ]);

        try {
            $task = Task::create([
                'title' => $this->quickTaskTitle,
                'description' => $this->quickTaskDescription,
                'priority' => $this->quickTaskPriority,
                'status_id' => $this->quickAddStatusId,
                'author_id' => Auth::id(),
                'assignee_id' => $this->quickTaskAssigneeId,
                'order' => Task::where('status_id', $this->quickAddStatusId)->max('order') + 1,
            ]);

            $this->showingQuickAdd = false;
            $this->dispatch('close-modal', id: 'quick-add-modal');
            
            // Refresh tasks
            unset($this->tasks);
            
            Notification::make()
                ->title('Zadanie utworzone')
                ->body("Zadanie '{$task->title}' zostało pomyślnie utworzone.")
                ->success()
                ->send();

        } catch (\Exception $e) {
            Log::error('Error creating quick task: ' . $e->getMessage());
            
            Notification::make()
                ->title('Błąd podczas tworzenia zadania')
                ->body('Wystąpił błąd podczas tworzenia zadania.')
                ->danger()
                ->send();
        }
    }

    public function cancelQuickAdd()
    {
        $this->showingQuickAdd = false;
        $this->dispatch('close-modal', id: 'quick-add-modal');
    }

    public function showSubtasks($taskId)
    {
        $this->currentTaskForDetails = Task::with(['subtasks.status', 'subtasks.assignee'])->findOrFail($taskId);
        $this->showingSubtasks = true;
        $this->dispatch('open-modal', id: 'subtasks-modal');
    }

    public function showComments($taskId)
    {
        $this->currentTaskForDetails = Task::with(['comments.author'])->findOrFail($taskId);
        $this->showingComments = true;
        $this->dispatch('open-modal', id: 'comments-modal');
    }

    public function showAttachments($taskId)
    {
        $this->currentTaskForDetails = Task::with('attachments')->findOrFail($taskId);
        $this->showingAttachments = true;
        $this->dispatch('open-modal', id: 'attachments-modal');
    }

    public function addComment()
    {
        if (!$this->currentTaskForDetails || !$this->newComment) {
            return;
        }

        try {
            $this->currentTaskForDetails->comments()->create([
                'content' => $this->newComment,
                'author_id' => Auth::id(),
            ]);

            $this->newComment = '';
            $this->currentTaskForDetails->refresh();
            $this->currentTaskForDetails->load(['comments.author']);

            Notification::make()
                ->title('Komentarz dodany')
                ->body('Komentarz został pomyślnie dodany.')
                ->success()
                ->send();

        } catch (\Exception $e) {
            Log::error('Error adding comment: ' . $e->getMessage());
            
            Notification::make()
                ->title('Błąd')
                ->body('Wystąpił błąd podczas dodawania komentarza.')
                ->danger()
                ->send();
        }
    }

    public function addSubtask()
    {
        if (!$this->currentTaskForDetails || !$this->newSubtaskTitle) {
            return;
        }

        try {
            $defaultStatus = TaskStatus::where('is_default', true)->first() 
                ?? TaskStatus::orderBy('order')->first();

            $this->currentTaskForDetails->subtasks()->create([
                'title' => $this->newSubtaskTitle,
                'description' => '',
                'status_id' => $defaultStatus->id,
                'author_id' => Auth::id(),
                'assignee_id' => $this->currentTaskForDetails->assignee_id,
                'priority' => 'medium',
            ]);

            $this->newSubtaskTitle = '';
            $this->currentTaskForDetails->refresh();
            $this->currentTaskForDetails->load(['subtasks.status', 'subtasks.assignee']);

            Notification::make()
                ->title('Podzadanie dodane')
                ->body('Podzadanie zostało pomyślnie dodane.')
                ->success()
                ->send();

        } catch (\Exception $e) {
            Log::error('Error adding subtask: ' . $e->getMessage());
            
            Notification::make()
                ->title('Błąd')
                ->body('Wystąpił błąd podczas dodawania podzadania.')
                ->danger()
                ->send();
        }
    }

    public function updateSubtaskStatus($subtaskId, $statusId)
    {
        try {
            $subtask = Task::findOrFail($subtaskId);
            $subtask->status_id = $statusId;
            $subtask->save();

            $this->currentTaskForDetails->refresh();
            $this->currentTaskForDetails->load(['subtasks.status', 'subtasks.assignee']);

            Notification::make()
                ->title('Status podzadania zmieniony')
                ->success()
                ->send();

        } catch (\Exception $e) {
            Log::error('Error updating subtask status: ' . $e->getMessage());
        }
    }

    public function deleteComment($commentId)
    {
        try {
            $comment = \App\Models\TaskComment::findOrFail($commentId);
            
            if ($comment->author_id !== Auth::id() && !Auth::user()->roles->contains('name', 'admin')) {
                Notification::make()
                    ->title('Brak uprawnień')
                    ->body('Nie możesz usunąć tego komentarza.')
                    ->danger()
                    ->send();
                return;
            }

            $comment->delete();
            $this->currentTaskForDetails->refresh();
            $this->currentTaskForDetails->load(['comments.author']);

            Notification::make()
                ->title('Komentarz usunięty')
                ->success()
                ->send();

        } catch (\Exception $e) {
            Log::error('Error deleting comment: ' . $e->getMessage());
        }
    }

    protected function canModifyTask(Task $task): bool
    {
        $user = Auth::user();
        
        if ($user->roles && $user->roles->contains('name', 'admin')) {
            return true;
        }
        
        return $task->author_id === $user->id || $task->assignee_id === $user->id;
    }

    protected function canDeleteTask(Task $task): bool
    {
        $user = Auth::user();
        
        if ($user->roles && $user->roles->contains('name', 'admin')) {
            return true;
        }
        
        return $task->author_id === $user->id;
    }

    protected function getViewData(): array
    {
        return [
            'tasks' => $this->tasks(),
            'statuses' => $this->statuses(),
            'currentUser' => Auth::user(),
            'users' => \App\Models\User::all(), // Dodano przekazywanie użytkowników
        ];
    }

    // --- Subtask editing ---
    public function editSubtask($subtaskId)
    {
        $subtask = Task::findOrFail($subtaskId);
        $this->editSubtaskId = $subtaskId;
        $this->editSubtaskData = [
            'title' => $subtask->title,
            'description' => $subtask->description,
            'priority' => $subtask->priority,
            'assignee_id' => $subtask->assignee_id,
            'status_id' => $subtask->status_id,
            'due_date' => $subtask->due_date ? $subtask->due_date->format('Y-m-d\TH:i') : null,
        ];
        $this->editingSubtask = true;
    }

    public function cancelEditSubtask()
    {
        $this->editingSubtask = false;
        $this->editSubtaskId = null;
        $this->editSubtaskData = [
            'title' => '',
            'description' => '',
            'priority' => 'medium',
            'assignee_id' => null,
            'status_id' => null,
            'due_date' => null,
        ];
    }

    public function saveSubtask()
    {
        $this->validate([
            'editSubtaskData.title' => 'required|string|max:255',
            'editSubtaskData.priority' => 'required|in:low,medium,high',
            'editSubtaskData.status_id' => 'required|exists:task_statuses,id',
            'editSubtaskData.assignee_id' => 'nullable|exists:users,id',
            'editSubtaskData.due_date' => 'nullable|date',
        ]);
        $subtask = Task::findOrFail($this->editSubtaskId);
        $subtask->update([
            'title' => $this->editSubtaskData['title'],
            'description' => $this->editSubtaskData['description'],
            'priority' => $this->editSubtaskData['priority'],
            'assignee_id' => $this->editSubtaskData['assignee_id'],
            'status_id' => $this->editSubtaskData['status_id'],
            'due_date' => $this->editSubtaskData['due_date'],
        ]);
        $this->editingSubtask = false;
        $this->editSubtaskId = null;
        $this->editSubtaskData = [
            'title' => '',
            'description' => '',
            'priority' => 'medium',
            'assignee_id' => null,
            'status_id' => null,
            'due_date' => null,
        ];
        $this->currentTaskForDetails->refresh();
        $this->currentTaskForDetails->load(['subtasks.status', 'subtasks.assignee']);
        \Filament\Notifications\Notification::make()
            ->title('Podzadanie zapisane')
            ->success()
            ->send();
    }

    // Dodajemy przełącznik do pełnego formularza dodawania podzadania
    public $showAdvancedSubtaskForm = false;
    public function showAdvancedSubtaskForm()
    {
        $this->showAdvancedSubtaskForm = true;
        $this->editingSubtask = false;
        $this->editSubtaskId = null;
        $this->editSubtaskData = [
            'title' => '',
            'description' => '',
            'priority' => 'medium',
            'assignee_id' => null,
            'status_id' => null,
            'due_date' => null,
        ];
    }
    public function cancelAdvancedSubtaskForm()
    {
        $this->showAdvancedSubtaskForm = false;
        $this->editSubtaskData = [
            'title' => '',
            'description' => '',
            'priority' => 'medium',
            'assignee_id' => null,
            'status_id' => null,
            'due_date' => null,
        ];
    }
    public function addAdvancedSubtask()
    {
        $this->validate([
            'editSubtaskData.title' => 'required|string|max:255',
            'editSubtaskData.priority' => 'required|in:low,medium,high',
            'editSubtaskData.status_id' => 'required|exists:task_statuses,id',
            'editSubtaskData.assignee_id' => 'nullable|exists:users,id',
            'editSubtaskData.due_date' => 'nullable|date',
        ]);
        $this->currentTaskForDetails->subtasks()->create([
            'title' => $this->editSubtaskData['title'],
            'description' => $this->editSubtaskData['description'],
            'priority' => $this->editSubtaskData['priority'],
            'assignee_id' => $this->editSubtaskData['assignee_id'],
            'status_id' => $this->editSubtaskData['status_id'],
            'due_date' => $this->editSubtaskData['due_date'],
            'author_id' => Auth::id(),
        ]);
        $this->showAdvancedSubtaskForm = false;
        $this->editSubtaskData = [
            'title' => '',
            'description' => '',
            'priority' => 'medium',
            'assignee_id' => null,
            'status_id' => null,
            'due_date' => null,
        ];
        $this->currentTaskForDetails->refresh();
        $this->currentTaskForDetails->load(['subtasks.status', 'subtasks.assignee']);
        \Filament\Notifications\Notification::make()
            ->title('Podzadanie dodane')
            ->success()
            ->send();
    }
}
