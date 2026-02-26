<?php

namespace App\Filament\Resources\EventTemplateProgramPointResource\Pages;

use App\Filament\Resources\EventTemplateProgramPointResource;
use App\Filament\Resources\EventTemplateProgramPointResource\Widgets\EventTemplatesForProgramPoint;
use App\Traits\CompressesImages;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEventTemplateProgramPoint extends EditRecord
{
    use CompressesImages;
    
    protected static string $resource = EventTemplateProgramPointResource::class;
    protected static string $view = 'filament.resources.event-template-program-point-resource.pages.edit-event-template-program-point';

    public function getTitle(): string
    {
        return 'Edytuj punkt programu';
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Zmiany zostały zapisane';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\Action::make('clone')
                ->label('Klonuj')
                ->icon('heroicon-o-document-duplicate')
                ->action(fn () => $this->cloneProgramPoint()),
        ];
    }

    protected function cloneProgramPoint()
    {
        $original = $this->record;
        $clone = $original->replicate();
        $clone->name = $original->name . ' (Kopia)';
        $clone->push();
        // Klonuj relacje tags
        $clone->tags()->sync($original->tags->pluck('id')->toArray());
        // Klonuj relacje parents/children
        $clone->parents()->sync($original->parents->pluck('id')->toArray());
        $clone->children()->sync($original->children->pluck('id')->toArray());
        // Przekieruj do edycji nowego klona
        $this->redirect(static::getResource()::getUrl('edit', ['record' => $clone->id]));
    }

    protected function getFooterWidgets(): array
    {
        return [
            EventTemplatesForProgramPoint::make(['record' => $this->record]),
        ];
    }
}
