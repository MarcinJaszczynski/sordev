<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Models\Event;
use Filament\Actions;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\MaxWidth;

class EditEventProgram extends Page
{
    protected static string $resource = EventResource::class;

    protected static string $view = 'filament.resources.event-resource.pages.edit-event-program';

    protected ?string $maxContentWidth = 'full';

    public Event $record;

    public function mount($record): void
    {
        if ($record instanceof Event) {
            $this->record = $record;

            return;
        }

        $this->record = Event::findOrFail($record);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back_to_edit')
                ->label('Wróć do edycji')
                ->icon('heroicon-o-arrow-left')
                ->url(fn (): string => static::getResource()::getUrl('edit', [
                    'record' => $this->record->id,
                    'activeRelationManager' => 0,
                ]))
                ->color('gray'),
        ];
    }

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::Full;
    }
}
