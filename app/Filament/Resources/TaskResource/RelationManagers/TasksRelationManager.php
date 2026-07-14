<?php

namespace App\Filament\Resources\TaskResource\RelationManagers;

use App\Filament\Concerns\InteractsWithTaskEditModal;
use App\Filament\Concerns\InteractsWithTaskOwnershipScope;
use App\Filament\Resources\TaskResource;
use App\Models\Task;
use App\Support\Tasks\TaskAuthorization;
use App\Support\Tasks\TaskQueryFilters;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class TasksRelationManager extends RelationManager
{
    use InteractsWithTaskEditModal;
    use InteractsWithTaskOwnershipScope;

    protected static string $relationship = 'tasks';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $title = 'Zadania';

    public function mount(): void
    {
        $this->mountInteractsWithTaskEditModal();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label('Tytuł')
                    ->required()
                    ->maxLength(255),

                \FilamentTiptapEditor\TiptapEditor::make('description')
                    ->label('Opis')
                    ->columnSpanFull(),

                Forms\Components\DateTimePicker::make('due_date')
                    ->label('Termin'),

                Forms\Components\Select::make('status_id')
                    ->label('Status')
                    ->relationship('status', 'name')
                    ->default(fn () => Task::getDefaultStatusId())
                    ->required()
                    ->searchable()
                    ->preload(),

                Forms\Components\Select::make('priority')
                    ->label('Priorytet')
                    ->options(\App\Enums\TaskPriority::options())
                    ->default(\App\Enums\TaskPriority::Normal->value)
                    ->required(),

                Forms\Components\Select::make('assignee_id')
                    ->label('Przypisane do')
                    ->relationship('assignee', 'name')
                    ->searchable()
                    ->preload(),

                Forms\Components\Select::make('parent_id')
                    ->label('Zadanie nadrzędne')
                    ->options(function (): array {
                        return Task::query()
                            ->where('taskable_type', $this->getOwnerRecord()::class)
                            ->where('taskable_id', $this->getOwnerRecord()->getKey())
                            ->whereNull('parent_id')
                            ->tap(fn ($query) => TaskQueryFilters::officeOnly($query))
                            ->orderBy('title')
                            ->pluck('title', 'id')
                            ->all();
                    })
                    ->searchable()
                    ->preload(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        $owner = $this->getOwnerRecord();

        return TaskResource::configureSharedTable(
            $table,
            officeOnly: true,
            showContext: false,
            includeTrashed: false,
            additionalQueryModifier: fn (Builder $query): Builder => $this->applyTasksScopeTo($query),
        )
            ->heading('Zadania')
            ->description(new HtmlString(
                view('filament.tasks.ownership-quick-filters', ['tasksScope' => $this->tasksScope])->render()
            ))
            ->columns(TaskResource::eventTasksTableColumns())
            ->filters([
                TaskResource::finishedVisibilityTableFilter(),
                TaskResource::archivedVisibilityTableFilter(),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->relationship('status', 'name'),
                Tables\Filters\SelectFilter::make('priority')
                    ->label('Priorytet')
                    ->options(\App\Enums\TaskPriority::options()),
            ])
            ->headerActions([
                $this->makeCreateTaskAction(defaultFormData: fn (): array => [
                    'taskable_type' => $owner::class,
                    'taskable_id' => $owner->getKey(),
                ]),
            ])
            ->actions([
                TaskResource::modalEditTableAction(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Task $record): bool => TaskAuthorization::canDelete(auth()->user(), $record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make(TaskResource::tableBulkActions()),
            ]);
    }

    protected function afterTasksScopeChanged(): void
    {
        $this->resetTable();
    }

    protected function afterTaskModalSaved(Task $task): void
    {
        $this->resetTable();
    }
}
