<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Enums\TaskPriority;
use App\Filament\Concerns\InteractsWithTaskEditModal;
use App\Filament\Concerns\InteractsWithTaskOwnershipScope;
use App\Filament\Concerns\MarksTaskInboxAsSeen;
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
use App\Models\User;
use Filament\Actions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use App\Support\Tasks\TaskAuthorization;
use App\Support\Tasks\TaskQueryFilters;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;

class TasksKanbanBoardPage extends Page implements HasForms
{
    use InteractsWithForms;
    use InteractsWithTaskEditModal;
    use InteractsWithTaskOwnershipScope;
    use MarksTaskInboxAsSeen;

    protected static string $resource = TaskResource::class;

    protected static string $view = 'filament.resources.task-resource.pages.tasks-kanban-board-page';

    protected static ?string $slug = 'board';

    protected static ?string $title = 'Kanban - Zarządzanie zadaniami';

    protected ?string $maxContentWidth = 'full';

    // Właściwości filtrowania
    public $priorityFilter = '';

    public $contextFilter = '';

    public $searchTerm = '';

    public $dueFilter = '';

    public bool $showFinishedTasks = false;

    public ?int $eventFilter = null;

    // Sortowanie kolumn
    public $columnSorts = [];

    // Ukryte kolumny (tablica ID statusów)
    public array $hiddenColumns = [];

    // Quick add task
    public $showingQuickAdd = false;

    public $quickAddStatusId = null;

    public $quickTaskTitle = '';

    public $quickTaskDescription = '';

    public $quickTaskPriority = 'normal';

    public $quickTaskAssigneeId = null;

    public $quickTaskDueDate = null;

    public $quickTaskableType = null;

    public $quickTaskableId = null;

    public function mount(): void
    {
        static::authorizeResourceAccess();

        $this->eventFilter = request()->integer('event') ?: null;

        if ($this->eventFilter) {
            $this->quickTaskableType = Event::class;
            $this->quickTaskableId = $this->eventFilter;
        }

        $this->mountInteractsWithTaskEditModal();
    }

    protected function getHeaderActions(): array
    {
        $actions = [];

        if ($this->eventFilter) {
            $actions[] = Actions\Action::make('back_to_event_tasks')
                ->label('Zadania imprezy')
                ->icon('heroicon-m-arrow-left')
                ->color('gray')
                ->url(EventResource::getUrl('tasks', ['record' => $this->eventFilter]));
        }

        $actions[] = Actions\Action::make('list')
            ->label('Widok Listy')
            ->icon('heroicon-m-list-bullet')
            ->color('gray')
            ->url(TaskResource::getUrl('index'));

        $actions[] = Actions\Action::make('createTask')
            ->label('Dodaj zadanie')
            ->icon('heroicon-m-plus')
            ->action(fn () => $this->mountAction('createTask'));

        return $actions;
    }

    /**
     * @return array<string, mixed>
     */
    protected function createTaskDefaultFormData(): array
    {
        return $this->eventFilter
            ? ['taskable_type' => Event::class, 'taskable_id' => $this->eventFilter]
            : [];
    }

    protected function afterTaskModalSaved(Task $task): void
    {
        unset($this->tasks);
    }

