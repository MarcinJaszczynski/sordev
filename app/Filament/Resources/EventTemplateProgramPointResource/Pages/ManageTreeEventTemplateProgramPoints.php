<?php

namespace App\Filament\Resources\EventTemplateProgramPointResource\Pages;

use App\Filament\Resources\EventTemplateProgramPointResource;
use Filament\Resources\Pages\Page;
use Filament\Actions\Action;

class ManageTreeEventTemplateProgramPoints extends Page
{
    protected static string $resource = EventTemplateProgramPointResource::class;

    protected static string $view = 'filament.resources.event-template-program-point-resource.pages.manage-tree';

    protected static ?string $navigationLabel = 'Drzewo punktów';
    protected static ?string $title = 'Zarządzanie drzewem punktów programu';
    protected static ?string $slug = 'tree';

    public function getTitle(): string
    {
        return 'Zarządzanie drzewem punktów programu';
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Zmiany w drzewie zostały zapisane';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Dodaj nowy punkt programu')
                ->icon('heroicon-o-plus')
                ->url(static::getResource()::getUrl('create'))
                ->color('primary'),
            Action::make('back_to_list')
                ->label('Powrót do listy')
                ->icon('heroicon-o-list-bullet')
                ->url(static::getResource()::getUrl('index')),
        ];
    }
}
