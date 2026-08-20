<?php

namespace App\Filament\Resources;

use App\Enums\TaskPriority;
use App\Filament\Resources\TaskResource\Pages;
use App\Filament\Resources\TaskResource\RelationManagers;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Services\Tasks\TaskInboxService;
use App\Support\FilamentNavigation;
use App\Support\Tasks\TaskAuthorization;
use App\Support\Tasks\TaskListColumn;
use App\Support\Tasks\TaskNavigation;
use App\Support\Tasks\TaskQueryFilters;
use App\Services\NotificationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Zadania';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_OPERATIONS;

    protected static ?int $navigationSort = 6;

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
                ->badge(static::getNavigationBadge(), static::getNavigationBadgeColor())
                ->sort(static::getNavigationSort())
                ->url(static::getUrl()),
        ];
    }

    public static function modalEditTableAction(): Tables\Actions\Action
    {
        // Page/RM action `editTask` ma pełny edytor. Klik w przycisk idzie przez Alpine
        // (bez mountTableAction) — cykl table-action + Livewire morph zostawiał x-cloak
        // na wrapperze modala (isOpen=true, ale display:none). action() zostaje dla
        // recordAction / testów wołających mountTableAction.
        return Tables\Actions\Action::make('edit')
            ->label('Edytuj')
            ->icon('heroicon-o-pencil-square')
            ->alpineClickHandler(
                fn (Task $record): string => '$wire.openEditTaskModal('.(int) $record->id.')',
            )
            ->action(function (Task $record, $livewire): void {
                if (! is_object($livewire) || ! method_exists($livewire, 'openEditTaskModal')) {
                    return;
                }

                $livewire->openEditTaskModal((int) $record->id);
            });
    }

    public static function modalEditorForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label('Tytuł')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\Grid::make(3)
                    ->schema([
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
                    ]),
                Forms\Components\Grid::make(3)
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
                            ->afterStateUpdated(function (Set $set): void {
                                $set('taskable_id', null);
                            }),
                    ]),
                Forms\Components\Select::make('taskable_id')
                    ->label('Powiązany rekord')
                    ->options(fn (Get $get): array => Task::getTaskableRecordOptions($get('taskable_type')))
                    ->default(fn () => request()->query('taskable_id'))
                    ->searchable()
                    ->preload()
                    ->visible(fn (Get $get): bool => filled($get('taskable_type')))
                    ->required(fn (Get $get): bool => filled($get('taskable_type')))
                    ->columnSpanFull(),
                \FilamentTiptapEditor\TiptapEditor::make('description')
                    ->label('Treść')
                    ->columnSpanFull(),
                Forms\Components\ViewField::make('context_navigation')
                    ->label('Przejdź do')
                    ->view('filament.pages.partials.calendar-entry-links')
                    ->viewData(fn (?Task $record): array => [
                        'links' => \App\Support\Tasks\TaskContextRegistry::linksForTask($record),
                        'openInNewTab' => true,
                    ])
                    ->visible(fn (?Task $record): bool => filled($record?->taskable_type))
                    ->columnSpanFull(),
            ])
            ->columns(1);
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
                                \FilamentTiptapEditor\TiptapEditor::make('description')
                                    ->label('Opis')
                                    
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
                                    ->options(TaskPriority::options())
                                    ->default(TaskPriority::Normal->value)
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
                                Forms\Components\ViewField::make('context_navigation')
                                    ->label('Przejdź do')
                                    ->view('filament.pages.partials.calendar-entry-links')
                                    ->viewData(fn (?Task $record): array => [
                                        'links' => \App\Support\Tasks\TaskContextRegistry::linksForTask($record),
                                        'openInNewTab' => true,
                                    ])
                                    ->visible(fn (?Task $record): bool => filled($record?->taskable_type))
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpan(['lg' => 2]),
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Załączniki')
                            ->schema([
                                Forms\Components\FileUpload::make('pending_attachments')
                                    ->label('Pliki')
                                    ->disk('public')
                                    ->multiple()
                                    ->directory('task-attachments')
                                    ->preserveFilenames()
                                    ->helperText('Możesz dodać pliki już przy tworzeniu zadania.'),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1])
                    ->visible(fn (?Task $record): bool => ! $record?->exists),
            ])
            ->columns(3);
    }

    public static function archivedVisibilityTableFilter(): Tables\Filters\TernaryFilter
    {
        return Tables\Filters\TernaryFilter::make('archived_visibility')
            ->label('Zarchiwizowane')
            ->trueLabel('Pokaż')
            ->falseLabel('Ukryj')
            ->default(false)
            ->queries(
                true: fn (Builder $query): Builder => $query,
                false: fn (Builder $query): Builder => TaskQueryFilters::excludeArchived($query),
                blank: fn (Builder $query): Builder => TaskQueryFilters::excludeArchived($query),
            );
    }

    /**
     * @return array<int, Tables\Columns\Column>
     */
    public static function adminListTableColumns(bool $showContextColumn = true, bool $showSourceColumn = false): array
    {
        $columns = [
            Tables\Columns\ViewColumn::make('task_summary')
                ->label('Zadanie')
                ->searchable(['title', 'description'])
                ->sortable(['title'])
                ->view('filament.tasks.list-task-cell'),
        ];

        if ($showContextColumn) {
            $columns[] = Tables\Columns\ViewColumn::make('context_summary')
                ->label('Kontekst')
                ->view('filament.tasks.list-task-context-cell');
        } else {
            $columns[] = Tables\Columns\ViewColumn::make('context_summary')
                ->label('Kontekst')
                ->view('filament.tasks.list-task-context-cell')
                ->viewData(['showContextRecord' => false]);

            if ($showSourceColumn) {
                $columns[] = Tables\Columns\TextColumn::make('source')
                    ->label('Źródło')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state instanceof \App\Enums\TaskSource ? $state->value : (string) $state) {
                        \App\Enums\TaskSource::PilotChecklist->value => 'Checklista pilota',
                        default => 'Biuro',
                    })
                    ->color(fn ($state) => ($state instanceof \App\Enums\TaskSource ? $state->value : (string) $state) === \App\Enums\TaskSource::PilotChecklist->value
                        ? 'info'
                        : 'gray');
            }
        }

        return array_merge($columns, [
            Tables\Columns\SelectColumn::make('status_id')
                ->label('Status')
                ->options(fn (): array => TaskStatus::query()->orderBy('order')->pluck('name', 'id')->all())
                ->sortable()
                ->selectablePlaceholder(false),
            Tables\Columns\TextColumn::make('due_date')
                ->label('Termin / Priorytet')
                ->html()
                ->state(fn (Task $record): string => TaskListColumn::duePriorityCellHtml($record))
                ->sortable(),
            Tables\Columns\TextColumn::make('modified_at')
                ->label('Modyfikacja')
                ->html()
                ->state(fn (Task $record): string => TaskListColumn::modificationCellHtml($record))
                ->sortable(query: function (Builder $query, string $direction): Builder {
                    $dir = strtolower($direction) === 'asc' ? 'asc' : 'desc';

                    return $query->orderByRaw('COALESCE(updated_at, created_at) '.$dir);
                }),
        ]);
    }

    /**
     * @return array<int, Tables\Columns\Column>
     */
    public static function sharedTableColumns(bool $showContext = true): array
    {
        $columns = [
            Tables\Columns\TextColumn::make('title')
                ->label('Tytuł')
                ->searchable()
                ->wrap()
                ->sortable()
                ->weight('semibold')
                ->description(function (Task $record): ?string {
                    if ($record->parent) {
                        return 'Podzadanie: '.$record->parent->title;
                    }

                    if (! filled($record->description)) {
                        return null;
                    }

                    return Str::limit(trim(strip_tags((string) $record->description)), 120);
                }),
            Tables\Columns\TextColumn::make('parent.title')
                ->label('Zad. nadrzędne')
                ->url(fn (Task $record): ?string => $record->parent_id
                    ? TaskNavigation::fullViewUrl($record->parent_id)
                    : null)
                ->color('info')
                ->wrap(),
            Tables\Columns\IconColumn::make('attachments_count')
                ->label('Zał.')
                ->icon(fn (Task $record): string => ($record->attachments_count ?? 0) > 0
                    ? 'heroicon-o-paper-clip'
                    : 'heroicon-o-minus')
                ->color(fn (Task $record): string => ($record->attachments_count ?? 0) > 0 ? 'primary' : 'gray')
                ->tooltip(fn (Task $record): string => ($record->attachments_count ?? 0) > 0
                    ? 'Ma załączniki'
                    : 'Brak załączników'),
            Tables\Columns\SelectColumn::make('status_id')
                ->label('Status')
                ->options(fn (): array => TaskStatus::query()->orderBy('order')->pluck('name', 'id')->all())
                ->sortable()
                ->selectablePlaceholder(false),
            Tables\Columns\TextColumn::make('latest_comment')
                ->label('Ostatni komentarz')
                ->state(function (Task $record): ?string {
                    $comment = $record->comments->first();

                    if (! $comment) {
                        return null;
                    }

                    return Str::limit(trim(strip_tags((string) $comment->content)), 160);
                })
                ->placeholder('—')
                ->wrap()
                ->extraAttributes(['class' => 'bg-slate-50 dark:bg-slate-900/40 rounded-lg px-2 py-1'])
                ->weight('semibold'),
            Tables\Columns\TextColumn::make('latest_comment_at')
                ->label('Data komentarza')
                ->state(fn (Task $record): ?string => optional($record->comments->first()?->created_at)?->format('d.m.Y H:i'))
                ->placeholder('—')
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('latest_activity_at')
                ->label('Ostatnia aktywność')
                ->dateTime('d.m.Y H:i')
                ->sortable()
                ->description(function (Task $record): ?string {
                    $commentAt = $record->comments->first()?->created_at;
                    $updatedAt = $record->updated_at;
                    $createdAt = $record->created_at;

                    if ($commentAt && (! $updatedAt || $commentAt->greaterThan($updatedAt))) {
                        return 'Komentarz';
                    }

                    if ($record->attachments_count > 0) {
                        return 'Zawiera załączniki';
                    }

                    if ($updatedAt && $createdAt && $updatedAt->greaterThan($createdAt)) {
                        return 'Edycja zadania';
                    }

                    return 'Utworzenie';
                }),
            Tables\Columns\TextColumn::make('assignee.name')
                ->label('Przypisane do')
                ->placeholder('—')
                ->sortable(),
            Tables\Columns\TextColumn::make('author.name')
                ->label('Autor')
                ->placeholder('—')
                ->sortable(),
            Tables\Columns\TextColumn::make('created_at')
                ->label('Utworzono')
                ->dateTime('d.m.Y H:i')
                ->sortable(),
            Tables\Columns\TextColumn::make('due_date')
                ->label('Termin')
                ->dateTime('d.m.Y H:i')
                ->placeholder('—')
                ->sortable(),
            Tables\Columns\TextColumn::make('updated_at')
                ->label('Ostatnia zmiana')
                ->dateTime('d.m.Y H:i')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('priority')
                ->label('Priorytet')
                ->badge()
                ->formatStateUsing(fn (?string $state) => TaskPriority::normalize($state) === TaskPriority::Urgent->value
                    ? TaskPriority::Urgent->label()
                    : TaskPriority::Normal->label())
                ->color(fn (?string $state) => TaskPriority::normalize($state) === TaskPriority::Urgent->value
                    ? 'danger'
                    : 'gray')
                ->sortable(),
        ];

        if ($showContext) {
            array_splice($columns, 2, 0, [
                Tables\Columns\TextColumn::make('taskable_type_label')
                    ->label('Kontekst')
                    ->state(fn (Task $record): string => $record->taskable_type_label ?: 'Wolne / nieprzypisane')
                    ->description(fn (Task $record): string => $record->taskable_label)
                    ->url(fn (Task $record): ?string => \App\Support\Tasks\TaskContextRegistry::urlForRecord($record->taskable))
                    ->openUrlInNewTab()
                    ->color(fn (Task $record): ?string => $record->taskable ? 'primary' : null)
                    ->wrap(),
            ]);
        }

        return $columns;
    }

    /**
     * @return array<int, Tables\Columns\Column>
     */
    public static function eventTasksTableColumns(): array
    {
        $columns = static::sharedTableColumns(showContext: false);

        array_splice($columns, 1, 0, [
            Tables\Columns\TextColumn::make('source')
                ->label('Źródło')
                ->badge()
                ->formatStateUsing(fn ($state) => match ($state instanceof \App\Enums\TaskSource ? $state->value : (string) $state) {
                    \App\Enums\TaskSource::PilotChecklist->value => 'Checklista pilota',
                    default => 'Biuro',
                })
                ->color(fn ($state) => ($state instanceof \App\Enums\TaskSource ? $state->value : (string) $state) === \App\Enums\TaskSource::PilotChecklist->value
                    ? 'info'
                    : 'gray'),
        ]);

        return $columns;
    }

    /**
     * @return array<int, Tables\Filters\BaseFilter>
     */
    public static function sharedTableFilters(bool $includeTrashed = true): array
    {
        $filters = [
            static::finishedVisibilityTableFilter(),
            static::archivedVisibilityTableFilter(),
            Tables\Filters\SelectFilter::make('priority')
                ->label('Priorytet')
                ->options(TaskPriority::options()),
            Tables\Filters\SelectFilter::make('status')
                ->label('Status')
                ->relationship('status', 'name'),
            Tables\Filters\SelectFilter::make('assignee')
                ->label('Przypisane do')
                ->relationship('assignee', 'name'),
        ];

        if ($includeTrashed) {
            $filters[] = Tables\Filters\TrashedFilter::make();
        }

        return $filters;
    }

    public static function configureAdminTaskListTable(
        Table $table,
        bool $officeOnly = true,
        bool $showContextColumn = true,
        bool $showSourceColumn = false,
        bool $includeTrashed = false,
        ?\Closure $additionalQueryModifier = null,
    ): Table {
        return $table
            ->modifyQueryUsing(function (Builder $query) use ($officeOnly, $additionalQueryModifier): Builder {
                TaskQueryFilters::applyDefaultListScopes($query, $officeOnly);

                $query = TaskQueryFilters::orderByHierarchyThenLatestActivityDesc($query);

                if ($additionalQueryModifier) {
                    $query = $additionalQueryModifier($query);
                }

                return $query;
            })
            ->defaultSort('latest_activity_at', 'desc')
            ->columns(static::adminListTableColumns($showContextColumn, $showSourceColumn))
            ->filters(static::sharedTableFilters($includeTrashed))
            ->recordUrl(null)
            ->recordAction(null)
            ->actionsColumnLabel('Działanie')
            ->actions([
                static::modalEditTableAction(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Task $record): bool => TaskAuthorization::canDelete(auth()->user(), $record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make(static::tableBulkActions()),
            ]);
    }

    public static function configureSharedTable(
        Table $table,
        bool $officeOnly = true,
        bool $showContext = true,
        bool $includeTrashed = true,
        ?\Closure $additionalQueryModifier = null,
    ): Table {
        return $table
            ->modifyQueryUsing(function (Builder $query) use ($officeOnly, $additionalQueryModifier): Builder {
                TaskQueryFilters::applyDefaultListScopes($query, $officeOnly);

                $query = TaskQueryFilters::orderByHierarchyThenLatestActivityDesc($query);

                if ($additionalQueryModifier) {
                    $query = $additionalQueryModifier($query);
                }

                return $query;
            })
            ->defaultSort('latest_activity_at', 'desc')
            ->columns(static::sharedTableColumns($showContext))
            ->filters(static::sharedTableFilters($includeTrashed))
            ->recordUrl(null)
            ->recordAction('edit')
            ->actions([
                static::modalEditTableAction(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Task $record): bool => TaskAuthorization::canDelete(auth()->user(), $record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make(static::tableBulkActions()),
            ]);
    }

    public static function finishedVisibilityTableFilter(): Tables\Filters\TernaryFilter
    {
        return Tables\Filters\TernaryFilter::make('finished_visibility')
            ->label('Zakończone i anulowane')
            ->trueLabel('Pokaż')
            ->falseLabel('Ukryj')
            ->default(false)
            ->queries(
                true: fn (Builder $query): Builder => $query,
                false: fn (Builder $query): Builder => TaskQueryFilters::excludeFinished($query),
                blank: fn (Builder $query): Builder => TaskQueryFilters::excludeFinished($query),
            );
    }

    public static function table(Table $table): Table
    {
        return static::configureAdminTaskListTable($table, officeOnly: true, includeTrashed: true)
            ->filters(array_merge(static::sharedTableFilters(true), [
                Tables\Filters\SelectFilter::make('taskable_type')
                    ->label('Kontekst')
                    ->options(Task::getTaskableTypeOptions()),
                Tables\Filters\Filter::make('unassigned_context')
                    ->label('Wolne / nieprzypisane')
                    ->query(fn (Builder $query): Builder => $query->whereNull('taskable_type')),
            ]));
    }

    /**
     * @return array<int, Tables\Actions\BulkAction>
     */
    public static function tableBulkActions(): array
    {
        return [
            static::changeStatusBulkAction(),
            static::archiveBulkAction(),
            Tables\Actions\DeleteBulkAction::make()
                ->action(function (Collection $records): void {
                    $user = auth()->user();
                    $deleted = 0;
                    $skipped = 0;

                    foreach ($records as $task) {
                        if (! $task instanceof Task) {
                            continue;
                        }

                        if (TaskAuthorization::canDelete($user, $task)) {
                            $task->delete();
                            $deleted++;
                        } else {
                            $skipped++;
                        }
                    }

                    if ($deleted > 0) {
                        Notification::make()
                            ->title($deleted === 1 ? 'Zadanie usunięte' : "Usunięto {$deleted} zadań")
                            ->success()
                            ->send();
                    }

                    if ($skipped > 0) {
                        Notification::make()
                            ->title('Nie usunięto części zadań')
                            ->body('Usunąć może tylko osoba, która utworzyła zadanie.')
                            ->warning()
                            ->send();
                    }
                })
                ->deselectRecordsAfterCompletion(),
            Tables\Actions\ForceDeleteBulkAction::make()
                ->visible(fn (): bool => auth()->user()?->hasRole(['admin', 'super_admin']) ?? false)
                ->action(function (Collection $records): void {
                    $user = auth()->user();

                    foreach ($records as $task) {
                        if ($task instanceof Task && TaskAuthorization::canForceDelete($user, $task)) {
                            $task->forceDelete();
                        }
                    }
                })
                ->deselectRecordsAfterCompletion(),
            Tables\Actions\RestoreBulkAction::make(),
        ];
    }

    public static function archiveBulkAction(): Tables\Actions\BulkAction
    {
        return Tables\Actions\BulkAction::make('archive')
            ->label('Archiwizuj')
            ->icon('heroicon-o-archive-box')
            ->requiresConfirmation()
            ->action(function (Collection $records): void {
                $archivedId = TaskQueryFilters::archivedStatusId();

                if (! $archivedId) {
                    Notification::make()
                        ->title('Brak statusu „Zarchiwizowane”')
                        ->warning()
                        ->send();

                    return;
                }

                $archivableIds = TaskStatus::query()
                    ->whereIn('name', ['Zakończone', 'Zaakceptowane', 'Anulowane'])
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->all();

                $archived = 0;
                $skipped = 0;

                foreach ($records as $task) {
                    if (! $task instanceof Task) {
                        continue;
                    }

                    if (in_array((int) $task->status_id, $archivableIds, true)) {
                        $task->update(['status_id' => $archivedId]);
                        $archived++;
                    } else {
                        $skipped++;
                    }
                }

                if ($archived > 0) {
                    Notification::make()
                        ->title($archived === 1 ? 'Zadanie zarchiwizowane' : "Zarchiwizowano {$archived} zadań")
                        ->success()
                        ->send();
                }

                if ($skipped > 0) {
                    Notification::make()
                        ->title('Nie zarchiwizowano części zadań')
                        ->body('Archiwizować można tylko zadania zakończone, zaakceptowane lub anulowane.')
                        ->warning()
                        ->send();
                }
            })
            ->deselectRecordsAfterCompletion();
    }

    public static function changeStatusBulkAction(): Tables\Actions\BulkAction
    {
        return Tables\Actions\BulkAction::make('changeStatus')
            ->label('Zmień status')
            ->icon('heroicon-o-arrow-path')
            ->form([
                Forms\Components\Select::make('status_id')
                    ->label('Nowy status')
                    ->options(fn (): array => TaskStatus::query()->orderBy('order')->pluck('name', 'id')->all())
                    ->required()
                    ->searchable(),
            ])
            ->action(function (Collection $records, array $data): void {
                $records
                    ->filter(fn ($record): bool => $record instanceof Task)
                    ->each(fn (Task $task) => $task->update(['status_id' => $data['status_id']]));

                Notification::make()
                    ->title('Status zaktualizowany')
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
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
            'index' => Pages\ListTasks::route('/'),
            'board' => Pages\TasksKanbanBoardPage::route('/board'),
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
            ->with(['status', 'assignee', 'author', 'taskable', 'parent']);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = app(TaskInboxService::class)->unseenCount(auth()->user());

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string | array | null
    {
        return static::getNavigationBadge() ? 'warning' : null;
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
