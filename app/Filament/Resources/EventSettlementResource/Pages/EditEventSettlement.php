<?php

namespace App\Filament\Resources\EventSettlementResource\Pages;

use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventSettlementResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Livewire\Attributes\On;

class EditEventSettlement extends EditRecord
{
    private const SUMMARY_FIELDS = [
        'planned_cost_pln',
        'actual_cost_pln',
        'participant_due_pln',
        'participant_paid_pln',
    ];

    protected static string $resource = EventSettlementResource::class;

    protected function beforeFill(): void
    {
        $this->record->refreshDerivedData();
    }

    #[On('settlement-data-changed')]
    public function onSettlementDataChanged(): void
    {
        $this->record->refresh();
        $this->refreshFormData(self::SUMMARY_FIELDS);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('open_event')
                ->label('Przejdź do imprezy')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn () => EventResource::getUrl('edit', ['record' => $this->record->event_id]))
                ->openUrlInNewTab(),

            Actions\Action::make('import_costs')
                ->label('Importuj koszty z imprezy')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Importuj koszty z imprezy')
                ->modalDescription('Importuje aktywne punkty programu jako planowane koszty. Duplikaty są pomijane.')
                ->action(function () {
                    $this->record->importFromEvent();
                    $this->record->refresh();
                    Notification::make()->success()->title('Zaimportowano koszty z imprezy')->send();

                    return redirect(static::getResource()::getUrl('edit', ['record' => $this->record]));
                })
                ->visible(fn() => $this->record->status === 'draft'),

            Actions\Action::make('recalc_pilot')
                ->label('Oblicz gotówkę pilota')
                ->icon('heroicon-o-calculator')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->recalculatePilotCash();
                    $this->record->refresh();
                    Notification::make()->success()->title('Obliczono gotówkę pilota')->send();

                    return redirect(static::getResource()::getUrl('edit', ['record' => $this->record]));
                }),

            Actions\Action::make('close_settlement')
                ->label('Zamknij rozliczenie')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('Czy na pewno chcesz zamknąć rozliczenie? Tej operacji nie można cofnąć.')
                ->action(function () {
                    $this->record->update([
                        'status'      => 'closed',
                        'settled_at'  => now(),
                    ]);
                    Notification::make()->success()->title('Rozliczenie zamknięte')->send();

                    return redirect(static::getResource()::getUrl('edit', ['record' => $this->record]));
                })
                ->visible(fn() => in_array($this->record->status, ['active', 'pilot_settled'])),

            Actions\DeleteAction::make(),
        ];
    }
}