    #[Computed]
    public function tasks()
    {
        $query = Task::query()
            ->with(['status', 'assignee', 'author', 'parent', 'subtasks', 'attachments', 'comments.author', 'taskable']);

        TaskQueryFilters::officeOnly($query);
        TaskQueryFilters::excludeArchived($query);

        if ($this->eventFilter) {
            $query->where('taskable_type', Event::class)
                ->where('taskable_id', $this->eventFilter);
        }

        if (! $this->showFinishedTasks) {
            TaskQueryFilters::excludeFinished($query);
        }

        $this->applyTasksScopeTo($query);

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
                $q->where('title', 'like', '%'.$this->searchTerm.'%')
                    ->orWhere('description', 'like', '%'.$this->searchTerm.'%');
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
                        $statusTasks = $statusTasks->sortByDesc(fn (Task $task) => TaskPriority::sortWeight($task->priority));
                        break;
                    case 'priority_asc':
                        $statusTasks = $statusTasks->sortBy(fn (Task $task) => TaskPriority::sortWeight($task->priority));
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
                        $statusTasks = $statusTasks->sortByDesc('created_at');
                        break;
                }
            } else {
                $statusTasks = $statusTasks->sortByDesc('created_at');
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

    public function updateTaskStatus(int|string $taskId, int|string $statusId, int|string|null $order = null): void
    {
        try {
            $validated = validator([
                'task_id' => $taskId,
                'status_id' => $statusId,
                'order' => $order,
            ], [
                'task_id' => 'required|integer|exists:tasks,id',
                'status_id' => 'required|integer|exists:task_statuses,id',
                'order' => 'nullable|integer|min:0',
            ])->validate();

            $task = Task::findOrFail($validated['task_id']);

            if (! $this->canModifyTask($task)) {
                Notification::make()
                    ->title('Brak uprawnień')
                    ->body('Nie możesz zmienić statusu tego zadania.')
                    ->warning()
                    ->send();

                return;
            }

            $oldStatusId = $task->status_id;
            $task->status_id = (int) $validated['status_id'];

            if (array_key_exists('order', $validated) && $validated['order'] !== null) {
                $task->order = (int) $validated['order'];
            }

            $task->save();

            unset($this->tasks);

            if ($oldStatusId != $task->status_id) {
                $newStatus = TaskStatus::find($task->status_id);

                Notification::make()
                    ->title('Status zadania zmieniony')
                    ->body("Zadanie przeniesiono do kolumny: {$newStatus?->name}")
                    ->success()
                    ->send();
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            Notification::make()
                ->title('Nieprawidłowy status')
                ->body('Wybrane zadanie lub status nie istnieje.')
                ->danger()
                ->send();
        } catch (\Exception $e) {
            Log::error('Error updating task status: '.$e->getMessage());

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

            if (! $this->canDeleteTask($task)) {
                Notification::make()
                    ->title('Brak uprawnień')
                    ->body('Nie masz uprawnień do usunięcia tego zadania.')
                    ->danger()
                    ->send();

                return;
            }

            $task->delete();

            unset($this->tasks);

            Notification::make()
                ->title('Zadanie usunięte')
                ->body('Zadanie zostało pomyślnie usunięte.')
                ->success()
                ->send();

        } catch (\Exception $e) {
            Log::error('Error deleting task: '.$e->getMessage());

            Notification::make()
                ->title('Błąd podczas usuwania')
                ->body('Wystąpił błąd podczas usuwania zadania.')
                ->danger()
                ->send();
        }
    }

    public function refreshBoard()
    {
        $this->tasksScope = 'assigned';
        $this->showFinishedTasks = false;
        $this->reset(['priorityFilter', 'contextFilter', 'searchTerm', 'dueFilter', 'columnSorts']);

        // Clear computed properties
        unset($this->tasks);
        unset($this->boardStats);

        Notification::make()
            ->title('Tablica odświeżona')
            ->body('Filtry i sortowanie zostały zresetowane.')
            ->success()
            ->send();
    }

    public function applyQuickFilter(string $filter): void
    {
        $this->reset(['priorityFilter', 'contextFilter', 'searchTerm', 'dueFilter']);

        match ($filter) {
            'high_priority' => $this->priorityFilter = TaskPriority::Urgent->value,
            'overdue' => $this->dueFilter = 'overdue',
            default => null,
        };

        unset($this->tasks);
        unset($this->boardStats);
    }

    protected function afterTasksScopeChanged(): void
    {
        unset($this->tasks);
        unset($this->boardStats);
    }

    #[Computed]
    public function boardStats(): array
    {
        $baseQuery = Task::query();
        TaskQueryFilters::officeOnly($baseQuery);
        TaskQueryFilters::excludeArchived($baseQuery);

        if ($this->eventFilter) {
            $baseQuery->where('taskable_type', Event::class)
                ->where('taskable_id', $this->eventFilter);
        }

        if (! $this->showFinishedTasks) {
            TaskQueryFilters::excludeFinished($baseQuery);
        }

        $this->applyTasksScopeTo($baseQuery);

        $tasks = $baseQuery->get();

        return [
            'total' => $tasks->count(),
            'assigned_to_me' => $tasks->where('assignee_id', Auth::id())->count(),
            'urgent' => $tasks->where('priority', TaskPriority::Urgent->value)->count(),
            'overdue' => $tasks->filter(fn (Task $task): bool => $task->due_date && $task->due_date->isPast())->count(),
        ];
    }

    public function updatedSearchTerm(): void
    {
        unset($this->tasks);
    }

    public function updatedPriorityFilter(): void
    {
        unset($this->tasks);
    }

    public function updatedContextFilter(): void
    {
        unset($this->tasks);
    }

    public function updatedShowFinishedTasks(): void
    {
        unset($this->tasks);
        unset($this->boardStats);
    }

    public function updatedDueFilter(): void
    {
        unset($this->tasks);
    }

    public function toggleColumn(int $statusId): void
    {
        if (in_array($statusId, $this->hiddenColumns)) {
            $this->hiddenColumns = array_values(array_filter($this->hiddenColumns, fn ($id) => $id !== $statusId));
        } else {
            $this->hiddenColumns[] = $statusId;
        }
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
            ->body("Kolumna '{$status->name}' posortowana według: ".($sortNames[$sortType] ?? $sortType))
            ->success()
            ->send();
    }

    public function openQuickAddModal($statusId = null, $selectedDate = null)
    {
        $this->quickAddStatusId = $statusId ?: Task::getDefaultStatusId();
        $this->reset([
            'quickTaskTitle',
            'quickTaskDescription',
            'quickTaskPriority',
            'quickTaskAssigneeId',
            'quickTaskDueDate',
            'quickTaskableType',
            'quickTaskableId',
        ]);
        $this->quickTaskPriority = TaskPriority::Normal->value;

        if (filled($selectedDate)) {
            try {
                $selectedDateTime = Carbon::parse($selectedDate);

                if (mb_strlen((string) $selectedDate) <= 10) {
                    $selectedDateTime->setTime(9, 0);
                }

                $this->quickTaskDueDate = $selectedDateTime->format('Y-m-d\TH:i');
            } catch (\Throwable $exception) {
                $this->quickTaskDueDate = null;
            }
        }

        $this->showingQuickAdd = true;

        $this->dispatch('open-modal', id: 'quick-add-modal');
    }

    public function createQuickTask()
    {
        $this->validate([
            'quickTaskTitle' => 'required|string|max:255',
            'quickTaskDescription' => 'nullable|string',
            'quickTaskPriority' => 'required|in:'.TaskPriority::Normal->value.','.TaskPriority::Urgent->value,
            'quickTaskAssigneeId' => 'nullable|exists:users,id',
            'quickTaskDueDate' => 'nullable|date',
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
            'due_date' => $this->quickTaskDueDate,
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
            Log::error('Error creating quick task: '.$e->getMessage());

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

    protected function canModifyTask(Task $task): bool
    {
        $user = Auth::user();

        if ($user?->hasRole(['admin', 'super_admin'])) {
            return true;
        }

        return $task->author_id === $user?->id || $task->assignee_id === $user?->id;
    }

    protected function canDeleteTask(Task $task): bool
    {
        return TaskAuthorization::canDelete(Auth::user(), $task);
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

        return [
            'tasks' => $tasks,
            'statuses' => $this->statuses(),
            'boardStats' => $this->boardStats(),
            'currentUser' => Auth::user(),
            'users' => \App\Models\User::all(),
            'taskableTypes' => Task::getTaskableTypeOptions(),
            'quickTaskableRecords' => Task::getTaskableRecordOptions($this->quickTaskableType),
            'taskContextUrls' => $taskContextUrls,
            'taskContextTrees' => $taskContextTrees,
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
                    'label' => 'Punkt programu #'.$context->getKey(),
                    'url' => EventResource::getUrl('edit-program', ['record' => $context->event_id]),
                ] : null,
            ])),

            $context instanceof EventTemplate => [[
                'label' => 'Szablon: '.($context->name ?: ('#'.$context->getKey())),
                'url' => EventTemplateResource::getUrl('edit', ['record' => $context]),
            ]],

            $context instanceof EventTemplateProgramPoint => array_values(array_filter([
                $context->event_template_id ? [
                    'label' => 'Szablon imprezy',
                    'url' => EventTemplateResource::getUrl('edit', ['record' => $context->event_template_id]),
                ] : null,
                [
                    'label' => 'Punkt szablonu #'.$context->getKey(),
                    'url' => EventTemplateProgramPointResource::getUrl('edit', ['record' => $context]),
                ],
            ])),

            $context instanceof Contractor => [[
                'label' => 'Kontrahent: '.($context->name ?: ('#'.$context->getKey())),
                'url' => ContractorResource::getUrl('edit', ['record' => $context]),
            ]],

            $context instanceof EventDocument => array_values(array_filter([
                $context->event_id ? [
                    'label' => $this->formatEventContextLabel($context->event, $context->event_id),
                    'url' => EventResource::getUrl('edit', ['record' => $context->event_id]),
                ] : null,
                $context->event_id ? [
                    'label' => 'Dokument imprezy #'.$context->getKey(),
                    'url' => EventResource::getUrl('edit', ['record' => $context->event_id]),
                ] : null,
            ])),

            $context instanceof EventSettlementCost => array_values(array_filter([
                $context->settlement?->event_id ? [
                    'label' => $this->formatEventContextLabel($context->settlement?->event, $context->settlement->event_id),
                    'url' => EventResource::getUrl('edit', ['record' => $context->settlement->event_id]),
                ] : null,
                $context->settlement_id ? [
                    'label' => 'Rozliczenie #'.$context->settlement_id,
                    'url' => EventSettlementResource::getUrl('edit', ['record' => $context->settlement_id]),
                ] : null,
                $context->settlement_id ? [
                    'label' => 'Pozycja kosztu #'.$context->getKey(),
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
                    'label' => 'Rozliczenie #'.$context->settlement_id,
                    'url' => EventSettlementResource::getUrl('edit', ['record' => $context->settlement_id]),
                ] : null,
                $context->settlement_id ? [
                    'label' => 'Dokument rozliczenia #'.$context->getKey(),
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
                    'label' => 'Rozliczenie #'.$context->settlement_id,
                    'url' => EventSettlementResource::getUrl('edit', ['record' => $context->settlement_id]),
                ] : null,
                $context->settlement_id ? [
                    'label' => 'Wpłata uczestnika #'.$context->getKey(),
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
                    'label' => 'Rozliczenie #'.$context->settlement_id,
                    'url' => EventSettlementResource::getUrl('edit', ['record' => $context->settlement_id]),
                ] : null,
                $context->settlement_id ? [
                    'label' => 'Gotówka pilota #'.$context->getKey(),
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
        $eventName = $event?->name ?: ($fallbackId ? ('#'.$fallbackId) : 'Brak imprezy');
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

        return $url.$separator.http_build_query($query);
    }

    public function updatedQuickTaskableType(): void
    {
        $this->quickTaskableId = null;
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
