<?php

namespace App\Filament\Resources\TaskResource\RelationManagers;

use App\Filament\Concerns\InteractsWithTaskEditModal;
use App\Filament\Concerns\InteractsWithTaskListQuickActions;
use App\Filament\Concerns\InteractsWithTaskOwnershipScope;
use App\Filament\Resources\TaskResource;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\Reservation;
use App\Models\Task;
use App\Support\Tasks\TaskQueryFilters;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class TasksRelationManager extends RelationManager
{
    use InteractsWithTaskEditModal;
    use InteractsWithTaskListQuickActions;
    use InteractsWithTaskOwnershipScope;

    /**
     * Lazy RM ładuje się przez /livewire/update bez query stringa — deep link ?editTask=
     * wtedy nie otwiera modala. Eager mount zachowuje request()->query().
     */
    protected static bool $isLazy = false;

    protected static string $relationship = 'tasks';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $title = 'Zadania';

    public function mount(): void
    {
        parent::mount();

        $this->tasksScope = $this->defaultTasksScope();
        $this->showFinishedTasks = $this->defaultShowFinishedTasks();
        $this->restoreTaskQuickFiltersFromSession();
    }

    protected function defaultTasksScope(): string
    {
        return 'all';
    }

    protected function defaultShowFinishedTasks(): bool
    {
        return true;
    }

    protected function taskQuickFiltersSessionKey(): ?string
    {
        return 'tasks.event.filters.'.(auth()->id() ?? 'guest');
    }

    protected function taskQuickFiltersPreferenceKey(): ?string
    {
        return 'task_filters.event';
    }

    public function table(Table $table): Table
    {
        return $table
            ->header(fn (): \Illuminate\Contracts\View\View => view('filament.tasks.ownership-quick-filters', [
                'tasksScope' => $this->tasksScope,
                'dueFilter' => $this->dueFilter,
                'tasksOnlyUrgent' => $this->tasksOnlyUrgent,
                'showFinishedTasks' => $this->showFinishedTasks,
                'sourceFilter' => $this->sourceFilter,
                'showSource' => true,
                'showFinishedToggle' => true,
                'hasActive' => $this->hasActiveTaskQuickFilters(),
            ]))
            ->modifyQueryUsing(function (Builder $query): Builder {
                TaskQueryFilters::applyDefaultListScopes($query, officeOnly: true);
                TaskQueryFilters::withLatestActivityAtColumn($query);
                $this->applyTaskQuickFiltersTo($query, applyFinished: true, applySource: true);

                return $query;
            })
            ->defaultSort(
                fn (Builder $query, string $direction): Builder => TaskQueryFilters::orderByLatestActivity($query, $direction),
                'desc',
            )
            ->columns(TaskResource::eventWorkspaceTableColumns())
            ->searchable()
            ->recordUrl(null)
            ->recordAction(null)
            ->actionsColumnLabel('Akcje')
            ->actions([
                TaskResource::modalEditTableAction(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('createTask')
                    ->label('Nowe zadanie')
                    ->icon('heroicon-m-plus')
                    ->action(fn () => $this->mountAction('createTask')),
            ])
            ->emptyStateHeading('Brak zadań')
            ->emptyStateDescription('Dodaj pierwsze zadanie powiązane z tą imprezą, punktem programu lub rezerwacją.')
            ->emptyStateActions([
                Tables\Actions\Action::make('createTaskEmpty')
                    ->label('Nowe zadanie')
                    ->icon('heroicon-m-plus')
                    ->action(fn () => $this->mountAction('createTask')),
            ]);
    }

    /**
     * Zadania imprezy + punkty programu + rezerwacje tej imprezy.
     */
    protected function getTableQuery(): Builder|Relation|null
    {
        $owner = $this->getOwnerRecord();

        if (! $owner instanceof Event) {
            return parent::getTableQuery();
        }

        $pointIds = $owner->programPoints()->pluck('id');
        $reservationIds = $owner->reservations()->pluck('id');

        return Task::query()
            ->where(function (Builder $query) use ($owner, $pointIds, $reservationIds): void {
                $query->where(function (Builder $inner) use ($owner): void {
                    $inner->where('taskable_type', Event::class)
                        ->where('taskable_id', $owner->getKey());
                })->orWhere(function (Builder $inner) use ($pointIds): void {
                    $inner->where('taskable_type', EventProgramPoint::class)
                        ->whereIn('taskable_id', $pointIds);
                })->orWhere(function (Builder $inner) use ($reservationIds): void {
                    $inner->where('taskable_type', Reservation::class)
                        ->whereIn('taskable_id', $reservationIds);
                });
            });
    }

    /**
     * @return array<string, mixed>
     */
    protected function createTaskDefaultFormData(): array
    {
        $owner = $this->getOwnerRecord();

        return [
            'taskable_type' => $owner::class,
            'taskable_id' => $owner->getKey(),
        ];
    }

    protected function afterTaskModalSaved(Task $task): void
    {
        $this->resetTable();
    }

    protected function afterTasksScopeChanged(): void
    {
        $this->resetTable();
    }
}
