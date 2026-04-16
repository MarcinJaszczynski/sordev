<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\ContractorResource;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventSettlementResource;
use App\Filament\Resources\EventTemplateProgramPointResource;
use App\Filament\Resources\EventTemplateResource;
use App\Filament\Resources\TaskResource;
use App\Models\Contractor;
use App\Models\Event;
use App\Models\EventDocument;
use App\Models\EventProgramPoint;
use App\Models\EventSettlementCost;
use App\Models\EventSettlementDocument;
use App\Models\EventSettlementParticipantPayment;
use App\Models\EventTemplate;
use App\Models\EventTemplateProgramPoint;
use App\Models\PilotCashPreparation;
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
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
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
    protected ?string $maxContentWidth = 'full';

    // Właściwości filtrowania
    public $filterBy = '';
    public $priorityFilter = '';
    public $contextFilter = '';
    public $searchTerm = '';
    public $dueFilter = '';
    
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
    public $newSubtaskAssigneeId = null;
    
    // Quick add task
    public $showingQuickAdd = false;
    public $quickAddStatusId = null;
    public $quickTaskTitle = '';
    public $quickTaskDescription = '';
    public $quickTaskPriority = 'medium';
    public $quickTaskAssigneeId = null;
    public $quickTaskableType = null;
    public $quickTaskableId = null;

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
            ->with(['status', 'assignee', 'author', 'subtasks', 'attachments', 'comments.author', 'taskable']);

        // Apply filters
        if ($this->filterBy === 'author') {
            $query->where('author_id', Auth::id());
        } elseif ($this->filterBy === 'assignee') {
            $query->where('assignee_id', Auth::id());
        }

        if ($this->priorityFilter) {
            $query->where('priority', $this->priorityFilter);
        }

        if ($this->contextFilter === '__unassigned') {
            $query->whereNull('taskable_type');
        } elseif ($this->contextFilter) {
            $query->where('taskable_type', $this->contextFilter);
        }

        if ($this->searchTerm) {
            $query->where(function ($q) {
                $q->where('title', 'like', '%' . $this->searchTerm . '%')
                  ->orWhere('description', 'like', '%' . $this->searchTerm . '%');
            });
        }

        if ($this->dueFilter === 'overdue') {
            $query->whereNotNull('due_date')->where('due_date', '<', now());
        }

        if ($this->dueFilter === 'has_due_date') {
            $query->whereNotNull('due_date');
        }

        $tasks = $query->get();
        
        // Apply column-specific sorting
        $sortedTasks = collect();
        
        foreach ($this->statuses() as $status) {
            $statusTasks = $tasks->where('status_id', $status->id);
            
            if (isset($this->columnSorts[$status->id])) {
                $sortType = $this->columnSorts[$status->id];
                
                switch ($sortType) {
                    case 'activity_desc':
                        $statusTasks = $statusTasks->sortByDesc(fn (Task $task) => $this->getTaskActivityTimestamp($task));
                        break;
                    case 'activity_asc':
                        $statusTasks = $statusTasks->sortBy(fn (Task $task) => $this->getTaskActivityTimestamp($task));
                        break;
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
                        $statusTasks = $statusTasks->sortByDesc(fn (Task $task) => $this->getTaskActivityTimestamp($task));
                        break;
                }
            } else {
                // Domyślnie pokazuj najnowszą aktywność: komentarz, załącznik, podzadanie lub aktualizacja zadania.
                $statusTasks = $statusTasks->sortByDesc(fn (Task $task) => $this->getTaskActivityTimestamp($task));
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
        $task = Task::with(['status', 'assignee', 'author', 'attachments', 'comments.author', 'subtasks', 'taskable'])
            ->findOrFail($taskId);
        
        if (!$this->canModifyTask($task)) {
            Notification::make()
                ->title('Brak uprawnień')
                ->body('Nie masz uprawnień do edycji tego zadania.')
                ->danger()
                ->send();
            return;
        }

        $this->editingTask = $task;
        $this->editModalData = Arr::only($task->toArray(), [
            'title',
            'description',
            'priority',
            'status_id',
            'assignee_id',
            'due_date',
            'taskable_type',
            'taskable_id',
        ]);
        $this->editModalData['due_date'] = $task->due_date?->format('Y-m-d\TH:i');
        $this->dispatch('open-modal', id: 'edit-task-modal');
    }

    public function saveTask()
    {
        if (!$this->editingTask || !$this->canModifyTask($this->editingTask)) {
            return;
        }

        try {
            $this->validate([
                'editModalData.title' => 'required|string|max:255',
                'editModalData.description' => 'nullable|string',
                'editModalData.priority' => 'required|in:low,medium,high',
                'editModalData.status_id' => 'required|exists:task_statuses,id',
                'editModalData.assignee_id' => 'nullable|exists:users,id',
                'editModalData.due_date' => 'nullable|date',
                'editModalData.taskable_type' => ['nullable', Rule::in(Task::getSupportedTaskableTypes())],
                'editModalData.taskable_id' => 'nullable|integer|required_with:editModalData.taskable_type',
            ]);

            $this->editingTask->update($this->sanitizeTaskData($this->editModalData));
            $this->editingTask->refresh();
            $this->editingTask->load(['status', 'assignee', 'author', 'attachments', 'comments.author', 'subtasks', 'taskable']);

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
        $this->reset(['filterBy', 'priorityFilter', 'contextFilter', 'searchTerm', 'dueFilter', 'columnSorts']);
        
        // Clear computed properties
        unset($this->tasks);
        
        Notification::make()
            ->title('Tablica odświeżona')
            ->body('Filtry i sortowanie zostały zresetowane.')
            ->success()
            ->send();
    }

    public function applyQuickFilter(string $filter): void
    {
        match ($filter) {
            'all' => $this->reset(['filterBy', 'priorityFilter', 'contextFilter', 'searchTerm', 'dueFilter']),
            'assigned_to_me' => $this->filterBy = 'assignee',
            'high_priority' => $this->priorityFilter = 'high',
            'overdue' => $this->dueFilter = 'overdue',
            default => null,
        };

        unset($this->tasks);
    }

    public function sortColumn($statusId, $sortType)
    {
        $this->columnSorts[$statusId] = $sortType;
        
        // Clear computed property to force refresh
        unset($this->tasks);
        
        $sortNames = [
            'activity_desc' => 'Najnowsza aktywność',
            'activity_asc' => 'Najstarsza aktywność',
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
        $this->quickAddStatusId = $statusId ?: Task::getDefaultStatusId();
        $this->reset([
            'quickTaskTitle',
            'quickTaskDescription',
            'quickTaskPriority',
            'quickTaskAssigneeId',
            'quickTaskableType',
            'quickTaskableId',
        ]);
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
            'quickTaskableType' => ['nullable', Rule::in(Task::getSupportedTaskableTypes())],
            'quickTaskableId' => 'nullable|integer|required_with:quickTaskableType',
        ]);

        $quickTaskData = $this->sanitizeTaskData([
            'title' => $this->quickTaskTitle,
            'description' => $this->quickTaskDescription,
            'priority' => $this->quickTaskPriority,
            'status_id' => $this->quickAddStatusId ?: Task::getDefaultStatusId(),
            'author_id' => Auth::id(),
            'assignee_id' => $this->quickTaskAssigneeId,
            'taskable_type' => $this->quickTaskableType,
            'taskable_id' => $this->quickTaskableId,
            'order' => Task::where('status_id', $this->quickAddStatusId ?: Task::getDefaultStatusId())->max('order') + 1,
        ]);

        try {
            $task = Task::create($quickTaskData);

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
        $this->currentTaskForDetails = $this->loadDetailsTask($taskId);
        $this->prepareSubtaskFormDefaults();
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
        $this->currentTaskForDetails = Task::with('attachments.user')->findOrFail($taskId);
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
        if (! $this->currentTaskForDetails) {
            return;
        }

        $this->validate([
            'editSubtaskData.title' => 'required|string|max:255',
            'editSubtaskData.description' => 'nullable|string',
            'editSubtaskData.priority' => 'required|in:low,medium,high',
            'editSubtaskData.status_id' => 'required|exists:task_statuses,id',
            'editSubtaskData.assignee_id' => 'nullable|exists:users,id',
            'editSubtaskData.due_date' => 'nullable|date',
        ]);

        try {
            $this->currentTaskForDetails->subtasks()->create([
                'title' => $this->editSubtaskData['title'],
                'description' => $this->editSubtaskData['description'] ?? null,
                'status_id' => $this->editSubtaskData['status_id'],
                'author_id' => Auth::id(),
                'assignee_id' => filled($this->editSubtaskData['assignee_id'] ?? null)
                    ? $this->editSubtaskData['assignee_id']
                    : $this->currentTaskForDetails->assignee_id,
                'priority' => $this->editSubtaskData['priority'],
                'due_date' => $this->editSubtaskData['due_date'] ?? null,
                'taskable_type' => $this->currentTaskForDetails->taskable_type,
                'taskable_id' => $this->currentTaskForDetails->taskable_id,
            ]);

            $this->currentTaskForDetails = $this->loadDetailsTask($this->currentTaskForDetails->getKey());
            $this->prepareSubtaskFormDefaults();

            Notification::make()
                ->title('Podzadanie dodane')
                ->body('Podzadanie zostało pomyślnie dodane z pełnym zestawem pól.')
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
        $tasks = $this->tasks();
        $taskContextUrls = $tasks->mapWithKeys(fn (Task $task): array => [
            $task->id => $this->resolveTaskContextUrl($task),
        ])->all();
        $taskContextTrees = $tasks->mapWithKeys(fn (Task $task): array => [
            $task->id => $this->resolveTaskContextTree($task),
        ])->all();

        $editingTaskContextUrl = $this->editingTask instanceof Task
            ? $this->resolveTaskContextUrl($this->editingTask)
            : null;

        $currentTaskContextUrl = $this->currentTaskForDetails instanceof Task
            ? $this->resolveTaskContextUrl($this->currentTaskForDetails)
            : null;

        $editingTaskContextTree = $this->editingTask instanceof Task
            ? $this->resolveTaskContextTree($this->editingTask)
            : [];

        $currentTaskContextTree = $this->currentTaskForDetails instanceof Task
            ? $this->resolveTaskContextTree($this->currentTaskForDetails)
            : [];

        $currentTaskHierarchy = $this->currentTaskForDetails instanceof Task
            ? $this->resolveTaskHierarchy($this->currentTaskForDetails)
            : [];

        return [
            'tasks' => $tasks,
            'statuses' => $this->statuses(),
            'currentUser' => Auth::user(),
            'users' => \App\Models\User::all(), // Dodano przekazywanie użytkowników
            'taskableTypes' => Task::getTaskableTypeOptions(),
            'quickTaskableRecords' => Task::getTaskableRecordOptions($this->quickTaskableType),
            'editTaskableRecords' => Task::getTaskableRecordOptions($this->editModalData['taskable_type'] ?? null),
            'taskContextUrls' => $taskContextUrls,
            'taskContextTrees' => $taskContextTrees,
            'editingTaskContextUrl' => $editingTaskContextUrl,
            'editingTaskContextTree' => $editingTaskContextTree,
            'currentTaskContextUrl' => $currentTaskContextUrl,
            'currentTaskContextTree' => $currentTaskContextTree,
            'currentTaskHierarchy' => $currentTaskHierarchy,
            'calendarEvents' => $tasks
                ->filter(fn (Task $task) => !empty($task->due_date))
                ->map(function (Task $task): array {
                    return [
                        'id' => (string) $task->id,
                        'title' => $task->title,
                        'start' => optional($task->due_date)->toIso8601String(),
                        'url' => TaskResource::getUrl('edit', ['record' => $task]),
                        'backgroundColor' => match ($task->priority) {
                            'high' => '#dc2626',
                            'medium' => '#d97706',
                            'low' => '#16a34a',
                            default => '#2563eb',
                        },
                        'borderColor' => match ($task->priority) {
                            'high' => '#991b1b',
                            'medium' => '#92400e',
                            'low' => '#166534',
                            default => '#1d4ed8',
                        },
                        'textColor' => '#ffffff',
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    protected function resolveTaskContextUrl(Task $task): ?string
    {
        $context = $task->taskable;

        if (! $context) {
            return null;
        }

        return match (true) {
            $context instanceof Event => EventResource::getUrl('edit', ['record' => $context]),
            $context instanceof EventTemplate => EventTemplateResource::getUrl('edit', ['record' => $context]),
            $context instanceof EventTemplateProgramPoint => EventTemplateProgramPointResource::getUrl('edit', ['record' => $context]),
            $context instanceof Contractor => ContractorResource::getUrl('edit', ['record' => $context]),
            $context instanceof EventProgramPoint => $context->event_id
                ? EventResource::getUrl('edit-program', ['record' => $context->event_id])
                : null,
            $context instanceof EventDocument => $context->event_id
                ? EventResource::getUrl('edit', ['record' => $context->event_id])
                : null,
            $context instanceof EventSettlementCost => $context->settlement_id
                ? EventSettlementResource::getUrl('edit', ['record' => $context->settlement_id])
                : null,
            $context instanceof EventSettlementDocument => $context->settlement_id
                ? EventSettlementResource::getUrl('edit', ['record' => $context->settlement_id])
                : null,
            $context instanceof EventSettlementParticipantPayment => $context->settlement_id
                ? EventSettlementResource::getUrl('edit', ['record' => $context->settlement_id])
                : null,
            $context instanceof PilotCashPreparation => $context->settlement_id
                ? EventSettlementResource::getUrl('edit', ['record' => $context->settlement_id])
                : null,
            default => null,
        };
    }

    protected function resolveTaskContextTree(Task $task): array
    {
        $context = $task->taskable;

        if (! $context) {
            return [];
        }

        return match (true) {
            $context instanceof Event => [[
                'label' => $this->formatEventContextLabel($context),
                'url' => EventResource::getUrl('edit', ['record' => $context]),
            ]],

            $context instanceof EventProgramPoint => array_values(array_filter([
                $context->event_id ? [
                    'label' => $this->formatEventContextLabel($context->event, $context->event_id),
                    'url' => EventResource::getUrl('edit', ['record' => $context->event_id]),
                ] : null,
                $context->event_id ? [
                    'label' => 'Program imprezy',
                    'url' => EventResource::getUrl('edit-program', ['record' => $context->event_id]),
                ] : null,
                $context->event_id ? [
                    'label' => 'Punkt programu #' . $context->getKey(),
                    'url' => EventResource::getUrl('edit-program', ['record' => $context->event_id]),
                ] : null,
            ])),

            $context instanceof EventTemplate => [[
                'label' => 'Szablon: ' . ($context->name ?: ('#' . $context->getKey())),
                'url' => EventTemplateResource::getUrl('edit', ['record' => $context]),
            ]],

            $context instanceof EventTemplateProgramPoint => array_values(array_filter([
                $context->event_template_id ? [
                    'label' => 'Szablon imprezy',
                    'url' => EventTemplateResource::getUrl('edit', ['record' => $context->event_template_id]),
                ] : null,
                [
                    'label' => 'Punkt szablonu #' . $context->getKey(),
                    'url' => EventTemplateProgramPointResource::getUrl('edit', ['record' => $context]),
                ],
            ])),

            $context instanceof Contractor => [[
                'label' => 'Kontrahent: ' . ($context->name ?: ('#' . $context->getKey())),
                'url' => ContractorResource::getUrl('edit', ['record' => $context]),
            ]],

            $context instanceof EventDocument => array_values(array_filter([
                $context->event_id ? [
                    'label' => $this->formatEventContextLabel($context->event, $context->event_id),
                    'url' => EventResource::getUrl('edit', ['record' => $context->event_id]),
                ] : null,
                $context->event_id ? [
                    'label' => 'Dokument imprezy #' . $context->getKey(),
                    'url' => EventResource::getUrl('edit', ['record' => $context->event_id]),
                ] : null,
            ])),

            $context instanceof EventSettlementCost => array_values(array_filter([
                $context->settlement?->event_id ? [
                    'label' => $this->formatEventContextLabel($context->settlement?->event, $context->settlement->event_id),
                    'url' => EventResource::getUrl('edit', ['record' => $context->settlement->event_id]),
                ] : null,
                $context->settlement_id ? [
                    'label' => 'Rozliczenie #' . $context->settlement_id,
                    'url' => EventSettlementResource::getUrl('edit', ['record' => $context->settlement_id]),
                ] : null,
                $context->settlement_id ? [
                    'label' => 'Pozycja kosztu #' . $context->getKey(),
                    'url' => $this->appendQuery(
                        EventSettlementResource::getUrl('edit', ['record' => $context->settlement_id]),
                        ['activeRelationManager' => 0]
                    ),
                ] : null,
            ])),

            $context instanceof EventSettlementDocument => array_values(array_filter([
                $context->settlement?->event_id ? [
                    'label' => $this->formatEventContextLabel($context->settlement?->event, $context->settlement->event_id),
                    'url' => EventResource::getUrl('edit', ['record' => $context->settlement->event_id]),
                ] : null,
                $context->settlement_id ? [
                    'label' => 'Rozliczenie #' . $context->settlement_id,
                    'url' => EventSettlementResource::getUrl('edit', ['record' => $context->settlement_id]),
                ] : null,
                $context->settlement_id ? [
                    'label' => 'Dokument rozliczenia #' . $context->getKey(),
                    'url' => $this->appendQuery(
                        EventSettlementResource::getUrl('edit', ['record' => $context->settlement_id]),
                        ['activeRelationManager' => 1]
                    ),
                ] : null,
            ])),

            $context instanceof EventSettlementParticipantPayment => array_values(array_filter([
                $context->settlement?->event_id ? [
                    'label' => $this->formatEventContextLabel($context->settlement?->event, $context->settlement->event_id),
                    'url' => EventResource::getUrl('edit', ['record' => $context->settlement->event_id]),
                ] : null,
                $context->settlement_id ? [
                    'label' => 'Rozliczenie #' . $context->settlement_id,
                    'url' => EventSettlementResource::getUrl('edit', ['record' => $context->settlement_id]),
                ] : null,
                $context->settlement_id ? [
                    'label' => 'Wpłata uczestnika #' . $context->getKey(),
                    'url' => $this->appendQuery(
                        EventSettlementResource::getUrl('edit', ['record' => $context->settlement_id]),
                        ['activeRelationManager' => 2]
                    ),
                ] : null,
            ])),

            $context instanceof PilotCashPreparation => array_values(array_filter([
                $context->settlement?->event_id ? [
                    'label' => $this->formatEventContextLabel($context->settlement?->event, $context->settlement->event_id),
                    'url' => EventResource::getUrl('edit', ['record' => $context->settlement->event_id]),
                ] : null,
                $context->settlement_id ? [
                    'label' => 'Rozliczenie #' . $context->settlement_id,
                    'url' => EventSettlementResource::getUrl('edit', ['record' => $context->settlement_id]),
                ] : null,
                $context->settlement_id ? [
                    'label' => 'Gotówka pilota #' . $context->getKey(),
                    'url' => $this->appendQuery(
                        EventSettlementResource::getUrl('edit', ['record' => $context->settlement_id]),
                        ['activeRelationManager' => 3]
                    ),
                ] : null,
            ])),

            default => [[
                'label' => $task->task_context_label,
                'url' => $this->resolveTaskContextUrl($task),
            ]],
        };
    }

    protected function formatEventContextLabel(?Event $event, ?int $fallbackId = null): string
    {
        $eventName = $event?->name ?: ($fallbackId ? ('#' . $fallbackId) : 'Brak imprezy');
        $eventDate = $event?->start_date?->format('d.m.Y') ?? 'brak daty';
        $orderingParty = $event?->contractor?->name ?: ($event?->client_name ?: 'brak zamawiającego');

        return sprintf('Impreza: %s • %s • Zamawiający: %s', $eventName, $eventDate, $orderingParty);
    }

    protected function getTaskActivityTimestamp(Task $task): int
    {
        $timestamps = [
            $task->updated_at?->timestamp,
            $task->created_at?->timestamp,
            optional($task->comments?->max('updated_at'))->timestamp,
            optional($task->comments?->max('created_at'))->timestamp,
            optional($task->attachments?->max('updated_at'))->timestamp,
            optional($task->attachments?->max('created_at'))->timestamp,
            optional($task->subtasks?->max('updated_at'))->timestamp,
            optional($task->subtasks?->max('created_at'))->timestamp,
        ];

        return (int) max(array_filter($timestamps, fn ($value) => ! is_null($value)) ?: [0]);
    }

    protected function appendQuery(string $url, array $query): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . http_build_query($query);
    }

    public function updatedQuickTaskableType(): void
    {
        $this->quickTaskableId = null;
    }

    public function updatedEditModalDataTaskableType(): void
    {
        $this->editModalData['taskable_id'] = null;
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
        $this->showAdvancedSubtaskForm = false;
    }

    public function cancelEditSubtask()
    {
        $this->editingSubtask = false;
        $this->editSubtaskId = null;
        $this->prepareSubtaskFormDefaults();
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
        $this->currentTaskForDetails = $this->loadDetailsTask($this->currentTaskForDetails->getKey());
        $this->prepareSubtaskFormDefaults();
        \Filament\Notifications\Notification::make()
            ->title('Podzadanie zapisane')
            ->success()
            ->send();
    }

    public function openSubtaskDetails(int $taskId): void
    {
        $this->currentTaskForDetails = $this->loadDetailsTask($taskId);
        $this->prepareSubtaskFormDefaults();
    }

    public function goToParentTaskDetails(): void
    {
        if (! $this->currentTaskForDetails?->parent_id) {
            return;
        }

        $this->currentTaskForDetails = $this->loadDetailsTask($this->currentTaskForDetails->parent_id);
        $this->prepareSubtaskFormDefaults();
    }

    public function deleteSubtask(int $subtaskId): void
    {
        $subtask = Task::findOrFail($subtaskId);

        if (! $this->canDeleteTask($subtask)) {
            Notification::make()
                ->title('Brak uprawnień')
                ->body('Nie masz uprawnień do usunięcia tego podzadania.')
                ->danger()
                ->send();

            return;
        }

        $subtask->delete();

        if ($this->currentTaskForDetails) {
            $this->currentTaskForDetails = $this->loadDetailsTask($this->currentTaskForDetails->getKey());
        }

        Notification::make()
            ->title('Podzadanie usunięte')
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
            'taskable_type' => $this->currentTaskForDetails->taskable_type,
            'taskable_id' => $this->currentTaskForDetails->taskable_id,
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

    protected function prepareSubtaskFormDefaults(): void
    {
        $defaultStatusId = Task::getDefaultStatusId();

        $this->editSubtaskData = [
            'title' => '',
            'description' => '',
            'priority' => 'medium',
            'assignee_id' => $this->currentTaskForDetails?->assignee_id,
            'status_id' => $defaultStatusId,
            'due_date' => null,
        ];
    }

    protected function loadDetailsTask(int|string $taskId): Task
    {
        return Task::with([
            'parent:id,title,parent_id',
            'subtasks' => fn ($query) => $query
                ->with(['status:id,name', 'assignee:id,name'])
                ->withCount(['subtasks', 'comments', 'attachments'])
                ->orderByDesc('updated_at'),
            'comments.author:id,name',
            'attachments.user:id,name',
            'status:id,name',
            'assignee:id,name',
            'taskable',
        ])->findOrFail($taskId);
    }

    protected function resolveTaskHierarchy(Task $task): array
    {
        $chain = [];
        $current = $task;
        $guard = 0;

        while ($current && $guard < 20) {
            $chain[] = [
                'id' => $current->getKey(),
                'title' => $current->title ?: ('Zadanie #' . $current->getKey()),
            ];

            if (! $current->parent_id) {
                break;
            }

            $current = Task::query()
                ->select(['id', 'title', 'parent_id'])
                ->find($current->parent_id);

            $guard++;
        }

        return array_reverse($chain);
    }

    protected function sanitizeTaskData(array $data): array
    {
        $data = Arr::only($data, [
            'title',
            'description',
            'priority',
            'status_id',
            'author_id',
            'assignee_id',
            'parent_id',
            'due_date',
            'order',
            'taskable_type',
            'taskable_id',
        ]);

        $data['assignee_id'] = filled($data['assignee_id'] ?? null) ? $data['assignee_id'] : null;
        $data['due_date'] = filled($data['due_date'] ?? null) ? $data['due_date'] : null;

        if (! filled($data['taskable_type'] ?? null) || ! filled($data['taskable_id'] ?? null)) {
            $data['taskable_type'] = null;
            $data['taskable_id'] = null;
        }

        return $data;
    }
}
