<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Concerns\SingleRelationManagerPage;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Concerns\HasEventWorkflowContext;
use App\Filament\Resources\TaskResource;
use App\Filament\Resources\TaskResource\RelationManagers\TasksRelationManager;
use Filament\Actions;

class ManageEventTasks extends SingleRelationManagerPage
{
    use HasEventWorkflowContext;

    protected static string $resource = EventResource::class;

    protected static ?string $navigationLabel = 'Zadania';

    protected static ?string $title = 'Zadania';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    /**
     * @return array<int|string, string>
     */
    protected function buildModuleBreadcrumbs(): array
    {
        return $this->eventRecordBreadcrumbs(sectionLabel: 'Zadania');
    }

    protected static function relationManager(): string
    {
        return TasksRelationManager::class;
    }

    /**
     * Modal otwiera TasksRelationManager — tu nie, bo wychodziły dwa okna (pierwsze martwe).
     */
    protected function openDeepLinkedTaskIfPresent(): void
    {
        //
    }

    protected function getHeaderActions(): array
    {
        // „Nowe zadanie” — box workflow + headerActions tabeli TasksRelationManager.
        return [
            Actions\Action::make('board')
                ->label('Tablica zadań')
                ->icon('heroicon-m-view-columns')
                ->color('gray')
                ->tooltip('Widok kolumnowy statusów — przeciąganie i szybka zmiana etapu zadania.')
                ->url(TaskResource::getUrl('board').'?event='.$this->getRecord()->getKey()),
        ];
    }
}
