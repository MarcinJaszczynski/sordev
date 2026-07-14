<?php

namespace App\Filament\Resources\TaskResource\RelationManagers;

use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Filament\Concerns\InteractsWithTaskEditModal;
use App\Filament\Resources\TaskResource;
use App\Models\Task;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class SubtasksRelationManager extends RelationManager
{
    use InteractsWithTaskEditModal;

    protected static string $relationship = 'subtasks';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $title = 'Podzadania';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label('Tytuł')
                    ->required()
                    ->maxLength(255),
                \FilamentTiptapEditor\TiptapEditor::make('description')
                    ->label('Opis'),
                Forms\Components\DateTimePicker::make('due_date')
                    ->label('Termin'),
                Forms\Components\Select::make('status_id')
                    ->label('Status')
                    ->relationship('status', 'name')
                    ->default(fn () => Task::getDefaultStatusId())
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('priority')
                    ->label('Priorytet')
                    ->options(TaskPriority::options())
                    ->default(TaskPriority::Normal->value)
                    ->required(),
                Forms\Components\Select::make('assignee_id')
                    ->label('Przypisane do')
                    ->relationship('assignee', 'name'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('order')
            ->defaultSort('order')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Tytuł')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status.name')
                    ->label('Status'),
                Tables\Columns\TextColumn::make('priority')
                    ->label('Priorytet')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => TaskPriority::normalize($state) === TaskPriority::Urgent->value
                        ? TaskPriority::Urgent->label()
                        : TaskPriority::Normal->label()),
                Tables\Columns\TextColumn::make('assignee.name')
                    ->label('Przypisane do'),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Termin')
                    ->dateTime(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['author_id'] = Auth::id();
                        $data['taskable_type'] = $this->getOwnerRecord()->taskable_type;
                        $data['taskable_id'] = $this->getOwnerRecord()->taskable_id;
                        $data['source'] = TaskSource::Office->value;

                        return $data;
                    }),
            ])
            ->recordUrl(null)
            ->recordAction('edit')
            ->actions([
                TaskResource::modalEditTableAction(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
