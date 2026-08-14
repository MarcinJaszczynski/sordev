<?php

namespace App\Filament\Resources\TaskResource\RelationManagers;

use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Filament\Concerns\DispatchesTopbarNotificationRefresh;
use App\Filament\Resources\TaskResource;
use App\Models\Task;
use App\Services\NotificationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class SubtasksRelationManager extends RelationManager
{
    use DispatchesTopbarNotificationRefresh;

    protected static string $relationship = 'subtasks';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $title = 'Podzadania';

    public bool $panelMode = false;

    protected function dispatchPanelUpdated(): void
    {
        if (! $this->panelMode) {
            return;
        }

        $this->dispatch('task-full-editor-updated', taskId: $this->getOwnerRecord()->getKey());
    }

    protected function refreshTopbarAfterSubtaskChange(Task $subtask): void
    {
        NotificationService::clearCacheForTaskStakeholders($subtask);
        $this->dispatchTopbarNotificationRefresh();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label('Tytuł')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('description')
                    ->label('Treść')
                    ->rows(4)
                    ->columnSpanFull(),
                Forms\Components\Select::make('status_id')
                    ->label('Status')
                    ->relationship('status', 'name')
                    ->default(fn () => Task::getDefaultStatusId())
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('assignee_id')
                    ->label('Przypisane do')
                    ->relationship('assignee', 'name')
                    ->searchable()
                    ->preload(),
                Forms\Components\DateTimePicker::make('due_date')
                    ->label('Termin'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading($this->panelMode ? static::$title : null)
            ->paginated($this->panelMode ? false : true)
            ->searchable(! $this->panelMode)
            ->columns($this->panelMode ? [
                Tables\Columns\TextColumn::make('title')
                    ->label('Tytuł')
                    ->wrap(),
                Tables\Columns\TextColumn::make('status.name')
                    ->label('Status')
                    ->badge(),
            ] : [
                Tables\Columns\TextColumn::make('title')
                    ->label('Tytuł')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('status.name')
                    ->label('Status')
                    ->badge(),
                Tables\Columns\TextColumn::make('assignee.name')
                    ->label('Przypisane do')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Termin')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: $this->panelMode),
            ])
            ->reorderable($this->panelMode ? false : 'order')
            ->defaultSort('order')
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->when($this->panelMode, fn (Tables\Actions\CreateAction $action) => $action
                        ->label('Dodaj podzadanie')
                        ->icon('heroicon-o-plus')
                        ->iconButton())
                    ->modalHeading('Nowe podzadanie')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['author_id'] = Auth::id();
                        $data['taskable_type'] = $this->getOwnerRecord()->taskable_type;
                        $data['taskable_id'] = $this->getOwnerRecord()->taskable_id;
                        $data['source'] = TaskSource::Office->value;
                        $data['priority'] = TaskPriority::Normal->value;

                        return $data;
                    })
                    ->after(function (Task $record): void {
                        // Podzadanie jest pełnoprawnym Task — nie touchujemy rodzica,
                        // żeby topbar/lista prowadziły do podzadania, a nie do zadania głównego.
                        $record->moveToStart();
                        $this->getOwnerRecord()->loadCount('subtasks');
                        $this->refreshTopbarAfterSubtaskChange($record);
                        $this->dispatchPanelUpdated();
                    }),
            ])
            ->recordUrl(null)
            ->recordAction('edit')
            ->actions([
                $this->panelMode
                    ? Tables\Actions\EditAction::make()
                        ->after(function (Task $record): void {
                            $this->refreshTopbarAfterSubtaskChange($record);
                            $this->dispatchPanelUpdated();
                        })
                    : TaskResource::modalEditTableAction(),
                Tables\Actions\DeleteAction::make()
                    ->after(function (Task $record): void {
                        $this->getOwnerRecord()->loadCount('subtasks');
                        $this->refreshTopbarAfterSubtaskChange($record);
                        $this->dispatchPanelUpdated();
                    }),
            ])
            ->bulkActions($this->panelMode ? [] : [
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
