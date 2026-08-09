<?php

namespace App\Filament\Resources\TaskResource\RelationManagers;

use App\Filament\Concerns\InteractsWithTaskEditModal;
use App\Filament\Concerns\InteractsWithTaskListQuickActions;
use App\Filament\Concerns\InteractsWithTaskOwnershipScope;
use App\Filament\Resources\TaskResource;
use App\Models\Event;
use App\Models\Task;
use App\Support\Tasks\TaskAuthorization;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class TasksRelationManager extends RelationManager
{
    use InteractsWithTaskEditModal;
    use InteractsWithTaskListQuickActions;
    use InteractsWithTaskOwnershipScope;

    protected static string $relationship = 'tasks';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $title = 'Zadania';

    public function mount(): void
    {
        $this->mountInteractsWithTaskEditModal();
    }

    public function table(Table $table): Table
    {
        $owner = $this->getOwnerRecord();

        return TaskResource::configureAdminTaskListTable(
            $table,
            officeOnly: true,
            showContextColumn: false,
            showSourceColumn: $owner instanceof Event,
            includeTrashed: false,
            additionalQueryModifier: fn (Builder $query): Builder => $this->applyTasksScopeTo($query),
        )
            ->heading('Zadania')
            ->description(new HtmlString(
                view('filament.tasks.ownership-quick-filters', ['tasksScope' => $this->tasksScope])->render()
            ))
            ->headerActions([
                Tables\Actions\Action::make('createTask')
                    ->label('Dodaj zadanie')
                    ->icon('heroicon-m-plus')
                    ->action(fn () => $this->mountAction('createTask')),
            ]);
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

    protected function afterTasksScopeChanged(): void
    {
        $this->resetTable();
    }

    protected function afterTaskModalSaved(Task $task): void
    {
        $this->resetTable();
    }

    protected function afterTaskListQuickActionSaved(): void
    {
        $this->resetTable();
    }
}
