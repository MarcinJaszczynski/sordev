<?php

namespace App\Filament\Resources\EventTemplateResource\Pages;

use App\Filament\Resources\EventTemplateResource;
use App\Models\EventTemplate;
use Filament\Resources\Pages\Page;
use Filament\Actions;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\View\View;

class ProgramEventTemplate extends Page
{
    // NIE używaj use DispatchesEvents;
    protected static string $resource = EventTemplateResource::class;
    protected static string $view = 'filament.resources.event-template-resource.pages.program-event-template';
    protected static ?string $slug = '/{record}/program';

    public EventTemplate $record;

    public function mount($record): void
    {
        \Illuminate\Support\Facades\Log::info('ProgramEventTemplate::mount() wywołane', ['record' => $record]);
        
        // Filament może przekazać id (int/string) lub model (obiekt)
        if ($record instanceof \App\Models\EventTemplate) {
            $this->record = $record;
        } else {
            $this->record = EventTemplate::findOrFail((int)$record);
        }
        
        \Illuminate\Support\Facades\Log::info('EventTemplate załadowany', [
            'id' => $this->record->id,
            'name' => $this->record->name
        ]);
    }

    public function getTitle(): string
    {
        return 'Program: ' . $this->record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Wróć do edycji')
                ->icon('heroicon-o-arrow-left')
                ->url(fn () => static::getResource()::getUrl('edit', ['record' => $this->record->id]))
                ->color('gray'),
            Actions\Action::make('transport')
                ->label('Transport')
                ->icon('heroicon-o-truck')
                ->url(fn () => static::getResource()::getUrl('transport', ['record' => $this->record->id]))
                ->color('info'),
            Actions\Action::make('calculation')
                ->label('Kalkulacja')
                ->icon('heroicon-o-calculator')
                ->url(fn () => static::getResource()::getUrl('calculation', ['record' => $this->record->id]))
                ->color('success'),
        ];
    }
}
