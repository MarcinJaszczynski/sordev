<?php

namespace App\Filament\Resources\TaskResource\RelationManagers;

use App\Enums\TaskSource;
use App\Filament\Concerns\InteractsWithTaskEditModal;
use App\Filament\Concerns\InteractsWithTaskListQuickActions;
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
use Illuminate\Support\Facades\Auth;

class TasksRelationManager extends RelationManager
{
    use InteractsWithTaskEditModal;
    use InteractsWithTaskListQuickActions;

    protected static string $relationship = 'tasks';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $title = 'Zadania';

    public function mount(): void
    {
        $this->mountInteractsWithTaskEditModal();
        if (method_exists($this, 'bootInteractsWithTaskListQuickActions')) {
            $this->bootInteractsWithTaskListQuickActions();
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                TaskQueryFilters::applyDefaultListScopes($query, officeOnly: true);

                // Domyślnie: najnowsza modyfikacja (updated_at DESC).
                return $query->orderByDesc('updated_at')->orderByDesc('id');
            })
            ->defaultSort('updated_at', 'desc')
            ->columns(TaskResource::eventWorkspaceTableColumns())
            ->searchable()
            ->filters([
                TaskResource::finishedVisibilityTableFilter(),
                Tables\Filters\SelectFilter::make('source')
                    ->label('Źródło')
                    ->options([
                        TaskSource::System->value => TaskSource::System->label(),
                        TaskSource::Office->value => TaskSource::Office->label(),
                    ]),
                Tables\Filters\Filter::make('mine_only')
                    ->label('Tylko moje')
                    ->query(function (Builder $query): Builder {
                        return TaskQueryFilters::mine($query, Auth::id());
                    }),
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
}
