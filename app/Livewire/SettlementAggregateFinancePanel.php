<?php

namespace App\Livewire;

use App\Filament\Resources\EventResource\Concerns\InteractsWithSettlementCostDrawer;
use App\Models\Event;
use App\Models\EventSettlementCost;
use App\Models\Reservation;
use App\Services\EventFinanceOverviewService;
use App\Services\SettlementAggregateFinanceService;
use App\Services\SettlementPaymentHealthService;
use App\Services\TransportContractorSettlementSync;
use Filament\Notifications\Notification;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Finanse transportu — grupy po przewoźniku + ten sam boczny drawer co Finanse / hotele.
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

        if ($this->aggregateType === 'transport') {
            app(TransportContractorSettlementSync::class)->syncForEvent($this->event());
        } else {
            app(SettlementAggregateFinanceService::class)->ensureBaseCost($this->event(), $this->aggregateType);
        }
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

    /**
     * @return list<array<string, mixed>>
     */
    public function transportFinanceGroups(): array
    {
        if ($this->aggregateType !== 'transport') {
            return [];
        }

        $event = $this->event();
        $settlement = $event->activeSettlement;
        $allCosts = $settlement?->costs ?? collect();
        $health = app(SettlementPaymentHealthService::class);

        return array_map(function (array $group) use ($allCosts, $health): array {
            /** @var EventSettlementCost|null $cost */
            $cost = $group['cost'] ?? null;
            /** @var Reservation|null $reservation */
            $reservation = $group['reservation'] ?? null;

            $plannedPln = $cost ? $health->plannedPlnForCost($cost) : 0.0;
            $paidPln = $cost ? $health->paidPlnForPlanCost($cost, $allCosts) : 0.0;
            $statusRaw = $cost?->payment_status;
            $statusLabel = $statusRaw
                ? (EventSettlementCost::$paymentStatuses[$statusRaw] ?? $statusRaw)
                : '—';
            $statusColor = match ($statusRaw) {
                'paid' => 'success',
                'partially_paid', 'advance_paid' => 'warning',
                default => 'gray',
            };

            return [
                'key' => $group['key'],
                'contractor_id' => $group['contractor_id'],
                'contractor_name' => $group['contractor_name'],
                'is_primary' => (bool) ($group['is_primary'] ?? false),
                'cost_id' => $cost?->id,
                'planned' => $plannedPln > 0 ? number_format($plannedPln, 2, ',', ' ').' zł' : '—',
                'paid' => $paidPln > 0 ? number_format($paidPln, 2, ',', ' ').' zł' : '—',
                'statusLabel' => $statusLabel,
                'statusColor' => $statusColor,
                'reservation_confirmed' => (bool) ($reservation?->confirmed_at || ($reservation?->status === 'confirmed')),
                'reservation_status' => $reservation?->status,
            ];
        }, app(TransportContractorSettlementSync::class)->financeGroups($event));
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
        EventFinanceOverviewService::forgetOverviewCacheForEvent((int) $this->settlementCostEvent()->id);
        unset($this->selectedRow, $this->drawerReservations);
        $this->dispatchSettlementFinanceChanged();
        $this->dispatch('event-price-table-refresh');
    }

    /**
     * @param  'panel'|'plan'|'advance'|'payment'|null  $focus
     */
    public function openAggregateFinance(?string $focus = 'panel'): void
    {
        if ($this->aggregateType === 'transport') {
            $groups = $this->transportFinanceGroups();
            $primary = collect($groups)->firstWhere('is_primary', true) ?? ($groups[0] ?? null);

            if ($primary && filled($primary['contractor_id'] ?? null)) {
                $this->openTransportGroupFinance((int) $primary['contractor_id'], $focus);

                return;
            }

            if ($primary && filled($primary['cost_id'] ?? null)) {
                $this->openCost((int) $primary['cost_id']);
                $this->applyFinanceFocus($focus);

                return;
            }
        }

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
        $this->applyFinanceFocus($focus);
    }

    /**
     * @param  'panel'|'plan'|'advance'|'payment'|null  $focus
     */
    public function openTransportGroupFinance(int $contractorId, ?string $focus = 'panel'): void
    {
        $cost = app(TransportContractorSettlementSync::class)
            ->ensureForContractor($this->event(), $contractorId);

        if (! $cost instanceof EventSettlementCost) {
            Notification::make()
                ->title('Brak kosztów transportu w rozliczeniu')
                ->body('Uzupełnij autokar lub ręczną kwotę transportu i zapisz imprezę.')
                ->warning()
                ->send();

            return;
        }

        $this->openCost((int) $cost->id);
        $this->applyFinanceFocus($focus);
    }

    /**
     * @param  'panel'|'plan'|'advance'|'payment'|null  $focus
     */
    protected function applyFinanceFocus(?string $focus): void
    {
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
