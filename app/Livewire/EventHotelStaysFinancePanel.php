<?php

namespace App\Livewire;

use App\Filament\Resources\EventResource\Concerns\InteractsWithSettlementCostDrawer;
use App\Models\Event;
use App\Models\EventHotelStay;
use App\Models\EventProgramPoint;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Services\EventHotelPlanService;
use App\Services\ProgramPointListFinanceDisplay;
use App\Services\ProgramPointSettlementCostCache;
use App\Support\MoneyFormatter;
use Filament\Notifications\Notification;
use Livewire\Component;
use Livewire\WithFileUploads;

class EventHotelStaysFinancePanel extends Component
{
    use InteractsWithSettlementCostDrawer;
    use WithFileUploads;

    public int $eventId;

    /** @var ProgramPointSettlementCostCache|null */
    protected ?ProgramPointSettlementCostCache $settlementCostCache = null;

    /** @var array<int, array<string, mixed>> */
    protected array $stayFinanceViewDataCache = [];

    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;
        $this->initializeSettlementCostDrawerForms();

        $event = Event::query()->findOrFail($eventId);
        app(EventHotelPlanService::class)->linkStaysToProgramPoints($event);
    }

    public function getRecord(): Event
    {
        return Event::query()
            ->with(['hotelStays.contractor', 'hotelStays.programPoint', 'hotelStays.roomLines', 'hotelStays.event'])
            ->findOrFail($this->eventId);
    }

    protected function settlementCostEvent(): Event
    {
        return $this->getRecord();
    }

    protected function invalidateSettlementCostCaches(): void
    {
        unset($this->selectedRow);
        $this->settlementCostCache = null;
        $this->stayFinanceViewDataCache = [];
    }

    protected function settlementCosts(): ProgramPointSettlementCostCache
    {
        if ($this->settlementCostCache === null) {
            $this->settlementCostCache = new ProgramPointSettlementCostCache;
            $event = $this->getRecord();
            $points = $event->hotelStays
                ->map(fn (EventHotelStay $stay) => $stay->programPoint)
                ->filter()
                ->values();
            $this->settlementCostCache->warm($points, $event);
        }

        return $this->settlementCostCache;
    }

    /**
     * @return array<string, mixed>
     */
    protected function stayFinanceViewData(EventHotelStay $stay): array
    {
        $id = (int) $stay->id;
        if (isset($this->stayFinanceViewDataCache[$id])) {
            return $this->stayFinanceViewDataCache[$id];
        }

        $eventMode = $stay->event->hotel_pricing_mode ?? 'lines';
        $stayTotal = MoneyFormatter::format($stay->totalPln($eventMode), 'PLN');

        $point = $stay->programPoint;
        if (! $point) {
            return $this->stayFinanceViewDataCache[$id] = [
                'planned' => '—',
                'paid' => '—',
                'statusLabel' => 'Brak punktu programu',
                'statusColor' => 'gray',
                'stayTotal' => $stayTotal,
            ];
        }

        $summary = app(ProgramPointListFinanceDisplay::class)->summarizePoint(
            $point,
            $this->settlementCosts(),
            max(1, (int) ($this->getRecord()->participant_count ?? 1)),
        );
        $baseCost = $this->settlementCosts()->baseCost((int) $point->id);
        $statusRaw = $baseCost?->payment_status;
        $summary['statusLabel'] = $statusRaw
            ? (EventSettlementCost::$paymentStatuses[$statusRaw] ?? $statusRaw)
            : '—';
        $summary['statusColor'] = match ($statusRaw) {
            'paid' => 'success',
            'partially_paid', 'advance_paid' => 'warning',
            default => 'gray',
        };
        $summary['stayTotal'] = $stayTotal;

        return $this->stayFinanceViewDataCache[$id] = $summary;
    }

    public function openStayFinance(int $stayId): void
    {
        $event = $this->getRecord();
        $stay = $event->hotelStays->firstWhere('id', $stayId);

        if (! $stay instanceof EventHotelStay) {
            Notification::make()->title('Nie znaleziono noclegu')->warning()->send();

            return;
        }

        app(EventHotelPlanService::class)->linkStaysToProgramPoints($event->fresh(['hotelStays.programPoint']));
        $stay = $stay->fresh(['programPoint']);

        $point = $stay->programPoint;
        if (! $point instanceof EventProgramPoint) {
            Notification::make()
                ->title('Brak punktu programu hotelu')
                ->body('Zapisz plan noclegów z przypisanym hotelem — powiązanie utworzy się automatycznie.')
                ->warning()
                ->send();

            return;
        }

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $cost = $settlement->upsertCostFromProgramPoint(
            $point->loadMissing('templatePoint', 'currency', 'event', 'reservations'),
        );

        $this->openCost((int) $cost->id);
    }

    public function render()
    {
        $event = $this->getRecord();

        return view('livewire.event-hotel-stays-finance-panel', [
            'event' => $event,
            'stays' => $event->hotelStays->sortBy('day')->values(),
            'financeViewData' => fn (EventHotelStay $stay): array => $this->stayFinanceViewData($stay),
        ]);
    }
}
