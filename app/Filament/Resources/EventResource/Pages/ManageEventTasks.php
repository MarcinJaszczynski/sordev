<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Concerns\SingleRelationManagerPage;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\TaskResource;
use App\Filament\Resources\TaskResource\RelationManagers\TasksRelationManager;
use Filament\Actions;

class ManageEventTasks extends SingleRelationManagerPage
{
    protected static string $resource = EventResource::class;

    protected static ?string $navigationLabel = 'Zadania';

    protected static ?string $title = 'Zadania imprezy';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static function relationManager(): string
    {
        return TasksRelationManager::class;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('board')
                ->label('Kanban')
                ->icon('heroicon-m-view-columns')
                ->color('gray')
                ->url(TaskResource::getUrl('board').'?event='.$this->getRecord()->getKey()),
        ];
    }
}
