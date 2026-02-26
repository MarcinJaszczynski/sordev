<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Mokhosh\FilamentKanban\Pages\KanbanBoard;
use Filament\Notifications\Notification;

class TasksKanbanBoard extends KanbanBoard
{
    protected static string $resource = TaskResource::class;
    protected static string $model = Task::class;
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationLabel = 'Kanban - Zadania';
    protected static ?string $title = 'Kanban - Zarządzanie zadaniami';
    protected static ?string $slug = '/kanban';

    // Właściwości filtrowania
    public $filterBy = '';
    public $priorityFilter = '';
    public $searchTerm = '';

    // Konfiguracja edycji modala
    protected string $editModalTitle = 'Edytuj zadanie';
    protected string $editModalWidth = '4xl';
    protected string $editModalSaveButtonLabel = 'Zapisz zmiany';
    protected string $editModalCancelButtonLabel = 'Anuluj';
    protected bool $editModalSlideOver = false;

    // Właściwości modelu
    protected static string $recordTitleAttribute = 'title';
    protected static string $recordStatusAttribute = 'status_id';

    public function mount(): void
    {
        // Authorize access to the resource
        static::authorizeResourceAccess();
    }

    protected function statuses(): \Illuminate\Support\Collection
    {
        return TaskStatus::orderBy('order')->get()->map(function ($status) {
            return [
                'id' => $status->id,
                'title' => $status->name,
                'color' => $status->color ?: '#6B7280',
            ];
        });
    }

    protected function records(): \Illuminate\Support\Collection
    {
        $query = Task::with(['status', 'assignee', 'author', 'subtasks', 'attachments', 'comments'])
            ->orderBy('order');

        // Apply filters based on component state
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

    protected function getEditModalFormSchema(null|int|string $recordId): array
    {
        return [
            TextInput::make('title')
                ->label('Tytuł zadania')
                ->required()
                ->maxLength(255),

            Textarea::make('description')
                ->label('Opis')
                ->rows(3)
                ->columnSpanFull(),

            Select::make('priority')
                ->label('Priorytet')
                ->options([
                    'low' => '🟢 Niski',
                    'medium' => '🟡 Średni',
                    'high' => '🔴 Wysoki',
                ])
                ->required(),

            Select::make('assignee_id')
                ->label('Osoba przypisana')
                ->relationship('assignee', 'name')
                ->searchable()
                ->preload(),

            DateTimePicker::make('due_date')
                ->label('Termin wykonania')
                ->native(false)
                ->displayFormat('d.m.Y H:i'),

            Select::make('status_id')
                ->label('Status')
                ->relationship('status', 'name')
                ->required(),
        ];
    }

    protected function editRecord(int|string $recordId, array $data, array $state): void
    {
        try {
            $task = Task::findOrFail($recordId);

            // Security check
            if (!$this->canModifyTask($task)) {
                Notification::make()
                    ->title('Brak uprawnień')
                    ->body('Nie masz uprawnień do edycji tego zadania.')
                    ->danger()
                    ->send();
                return;
            }

            $task->update($data);

            Notification::make()
                ->title('Zadanie zaktualizowane')
                ->body('Zmiany zostały pomyślnie zapisane.')
                ->success()
                ->send();

            Log::info("Task {$task->id} updated by user " . Auth::id(), [
                'changes' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating task in Kanban: ' . $e->getMessage());

            Notification::make()
                ->title('Błąd podczas aktualizacji')
                ->body('Wystąpił błąd podczas zapisywania zmian.')
                ->danger()
                ->send();
        }
    }

    public function onStatusChanged(int|string $recordId, string $status, array $fromOrderedIds, array $toOrderedIds): void
    {
        try {
            $task = Task::findOrFail($recordId);

            if (!$this->canModifyTask($task)) {
                return;
            }

            $oldStatusId = $task->status_id;
            $task->status_id = $status;
            $task->save();

            // Update order for all tasks in the target status
            foreach ($toOrderedIds as $order => $taskId) {
                Task::where('id', $taskId)->update(['order' => $order]);
            }

            // Log the status change
            if ($oldStatusId != $status) {
                $oldStatus = TaskStatus::find($oldStatusId);
                $newStatus = TaskStatus::find($status);

                Log::info("Task {$task->id} moved from {$oldStatus?->name} to {$newStatus?->name} by user " . Auth::id());

                Notification::make()
                    ->title('Status zadania zmieniony')
                    ->body("Zadanie przeniesiono do kolumny: {$newStatus?->name}")
                    ->success()
                    ->send();
            }
        } catch (\Exception $e) {
            Log::error('Error changing task status in Kanban: ' . $e->getMessage());

            Notification::make()
                ->title('Błąd podczas przenoszenia')
                ->body('Wystąpił błąd podczas zmiany statusu zadania.')
                ->danger()
                ->send();
        }
    }

    public function onSortChanged(int|string $recordId, string $status, array $orderedIds): void
    {
        try {
            $task = Task::findOrFail($recordId);

            if (!$this->canModifyTask($task)) {
                return;
            }

            // Update order for all tasks in the status
            foreach ($orderedIds as $order => $taskId) {
                Task::where('id', $taskId)->update(['order' => $order]);
            }
        } catch (\Exception $e) {
            Log::error('Error updating task order in Kanban: ' . $e->getMessage());
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

            Log::info("Task {$task->id} deleted by user " . Auth::id());
        } catch (\Exception $e) {
            Log::error('Error deleting task in Kanban: ' . $e->getMessage());

            Notification::make()
                ->title('Błąd podczas usuwania')
                ->body('Wystąpił błąd podczas usuwania zadania.')
                ->danger()
                ->send();
        }
    }

    public function refreshBoard()
    {
        $this->reset(['filterBy', 'priorityFilter', 'searchTerm']);

        Notification::make()
            ->title('Tablica odświeżona')
            ->body('Filtry zostały zresetowane.')
            ->success()
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

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('create')
                ->label('Dodaj zadanie')
                ->icon('heroicon-m-plus')
                ->color('primary')
                ->url(TaskResource::getUrl('create')),

            \Filament\Actions\Action::make('list')
                ->label('Widok listy')
                ->icon('heroicon-m-list-bullet')
                ->color('gray')
                ->url(TaskResource::getUrl('index')),

            \Filament\Actions\Action::make('refresh')
                ->label('Odśwież')
                ->icon('heroicon-m-arrow-path')
                ->color('gray')
                ->action(fn() => $this->refreshBoard()),
        ];
    }
}
