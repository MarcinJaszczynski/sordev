<?php

namespace App\Filament\Resources\EventSettlementResource\Pages;

use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventSettlementResource;
use App\Filament\Resources\EventSettlementResource\Concerns\HasEventSettlementWorkflowContext;
use Filament\Actions;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Livewire\Attributes\On;

class EditEventSettlement extends EditRecord
{
    use HasEventSettlementWorkflowContext;

    private const SUMMARY_FIELDS = [
        'planned_cost_pln',
        'actual_cost_pln',
        'participant_due_pln',
        'participant_paid_pln',
    ];

    protected static string $resource = EventSettlementResource::class;

    protected static ?string $navigationLabel = 'Podsumowanie';

    protected static ?string $navigationIcon = 'heroicon-o-home';

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

    public function getRelationManagers(): array
    {
        return [];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('open_event')
                ->label('Impreza')
                ->icon('heroicon-o-calendar-days')
                ->url(fn () => EventResource::getUrl('edit', ['record' => $this->record->event_id])),
            ActionGroup::make([
                Actions\Action::make('import_costs')
                    ->label('Importuj koszty z imprezy')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->requiresConfirmation()
                    ->action(function () {
                        $this->record->importFromEvent();
                        $this->record->refresh();
                        Notification::make()->success()->title('Zaimportowano koszty z imprezy')->send();
                    })
                    ->visible(fn () => $this->record->status === 'draft'),
                Actions\Action::make('recalc_pilot')
                    ->label('Oblicz gotówkę pilota')
                    ->icon('heroicon-o-calculator')
                    ->requiresConfirmation()
                    ->action(function () {
                        $this->record->recalculatePilotCash();
                        $this->record->refresh();
                        Notification::make()->success()->title('Obliczono gotówkę pilota')->send();
                    }),
                Actions\Action::make('close_settlement')
                    ->label('Zamknij rozliczenie')
                    ->icon('heroicon-o-check-badge')
                    ->requiresConfirmation()
                    ->action(function () {
                        $this->record->update(['status' => 'closed', 'settled_at' => now()]);
                        Notification::make()->success()->title('Rozliczenie zamknięte')->send();
                    })
                    ->visible(fn () => in_array($this->record->status, ['active', 'pilot_settled'])),
                Actions\DeleteAction::make(),
            ])
                ->label('Operacje')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->button(),
        ];
    }
}
