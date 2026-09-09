<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Concerns\InteractsWithTaskEditModal;
use App\Filament\Concerns\InteractsWithTaskListQuickActions;
use App\Filament\Concerns\InteractsWithTaskOwnershipScope;
use App\Filament\Concerns\InteractsWithTaskSplitList;
use App\Filament\Concerns\MarksTaskInboxAsSeen;
use App\Filament\Resources\TaskResource;
use App\Models\Task;
use App\Support\Tasks\TaskQueryFilters;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListTasks extends ListRecords
{
    use InteractsWithTaskEditModal;
    use InteractsWithTaskListQuickActions;
    use InteractsWithTaskOwnershipScope;
    use InteractsWithTaskSplitList {
        InteractsWithTaskSplitList::extraTaskQuickFiltersState insteadof InteractsWithTaskOwnershipScope;
        InteractsWithTaskSplitList::applyRestoredExtraTaskQuickFilters insteadof InteractsWithTaskOwnershipScope;
        InteractsWithTaskSplitList::openEditTaskModal insteadof InteractsWithTaskEditModal;
    }
    use MarksTaskInboxAsSeen;

    protected static string $resource = TaskResource::class;

    protected static string $view = 'filament.resources.task-resource.pages.list-tasks';

    public function getSubheading(): ?string
    {
        return 'Skrzynka cross-event — zadania jednej imprezy: zakładka Zadania na karcie imprezy.';
    }

    public function mount(): void
    {
        if (method_exists(get_parent_class($this), 'mount')) {
            parent::mount();
        }

        $this->restoreTaskQuickFiltersFromSession();

        $deepLinkTaskId = (int) request()->query('editTask', 0);
        if ($deepLinkTaskId > 0) {
            $this->selectedTaskId = $deepLinkTaskId;
            $this->selectedTaskActiveRelationManager = request()->has('activeRelationManager')
                ? (int) request()->query('activeRelationManager')
                : null;
        }
    }

    protected function taskQuickFiltersSessionKey(): ?string
    {
        return 'tasks.list.filters.'.(auth()->id() ?? 'guest');
    }

    protected function taskQuickFiltersPreferenceKey(): ?string
    {
        return 'task_filters.list';
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'active';
    }

    public function getTabs(): array
    {
        return [
            'active' => Tab::make('Aktywne')
                ->icon('heroicon-o-user')
                ->modifyQueryUsing(fn (Builder $query): Builder => TaskQueryFilters::excludeFinished($query)),
            'new' => Tab::make('Nowe (do zrobienia)')
                ->icon('heroicon-o-sparkles')
                ->modifyQueryUsing(fn (Builder $query): Builder => TaskQueryFilters::openTodo($query)),
            'finished' => Tab::make('Zakończone')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(fn (Builder $query): Builder => TaskQueryFilters::onlyFinished($query)),
            'manual' => Tab::make('Kolejność ręczna')
                ->icon('heroicon-o-bars-3'),
            'all' => Tab::make('Wszystkie statusy')
                ->icon('heroicon-o-queue-list'),
        ];
    }

    public function table(Table $table): Table
    {
        $table = parent::table($table);

        $table = $this->configureTaskSplitTable($table)
            ->modifyQueryUsing(function (Builder $query): Builder {
                $query = $this->applyTaskQuickFiltersTo($query, applyFinished: false, applySource: true);

                if ($this->activeTab === 'manual') {
                    return $this->pinSelectedTaskFirst($query);
                }

                return $this->applySplitListSort($query);
            });

        if ($this->activeTab === 'manual') {
            return $table
                ->reorderable('order')
                ->defaultSort('order');
        }

        return $table;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->makeCreateTaskAction(
                defaultDueDate: fn (): mixed => $this->createTaskDefaultDueDate(),
                defaultFormData: fn (): array => array_merge(
                    $this->createTaskDefaultFormData(),
                    $this->pendingCreateFormData,
                ),
            ),
            Actions\Action::make('board')
                ->label('Widok tablicy')
                ->icon('heroicon-m-view-columns')
                ->color('gray')
                ->tooltip('Tablica kolumnowa statusów zadań — wygodna do codziennej pracy.')
                ->url(TaskResource::getUrl('board')),
        ];
    }

    protected function afterTaskModalSaved(Task $task): void
    {
        $this->selectedTaskId = $task->id;
        $this->resetTable();
    }

    protected function afterTaskListQuickActionSaved(): void
    {
        if (! method_exists($this, 'resetTable')) {
            return;
        }

        if (app()->runningUnitTests()) {
            $this->resetTable();

            return;
        }

        $this->js('queueMicrotask(() => $wire.call("resetTable"))');
    }

    protected function afterTasksScopeChanged(): void
    {
        $this->resetTable();
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

        $this->selectTask($taskId, $activeRelationManager);
    }
}
