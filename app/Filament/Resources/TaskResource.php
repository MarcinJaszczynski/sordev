<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaskResource\Pages;
use App\Filament\Resources\TaskResource\RelationManagers;
use App\Models\Task;
use App\Models\TaskStatus;
use Filament\Forms;
use Filament\Forms\Form;
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
                                    ->columnSpanFull(),
                                Forms\Components\DateTimePicker::make('due_date')
                                    ->label('Termin'),
                                Forms\Components\Select::make('status_id')
                                    ->label('Status')
                                    ->relationship('status', 'name')
                                    ->default(1)
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
                        Forms\Components\Section::make('Przypisanie')
                            ->schema([
                                Forms\Components\Select::make('assignee_id')
                                    ->label('Przypisane do')
                                    ->relationship('assignee', 'name'),
                                Forms\Components\Select::make('parent_id')
                                    ->label('Zadanie nadrzędne')
                                    ->relationship('parent', 'title'),
                            ]),
                    ])
                    ->columnSpan(['lg' => 2]),
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Załączniki')
                            ->schema([
                                Forms\Components\FileUpload::make('attachments')
                                    ->label('Załączniki')
                                    ->multiple()
                                    ->directory('task-attachments'),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('order')
            ->defaultSort('order')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Tytuł')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status.name')
                    ->label('Status')
                    ->sortable(),
                Tables\Columns\TextColumn::make('assignee.name')
                    ->label('Przypisane do')
                    ->sortable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Termin')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('priority')
                    ->label('Priorytet')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->relationship('status', 'name'),
                Tables\Filters\SelectFilter::make('assignee')
                    ->relationship('assignee', 'name'),
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
            ]);
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