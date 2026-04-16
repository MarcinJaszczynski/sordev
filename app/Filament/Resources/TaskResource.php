<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaskResource\Pages;
use App\Filament\Resources\TaskResource\RelationManagers;
use App\Models\Task;
use App\Models\TaskStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Navigation\NavigationItem;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Zadania';
    protected static ?string $navigationGroup = 'Zadania';
    protected static ?int $navigationSort = 10;
    protected static ?string $modelLabel = 'zadanie';
    protected static ?string $pluralModelLabel = 'zadania';

    public static function getModelLabel(): string
    {
        return 'zadanie';
    }

    public static function getPluralModelLabel(): string
    {
        return 'zadania';
    }

    public static function getNavigationItems(): array
    {
        return [
            NavigationItem::make(static::getNavigationLabel())
                ->icon('heroicon-o-view-columns')
                ->group(static::getNavigationGroup())
                ->badge(static::getNavigationBadge())
                ->sort(static::getNavigationSort())
                ->url(static::getUrl()),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make()
                            ->schema([
                                Forms\Components\TextInput::make('title')
                                    ->label('Tytuł')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\RichEditor::make('description')
                                    ->label('Opis')
                                    ->toolbarButtons([
                                        'bold', 'italic', 'underline', 'strike', 'link', 'bulletList', 'orderedList', 'blockquote', 'codeBlock', 'h2', 'h3', 'color', 'highlight', 'undo', 'redo'
                                    ])
                                    ->columnSpanFull(),
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
                                    ->options([
                                        'low' => 'Niski',
                                        'medium' => 'Średni',
                                        'high' => 'Wysoki',
                                    ])
                                    ->required(),
                            ])
                            ->columns(2),
                        Forms\Components\Section::make('Przypisanie i kontekst')
                            ->schema([
                                Forms\Components\Select::make('assignee_id')
                                    ->label('Przypisane do')
                                    ->relationship('assignee', 'name')
                                    ->searchable()
                                    ->preload(),
                                Forms\Components\Select::make('parent_id')
                                    ->label('Zadanie nadrzędne')
                                    ->relationship('parent', 'title', modifyQueryUsing: fn (Builder $query) => $query->whereNull('parent_id'))
                                    ->searchable()
                                    ->preload(),
                                Forms\Components\Select::make('taskable_type')
                                    ->label('Kontekst zadania')
                                    ->options(Task::getTaskableTypeOptions())
                                    ->default(fn () => request()->query('taskable_type'))
                                    ->native(false)
                                    ->live()
                                    ->helperText('Zostaw puste, aby zadanie było wolne / nieprzypisane.')
                                    ->afterStateUpdated(function (Set $set): void {
                                        $set('taskable_id', null);
                                    }),
                                Forms\Components\Select::make('taskable_id')
                                    ->label('Powiązany rekord')
                                    ->options(fn (Get $get): array => Task::getTaskableRecordOptions($get('taskable_type')))
                                    ->default(fn () => request()->query('taskable_id'))
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn (Get $get): bool => filled($get('taskable_type')))
                                    ->required(fn (Get $get): bool => filled($get('taskable_type'))),
                            ]),
                    ])
                    ->columnSpan(['lg' => 2]),
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Załączniki')
                            ->schema([
                                Forms\Components\Placeholder::make('attachments_placeholder')
                                    ->label('Załączniki')
                                    ->content('Załączniki dodasz po zapisaniu zadania w zakładce „Załączniki”.'),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['status', 'assignee', 'author', 'taskable']))
            ->reorderable('order')
            ->defaultSort('order')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Tytuł')
                    ->searchable()
                    ->wrap()
                    ->sortable(),
                Tables\Columns\TextColumn::make('taskable_type_label')
                    ->label('Kontekst')
                    ->state(fn (Task $record): string => $record->taskable_type_label ?: 'Wolne / nieprzypisane')
                    ->description(fn (Task $record): string => $record->taskable_label)
                    ->wrap(),
                Tables\Columns\TextColumn::make('status.name')
                    ->label('Status')
                    ->sortable(),
                Tables\Columns\TextColumn::make('assignee.name')
                    ->label('Przypisane do')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('author.name')
                    ->label('Autor')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Termin')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
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
                    })
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->relationship('status', 'name'),
                Tables\Filters\SelectFilter::make('assignee')
                    ->label('Przypisane do')
                    ->relationship('assignee', 'name'),
                Tables\Filters\SelectFilter::make('taskable_type')
                    ->label('Kontekst')
                    ->options(Task::getTaskableTypeOptions()),
                Tables\Filters\Filter::make('unassigned_context')
                    ->label('Wolne / nieprzypisane')
                    ->query(fn (Builder $query): Builder => $query->whereNull('taskable_type')),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\CommentsRelationManager::class,
            RelationManagers\AttachmentsRelationManager::class,
            RelationManagers\SubtasksRelationManager::class,        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\TasksKanbanBoardPage::route('/'),
            'create' => Pages\CreateTask::route('/create'),
            'edit' => Pages\EditTask::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->with(['status', 'assignee', 'author', 'taskable']);
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function canViewAny(): bool
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user && $user->roles && $user->roles->contains('name', 'admin')) {
            return true;
        }
        if ($user && $user->roles && $user->roles->flatMap->permissions->contains('name', 'view task')) {
            return true;
        }
        return false;
    }
}