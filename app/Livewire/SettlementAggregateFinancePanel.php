<?php

namespace App\Livewire;

use App\Filament\Resources\EventResource\Concerns\InteractsWithSettlementCostDrawer;
use App\Models\Event;
use App\Models\EventSettlementCost;
use App\Services\SettlementAggregateFinanceService;
use Filament\Notifications\Notification;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Finanse agregatu (transport / legacy accommodation) — ten sam boczny drawer co Finanse / hotele.
 */
class SettlementAggregateFinancePanel extends Component
{
    use InteractsWithSettlementCostDrawer;
    use WithFileUploads;

    public int $eventId;

    /** @var 'transport'|'accommodation' */
    public string $aggregateType;

    public string $heading;

    public function mount(int $eventId, string $aggregateType, ?string $heading = null): void
    {
        $this->eventId = $eventId;
        $this->aggregateType = $aggregateType;
        $this->heading = $heading ?? match ($aggregateType) {
            'transport' => 'Finanse transportu',
            'accommodation' => 'Finanse noclegu',
            default => 'Finanse',
        };

        $this->initializeSettlementCostDrawerForms();
        app(SettlementAggregateFinanceService::class)->ensureBaseCost($this->event(), $this->aggregateType);
    }

    public function render()
    {
        return view('livewire.settlement-aggregate-finance-panel');
    }

    /**
     * @return array{planned: string, paid: string, remaining: string, status: string}
     */
    public function summary(): array
    {
        return app(SettlementAggregateFinanceService::class)
            ->summary($this->event(), $this->aggregateType);
    }

    public function getRecord(): Event
    {
        return $this->event();
    }

    protected function settlementCostEvent(): Event
    {
        return $this->event();
    }

    protected function invalidateSettlementCostCaches(): void
    {
        unset($this->selectedRow, $this->drawerReservations);
        $this->dispatchSettlementFinanceChanged();
        $this->dispatch('event-price-table-refresh');
    }

    /**
     * @param  'panel'|'plan'|'advance'|'payment'|null  $focus
     */
    public function openAggregateFinance(?string $focus = 'panel'): void
    {
        $cost = app(SettlementAggregateFinanceService::class)
            ->ensureBaseCost($this->event(), $this->aggregateType);

        if (! $cost instanceof EventSettlementCost) {
            Notification::make()
                ->title('Brak pozycji transportu w rozliczeniu')
                ->body('Uzupełnij autokar lub włącz ręczną kwotę transportu i zapisz imprezę — wtedy pojawi się koszt do płatności.')
                ->warning()
                ->send();

            return;
        }

        $this->openCost((int) $cost->id);

        match ($focus) {
            'plan' => $this->startEditPlan(),
            'advance' => $this->startAddAdvance(),
            'payment' => $this->startAddPayment(),
            default => null,
        };
    }

    protected function event(): Event
    {
        return Event::query()->findOrFail($this->eventId);
    }
}
