<?php

namespace App\Filament\Resources\EventTemplateResource\Concerns;

use App\Filament\Resources\EventResource;
use App\Models\EventTemplate;
use Filament\Actions;

trait HasGenerateEventAction
{
    protected function makeGenerateEventAction(): Actions\Action
    {
        return Actions\Action::make('generate_event')
            ->label('Generuj imprezę')
            ->icon('heroicon-o-sparkles')
            ->color('success')
            ->url(fn () => EventResource::getUrl('create', [
                'template' => $this->getEventTemplateRecord()->id,
            ]))
            ->tooltip('Otwiera pełny formularz tworzenia imprezy z programem i danymi ze szablonu');
    }

    protected function getEventTemplateRecord(): EventTemplate
    {
        $record = $this->record;

        if ($record instanceof EventTemplate) {
            return $record;
        }

        return EventTemplate::findOrFail((int) $record);
    }
}
