<?php

namespace App\Filament\Resources\TaskResource\RelationManagers;

use App\Filament\Resources\TaskResource;
use App\Models\Task;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class TasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $title = 'Zadania';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label('Tytuł')
                    ->required()
                    ->maxLength(255),

                Forms\Components\RichEditor::make('description')
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
                    ->options([
                        'low' => 'Niski',
                        'medium' => 'Średni',
                        'high' => 'Wysoki',
                    ])
                    ->default('medium')
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
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['status', 'assignee', 'author', 'parent']))
            ->defaultSort('due_date')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Tytuł')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('status.name')
                    ->label('Status')
                    ->badge(),

                Tables\Columns\TextColumn::make('priority')
                    ->label('Priorytet')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'high' => 'Wysoki',
                        'medium' => 'Średni',
                        'low' => 'Niski',
                        default => '—',
                    })
                    ->color(fn (?string $state) => match ($state) {
                        'high' => 'danger',
                        'medium' => 'warning',
                        'low' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('assignee.name')
                    ->label('Przypisane do')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('parent.title')
                    ->label('Zależne od')
                    ->placeholder('—')
                    ->wrap(),

                Tables\Columns\TextColumn::make('due_date')
                    ->label('Termin')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->relationship('status', 'name'),

                Tables\Filters\SelectFilter::make('priority')
                    ->label('Priorytet')
                    ->options([
                        'low' => 'Niski',
                        'medium' => 'Średni',
                        'high' => 'Wysoki',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['author_id'] = Auth::id();
                        $data['taskable_type'] = $this->getOwnerRecord()::class;
                        $data['taskable_id'] = $this->getOwnerRecord()->getKey();

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('open_task')
                    ->label('Pełny widok')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Task $record): string => TaskResource::getUrl('edit', ['record' => $record])),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
