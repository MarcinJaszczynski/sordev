<?php

namespace App\Filament\Resources\EventTemplateProgramPointResource\Pages;

use App\Filament\Resources\EventTemplateProgramPointResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Actions\Action as TableAction;

class ListEventTemplateProgramPoints extends ListRecords
{
    protected static string $resource = EventTemplateProgramPointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('tree')
                ->label('Drzewo punktów')
                ->icon('heroicon-o-queue-list')
                ->url(static::getResource()::getUrl('tree')),
            Action::make('create')
                ->label('Dodaj nowy punkt programu')
                ->icon('heroicon-o-plus')
                ->url(static::getResource()::getUrl('create'))
                ->color('primary'),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            TableAction::make('edit')
                ->label('Edytuj')
                ->icon('heroicon-o-pencil-square'),
            TableAction::make('delete')
                ->label('Usuń')
                ->icon('heroicon-o-trash'),
            TableAction::make('clone')
                ->label('Klonuj')
                ->icon('heroicon-o-document-duplicate')
                ->action(function ($record) {
                    $clone = $record->replicate();
                    $clone->name = $record->name.' (kopia)';
                    $clone->push();
                    // Klonuj tagi
                    $clone->tags()->sync($record->tags->pluck('id')->toArray());
                    // Klonuj relacje parents/children
                    $clone->parents()->sync($record->parents->pluck('id')->toArray());
                    $clone->children()->sync($record->children->pluck('id')->toArray());

                    return redirect()->to(static::getResource()::getUrl('edit', ['record' => $clone->id]));
                }),
        ];
    }
}
