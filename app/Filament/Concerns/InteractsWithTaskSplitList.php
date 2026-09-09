<?php

namespace App\Filament\Concerns;

use App\Models\Task;
use App\Support\Tasks\TaskAuthorization;
use App\Support\Tasks\TaskQueryFilters;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Split-view listy zadań: kompaktowa lista + edytor w panelu bocznym.
 * Wspólne dla ListTasks i TasksRelationManager.
 */
trait InteractsWithTaskSplitList
{
    public ?int $selectedTaskId = null;

    public ?int $selectedTaskActiveRelationManager = null;

    public string $listSort = 'activity_desc';

    /**
     * @return array<string, string>
     */
    public function listSortOptions(): array
    {
        return [
            'activity_desc' => 'Ostatnia aktywność',
            'due_asc' => 'Termin (najbliższy)',
            'due_desc' => 'Termin (najdalszy)',
            'title_asc' => 'Tytuł A–Z',
            'modified_desc' => 'Ostatnia modyfikacja',
        ];
    }

    public function updatedListSort(): void
    {
        if (! in_array($this->listSort, array_keys($this->listSortOptions()), true)) {
            $this->listSort = 'activity_desc';
        }

        $this->persistTaskQuickFilters();

        if (method_exists($this, 'resetTable')) {
            $this->resetTable();
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraTaskQuickFiltersState(): array
    {
        return [
            'listSort' => $this->listSort,
        ];
    }

    /**
     * @param  array<string, mixed>  $saved
     */
    protected function applyRestoredExtraTaskQuickFilters(array $saved): void
    {
        if (isset($saved['listSort']) && is_string($saved['listSort'])
            && array_key_exists($saved['listSort'], $this->listSortOptions())) {
            $this->listSort = $saved['listSort'];
        }
    }

    public function selectTask(int $taskId, ?int $activeRelationManager = null): void
    {
        if ($taskId <= 0) {
            return;
        }

        $this->selectedTaskId = $taskId;
        $this->selectedTaskActiveRelationManager = $activeRelationManager;

        if (method_exists($this, 'markOpenedTaskNotificationsAsRead')) {
            $this->markOpenedTaskNotificationsAsRead($taskId);
        }

        if (method_exists($this, 'resetTable')) {
            $this->resetTable();
        }

        // Backup na wypadek, gdy Alpine $watch nie złapie zmiany (morph / timing).
        $this->js(<<<'JS'
            setTimeout(() => window.scrollTasksSplitEditorIntoView?.(), 120);
            setTimeout(() => window.scrollTasksSplitEditorIntoView?.(), 350);
        JS);
    }

    public function openEditTaskModal(int $taskId, ?int $activeRelationManager = null): void
    {
        $this->selectTask($taskId, $activeRelationManager);
    }

    public function clearSelectedTask(): void
    {
        $this->selectedTaskId = null;
        $this->selectedTaskActiveRelationManager = null;

        if (method_exists($this, 'resetTable')) {
            $this->resetTable();
        }
    }

    public function selectedTaskRecord(): ?Task
    {
        if (! $this->selectedTaskId) {
            return null;
        }

        return Task::query()->find($this->selectedTaskId);
    }

    public function deleteSelectedTask(): void
    {
        $task = $this->selectedTaskRecord();

        if (! $task || ! TaskAuthorization::canDelete(Auth::user(), $task)) {
            Notification::make()
                ->title('Nie możesz usunąć tego zadania')
                ->danger()
                ->send();

            return;
        }

        $task->delete();
        $this->clearSelectedTask();

        Notification::make()
            ->title('Zadanie usunięte')
            ->success()
            ->send();
    }

    /**
     * @return array<int, Tables\Columns\Column>
     */
    protected function splitViewTableColumns(): array
    {
        return [
            Tables\Columns\ViewColumn::make('task_summary')
                ->label('Zadanie')
                ->searchable(['title', 'description'])
                ->view('filament.tasks.list-task-split-row')
                ->extraHeaderAttributes(['class' => 'fi-ta-col-task-summary'])
                ->extraCellAttributes(['class' => 'fi-ta-col-task-summary']),
        ];
    }

    protected function configureTaskSplitTable(Table $table): Table
    {
        return $table
            ->columns($this->splitViewTableColumns())
            ->actions([])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make(\App\Filament\Resources\TaskResource::tableBulkActions()),
            ])
            ->selectable(true)
            ->recordUrl(null)
            ->recordAction(null)
            ->defaultSort(null);
    }

    protected function applySplitListSort(Builder $query): Builder
    {
        $query->reorder();
        $this->pinSelectedTaskFirst($query);

        return match ($this->listSort) {
            'due_asc' => $query
                ->orderByRaw('due_date IS NULL')
                ->orderBy('due_date', 'asc')
                ->orderBy('id', 'desc'),
            'due_desc' => $query
                ->orderByRaw('due_date IS NULL')
                ->orderBy('due_date', 'desc')
                ->orderBy('id', 'desc'),
            'title_asc' => $query
                ->orderBy('title')
                ->orderBy('id', 'desc'),
            'modified_desc' => $query
                ->orderByRaw('COALESCE(updated_at, created_at) DESC')
                ->orderBy('id', 'desc'),
            default => TaskQueryFilters::orderByLatestActivity($query, 'desc'),
        };
    }

    protected function pinSelectedTaskFirst(Builder $query): Builder
    {
        $selectedId = (int) ($this->selectedTaskId ?? 0);

        if ($selectedId <= 0) {
            return $query;
        }

        return $query->orderByRaw('CASE WHEN tasks.id = ? THEN 0 ELSE 1 END', [$selectedId]);
    }
}
