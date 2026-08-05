<?php



namespace App\Filament\Resources\EventResource\Pages;



use App\Exports\EventSettlementReportExport;

use App\Filament\Resources\EventResource;

use App\Filament\Resources\EventResource\Concerns\HasEventFinanceSubNavigation;

use App\Filament\Resources\EventResource\Concerns\HasEventWorkflowContext;

use App\Filament\Resources\EventSettlementResource\Pages\EditEventSettlement;

use App\Models\Event;

use App\Models\EventSettlement;

use App\Services\EventSettlementReportService;

use App\Services\SettlementPayerBreakdownService;

use Filament\Actions;

use Filament\Notifications\Notification;

use Livewire\Attributes\Computed;

use Livewire\Attributes\On;

use Maatwebsite\Excel\Facades\Excel;



class ManageEventSettlementSummary extends EditEventSettlement

{

    use HasEventFinanceSubNavigation;

    use HasEventWorkflowContext {

        getWorkflowContext as protected getEventWorkflowContext;

    }



    protected static string $view = 'filament.resources.event-resource.pages.event-settlement-summary';



    protected static ?string $title = 'Podsumowanie rozliczenia';



    public Event $event;



    public function mount(int|string $record): void

    {

        $this->event = $this->resolveEvent($record);

        abort_unless(EventResource::canEdit($this->event), 403);



        $settlement = EventSettlement::findOrCreateActiveForEvent($this->event);



        parent::mount($settlement->getKey());

    }



    #[Computed]

    public function settlementDashboard(): array

    {

        $this->record->loadMissing('costs');



        return app(SettlementPayerBreakdownService::class)->forSettlement($this->record);

    }



    #[Computed]

    public function settlementReport(): array

    {

        return app(EventSettlementReportService::class)->build($this->event, $this->record);

    }



    #[Computed]

    public function marginPlanVsActual(): array

    {

        return \App\Support\EventMarginPlanVsActual::forEvent($this->event);

    }



    #[On('settlement-data-changed')]
    public function onSettlementDataChanged(): void
    {
        parent::onSettlementDataChanged();

        unset($this->settlementDashboard, $this->settlementReport, $this->marginPlanVsActual);
    }

    public function refreshPlanFromEvent(): void
    {

        $before = (float) ($this->record->planned_cost_pln ?? 0);

        $this->event->refreshActiveSettlementCosts();

        $this->record->refresh();

        $after = (float) ($this->record->planned_cost_pln ?? 0);



        unset($this->settlementDashboard, $this->settlementReport, $this->marginPlanVsActual);



        Notification::make()

            ->success()

            ->title('Plan kosztów odświeżony')

            ->body(sprintf(

                'Zaktualizowano pozycje z imprezy. Plan: %s → %s PLN.',

                number_format($before, 2, ',', ' '),

                number_format($after, 2, ',', ' '),

            ))

            ->send();

    }



    public function getWorkflowContext(): ?array

    {

        if (! isset($this->event)) {

            return null;

        }



        $originalRecord = $this->record;

        $this->record = $this->event;



        try {

            return $this->getEventWorkflowContext();

        } finally {

            $this->record = $originalRecord;

        }

    }



    protected function resolveEvent(int|string $record): Event

    {

        $event = EventResource::resolveRecordRouteBinding($record);



        abort_if($event === null, 404);



        return $event;

    }



    protected function getHeaderActions(): array

    {

        return array_merge(parent::getHeaderActions(), [

            Actions\Action::make('refresh_plan')

                ->label('Odśwież plan z imprezy')

                ->icon('heroicon-o-arrow-path')

                ->action(fn () => $this->refreshPlanFromEvent()),

            Actions\Action::make('export_settlement_report')

                ->label('Eksport raportu')

                ->icon('heroicon-o-arrow-down-tray')

                ->action(function () {

                    $filename = 'rozliczenie-'.($this->event->code ?: $this->event->id).'-'.now()->format('Y-m-d').'.xlsx';



                    return Excel::download(new EventSettlementReportExport($this->event), $filename);

                }),

        ]);

    }



    public static function getResourcePageName(): string

    {

        return 'settlement-summary';

    }



    public static function getRouteName(?string $panel = null): string

    {

        return EventResource::getRouteBaseName(panel: $panel).'.settlement-summary';

    }

}


