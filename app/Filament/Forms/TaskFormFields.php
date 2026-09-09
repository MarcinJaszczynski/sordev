<?php

namespace App\Filament\Forms;

use App\Enums\TaskPriority;
use App\Models\Task;
use App\Support\Tasks\TaskDueDates;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Database\Eloquent\Builder;

class TaskFormFields
{
    /**
     * Wspólne pola zadania — używane przez pełny form i modal.
     *
     * @return array<int, Forms\Components\Component>
     */
    public static function coreFields(bool $compact = false): array
    {
        $title = Forms\Components\TextInput::make('title')
            ->label('Tytuł')
            ->required()
            ->maxLength(255);

        if ($compact) {
            $title = $title->columnSpanFull();
        }

        $description = \FilamentTiptapEditor\TiptapEditor::make('description')
            ->label($compact ? 'Treść' : 'Opis')
            ->maxContentWidth('full')
            // Jak uwagi imprezy: klasa na wrapperze + na content — CSS resize celuje w .tiptap-prosemirror-wrapper.
            ->extraFieldWrapperAttributes(['class' => 'task-content-field'])
            ->extraInputAttributes(['class' => 'task-content-editor'])
            ->columnSpanFull();

        $dueDate = Forms\Components\DateTimePicker::make('due_date')
            ->label('Termin')
            ->default(fn () => TaskDueDates::defaultForNew());

        $status = Forms\Components\Select::make('status_id')
            ->label('Status')
            ->relationship('status', 'name')
            ->default(fn () => Task::getDefaultStatusId())
            ->searchable()
            ->preload()
            ->required();

        $priority = Forms\Components\Select::make('priority')
            ->label('Priorytet')
            ->options(TaskPriority::options())
            ->default(TaskPriority::Normal->value)
            ->required();

        $assignee = Forms\Components\Select::make('assignee_id')
            ->label('Przypisane do')
            ->relationship('assignee', 'name')
            ->searchable()
            ->preload();

        $parent = Forms\Components\Select::make('parent_id')
            ->label('Zadanie nadrzędne')
            ->relationship('parent', 'title', modifyQueryUsing: fn (Builder $query) => $query->whereNull('parent_id'))
            ->searchable()
            ->preload();

        $taskableType = Forms\Components\Select::make('taskable_type')
            ->label('Powiązane z')
            ->options(Task::getTaskableTypeOptions())
            ->default(fn () => request()->query('taskable_type'))
            ->native(false)
            ->live()
            ->afterStateUpdated(function (Set $set): void {
                $set('taskable_id', null);
            });

        if (! $compact) {
            $taskableType = $taskableType->helperText('Zostaw puste, aby zadanie było wolne / nieprzypisane.');
        }

        $taskableId = Forms\Components\Select::make('taskable_id')
            ->label(fn (Get $get): string => Task::getTaskableTypeOptions()[$get('taskable_type')] ?? 'Rekord')
            ->options(fn (Get $get): array => Task::getTaskableRecordOptions($get('taskable_type')))
            ->default(fn () => request()->query('taskable_id'))
            ->searchable()
            ->preload()
            ->visible(fn (Get $get): bool => filled($get('taskable_type')))
            ->required(fn (Get $get): bool => filled($get('taskable_type')));

        if ($compact) {
            $taskableId = $taskableId->columnSpanFull();
        }

        $attachments = Forms\Components\FileUpload::make('pending_attachments')
            ->label($compact ? 'Załączniki' : 'Pliki')
            ->disk('public')
            ->visibility('public')
            ->multiple()
            ->directory('task-attachments')
            ->preserveFilenames()
            ->openable()
            ->downloadable()
            ->helperText($compact
                ? 'Możesz dodać pliki już przy tworzeniu. Po zapisie dostępne są też komentarze, podzadania i kolejne załączniki.'
                : 'Możesz dodać pliki już przy tworzeniu zadania.')
            ->visible(fn (?Task $record): bool => ! $record?->exists);

        if ($compact) {
            $attachments = $attachments->columnSpanFull();

            return [
                $title,
                Forms\Components\Grid::make(3)->schema([$dueDate, $status, $priority]),
                Forms\Components\Grid::make(3)->schema([$assignee, $parent, $taskableType]),
                $taskableId,
                $description,
                $attachments,
            ];
        }

        // Compact (modal) nie dostaje tego pola — TaskFullEditor renderuje linki raz, nad formularzem.
        $contextNavigation = Forms\Components\ViewField::make('context_navigation')
            ->label('Przejdź do')
            ->view('filament.pages.partials.calendar-entry-links')
            ->viewData(fn (?Task $record): array => [
                'links' => \App\Support\Tasks\TaskContextRegistry::linksForTask($record),
                'openInNewTab' => true,
            ])
            ->visible(fn (?Task $record): bool => filled($record?->taskable_type))
            ->columnSpanFull();

        return [
            Forms\Components\Group::make()
                ->schema([
                    Forms\Components\Section::make()
                        ->schema([
                            $title,
                            $description,
                            $dueDate,
                            $status,
                            $priority,
                        ])
                        ->columns(['default' => 1, 'md' => 2]),
                    Forms\Components\Section::make('Przypisanie')
                        ->schema([
                            $assignee,
                            $parent,
                            $taskableType,
                            $taskableId,
                            $contextNavigation,
                        ]),
                ])
                ->columnSpan(['lg' => 2]),
            Forms\Components\Group::make()
                ->schema([
                    Forms\Components\Section::make('Załączniki')
                        ->schema([
                            $attachments,
                        ]),
                ])
                ->columnSpan(['lg' => 1])
                ->visible(fn (?Task $record): bool => ! $record?->exists),
        ];
    }
}
