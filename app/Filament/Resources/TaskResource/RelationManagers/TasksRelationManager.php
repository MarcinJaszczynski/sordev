<?php

namespace App\Filament\Resources\TaskResource\RelationManagers;

use App\Filament\Concerns\InteractsWithTaskEditModal;
use App\Filament\Resources\TaskResource;
use App\Models\Event;
use App\Models\EventProgramPoint;
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

    protected static string $relationship = 'tasks';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $title = 'Zadania';

    public function mount(): void
    {
        $this->mountInteractsWithTaskEditModal();
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                return TaskQueryFilters::applyDefaultListScopes($query, officeOnly: true);
            })
            ->columns(TaskResource::eventWorkspaceTableColumns())
            ->defaultSort('due_date', 'asc')
            ->searchable()
            ->filters([
                TaskResource::finishedVisibilityTableFilter(),
            ])
            ->recordUrl(null)
            ->recordAction(null)
            ->actionsColumnLabel('Działanie')
            ->actions([
                TaskResource::modalEditTableAction(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('createTask')
                    ->label('Dodaj zadanie')
                    ->icon('heroicon-m-plus')
                    ->action(fn () => $this->mountAction('createTask')),
            ])
            ->emptyStateHeading('Brak zadań')
            ->emptyStateDescription('Dodaj pierwsze zadanie powiązane z tą imprezą lub punktem programu.')
            ->emptyStateActions([
                Tables\Actions\Action::make('createTaskEmpty')
                    ->label('Dodaj zadanie')
                    ->icon('heroicon-m-plus')
                    ->action(fn () => $this->mountAction('createTask')),
            ]);
    }

    /**
     * Zadania imprezy + zadania punktów programu tej imprezy.
     */
    protected function getTableQuery(): Builder|Relation|null
    {
        $owner = $this->getOwnerRecord();

        if (! $owner instanceof Event) {
            return parent::getTableQuery();
        }

        $pointIds = $owner->programPoints()->pluck('id');

        return Task::query()
            ->where(function (Builder $query) use ($owner, $pointIds): void {
                $query->where(function (Builder $inner) use ($owner): void {
                    $inner->where('taskable_type', Event::class)
                        ->where('taskable_id', $owner->getKey());
                })->orWhere(function (Builder $inner) use ($pointIds): void {
                    $inner->where('taskable_type', EventProgramPoint::class)
                        ->whereIn('taskable_id', $pointIds);
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
}
