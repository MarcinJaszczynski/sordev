<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Concerns\InteractsWithTaskEditModal;
use App\Filament\Concerns\InteractsWithTaskListQuickActions;
use App\Filament\Concerns\InteractsWithTaskOwnershipScope;
use App\Filament\Concerns\MarksTaskInboxAsSeen;
use App\Filament\Resources\TaskResource;
use App\Models\Task;
use App\Support\Tasks\TaskQueryFilters;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListTasks extends ListRecords
{
    use InteractsWithTaskEditModal;
    use InteractsWithTaskListQuickActions;
    use InteractsWithTaskOwnershipScope;
    use MarksTaskInboxAsSeen;

    protected static string $resource = TaskResource::class;

    protected static string $view = 'filament.resources.task-resource.pages.list-tasks';

    public function mount(): void
    {
        if (method_exists(get_parent_class($this), 'mount')) {
            parent::mount();
        }

        $this->mountInteractsWithTaskEditModal();
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'active';
    }

    public function getTabs(): array
    {
        return [
            'active' => Tab::make('Aktywne')
                ->icon('heroicon-o-user')
                ->modifyQueryUsing(fn (Builder $query): Builder => TaskQueryFilters::orderByLatestActivityDesc($query)),
            'new' => Tab::make('Nowe (do zrobienia)')
                ->icon('heroicon-o-sparkles')
                ->modifyQueryUsing(function (Builder $query): Builder {
                    TaskQueryFilters::openTodo($query);

                    return TaskQueryFilters::orderByLatestActivityDesc($query);
                }),
            'manual' => Tab::make('Kolejność ręczna')
                ->icon('heroicon-o-bars-3')
                ->modifyQueryUsing(fn (Builder $query): Builder => TaskQueryFilters::orderByManual($query)),
            'all' => Tab::make('Wszystkie statusy')
                ->icon('heroicon-o-queue-list')
                ->modifyQueryUsing(fn (Builder $query): Builder => TaskQueryFilters::orderByLatestActivityDesc($query)),
        ];
    }

    public function table(Table $table): Table
    {
        $table = parent::table($table);

        $table = $table->modifyQueryUsing(function (Builder $query): Builder {
            return $this->applyTasksScopeTo($query);
        });

        if ($this->activeTab === 'manual') {
            return $table
                ->reorderable('order')
                ->defaultSort('order');
        }

        return $table;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('createTask')
                ->label('Dodaj zadanie')
                ->icon('heroicon-m-plus')
                ->action(fn () => $this->mountAction('createTask')),
            Actions\Action::make('board')
                ->label('Widok Tablicy (Kanban)')
                ->icon('heroicon-m-view-columns')
                ->color('gray')
                ->url(TaskResource::getUrl('board')),
        ];
    }

    protected function afterTaskModalSaved(Task $task): void
    {
        $this->resetTable();
    }

    protected function afterTasksScopeChanged(): void
    {
        $this->resetTable();
    }
}
