<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Models\Event;
use App\Services\EventPriceCalculator;
use Filament\Actions;
use Filament\Resources\Pages\Page;

class EventCalculation extends Page
{
    protected static string $resource = EventResource::class;

    protected static string $view = 'filament.resources.event-resource.pages.event-calculation';

    public Event $record;

    public function mount($record): void
    {
        if (is_array($record) && isset($record['id'])) {
            $this->record = Event::with(['bus', 'markup', 'programPoints.templatePoint'])->findOrFail($record['id']);
        } elseif ($record instanceof Event) {
            // Jeśli już jest modelem, załaduj relacje
            $this->record = $record->load(['bus', 'markup', 'programPoints.templatePoint']);
        } else {
            $this->record = Event::with(['bus', 'markup', 'programPoints.templatePoint'])->findOrFail($record);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Wróć do edycji')
                ->icon('heroicon-o-arrow-left')
                ->url(fn () => static::getResource()::getUrl('edit', [
                    'record' => $this->record->id,
                    'activeRelationManager' => 0,
                ]))
                ->color('gray'),
            Actions\Action::make('edit-program')
                ->label('Edytuj program')
                ->icon('heroicon-o-bars-3')
                ->url(fn () => static::getResource()::getUrl('edit-program', ['record' => $this->record->id]))
                ->color('primary'),
            Actions\Action::make('create_snapshot')
                ->label('Utwórz snapshot')
                ->icon('heroicon-o-camera')
                ->color('success')
                ->form([
                    \Filament\Forms\Components\TextInput::make('name')
                        ->label('Nazwa snapshotu')
                        ->required()
                        ->maxLength(255)
                        ->default('Snapshot kalkulacji '.now()->format('d.m.Y H:i')),

                    \Filament\Forms\Components\RichEditor::make('description')
                        ->maxLength(500)
                        ->helperText('Opisz powód utworzenia tego snapshotu'),
                ])
                ->action(function (array $data) {
                    $this->record->createManualSnapshot($data['name'], $data['description']);

                    \Filament\Notifications\Notification::make()
                        ->title('Snapshot utworzony')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('recalculate_event')
                ->label('Przelicz dla imprezy')
                ->icon('heroicon-o-calculator')
                ->color('primary')
                ->requiresConfirmation()
                ->action(function () {
                    $calculator = new EventPriceCalculator;
                    $calculator->calculateForEvent($this->record);

                    \Filament\Notifications\Notification::make()
                        ->title('Kalkulacja wykonana')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('edit_prices')
                ->label('Edytuj ceny')
                ->icon('heroicon-o-currency-dollar')
                ->url(fn () => static::getResource()::getUrl('edit', ['record' => $this->record->id]).'#price-per-person')
                ->color('primary'),
        ];
    }

    public function getWidgets(): array
    {
        return [
            \App\Filament\Resources\EventResource\Widgets\EventPriceTable::class,
        ];
    }
}
