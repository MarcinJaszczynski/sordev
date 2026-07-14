<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Concerns\HasEventFinanceSubNavigation;
use App\Filament\Resources\EventResource\Concerns\HasEventWorkflowContext;
use App\Services\EventPriceCalculator;
use Filament\Actions;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class EventCalculation extends Page
{
    use HasEventFinanceSubNavigation;
    use HasEventWorkflowContext;
    use InteractsWithRecord;

    protected static string $resource = EventResource::class;

    protected static string $view = 'filament.resources.event-resource.pages.event-calculation';

    protected static ?string $navigationLabel = 'Kalkulacja';

    protected static ?string $title = 'Kalkulacja imprezy';

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(static::getResource()::canEdit($this->getRecord()), 403);
        $this->record->load(['bus', 'markup', 'programPoints.templatePoint']);

        try {
            $hasVariants = $this->record->qtyVariants()->exists();
        } catch (\Throwable $e) {
            $hasVariants = false;
        }

        if (! $hasVariants) {
            $this->redirect(EventResource::getUrl('settlement-summary', ['record' => $this->record]));

            return;
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('recalculate_event')
                ->label('Przelicz')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->action(function () {
                    (new EventPriceCalculator)->calculateForEvent($this->record);
                    \Filament\Notifications\Notification::make()->title('Kalkulacja wykonana')->success()->send();
                    $this->dispatch('event-price-table-refresh');
                }),
            ActionGroup::make([
                Actions\Action::make('create_snapshot')
                    ->label('Utwórz snapshot')
                    ->icon('heroicon-o-camera')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('name')
                            ->label('Nazwa snapshotu')
                            ->required()
                            ->default('Snapshot kalkulacji '.now()->format('d.m.Y H:i')),
                        \FilamentTiptapEditor\TiptapEditor::make('description')
                            ->maxLength(500),
                    ])
                    ->action(function (array $data) {
                        $this->record->createManualSnapshot($data['name'], $data['description'] ?? null);
                        \Filament\Notifications\Notification::make()->title('Snapshot utworzony')->success()->send();
                    }),
                Actions\Action::make('export_pdf')
                    ->label('Eksport PDF')
                    ->url(fn () => route('admin.events.calculation.pdf', $this->record))
                    ->openUrlInNewTab(),
                Actions\Action::make('export_excel')
                    ->label('Eksport Excel')
                    ->url(fn () => route('admin.events.calculation.excel', $this->record))
                    ->openUrlInNewTab(),
            ])
                ->label('Więcej')
                ->icon('heroicon-o-ellipsis-vertical')
                ->color('gray')
                ->button(),
        ];
    }

    public function getWidgets(): array
    {
        return [
            \App\Filament\Resources\EventResource\Widgets\EventPriceTable::class,
        ];
    }
}
