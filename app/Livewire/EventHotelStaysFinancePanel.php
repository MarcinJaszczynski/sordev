<?php

namespace App\Livewire;

use App\Filament\Resources\EventResource\Concerns\InteractsWithSettlementCostDrawer;
use App\Models\Currency;
use App\Models\Event;
use App\Models\EventHotelStay;
use App\Models\EventSettlementCost;
use App\Services\EventHotelOccupancyService;
use App\Services\EventHotelPlanService;
use App\Services\HotelStayReservationSync;
use App\Services\HotelStaySettlementSync;
use App\Services\ProgramPointListFinanceDisplay;
use App\Services\ProgramPointSettlementCostCache;
use App\Support\EventHotelPlanFormatting;
use Filament\Notifications\Notification;
use Livewire\Component;
use Livewire\WithFileUploads;

class EventHotelStaysFinancePanel extends Component
{
    use InteractsWithSettlementCostDrawer;
    use WithFileUploads;

    public int $eventId;

    /** @var 'default'|'overview' */
    public string $variant = 'default';

    protected ?ProgramPointSettlementCostCache $settlementCostCache = null;

    /** @var array<int, array<string, mixed>> */
    protected array $stayFinanceViewDataCache = [];

    /** @var array<int, array<string, mixed>> */
    protected array $hotelGroupFinanceViewDataCache = [];

    public function mount(int $eventId, string $variant = 'default'): void
    {
        $this->eventId = $eventId;
        $this->variant = in_array($variant, ['default', 'overview'], true) ? $variant : 'default';
        $this->initializeSettlementCostDrawerForms();

        $event = Event::query()->findOrFail($eventId);

        try {
            app(EventHotelPlanService::class)->linkStaysToProgramPoints($event);
            app(HotelStaySettlementSync::class)->syncForEvent($event);
        } catch (\Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Nie udało się zsynchronizować finansów hotelowych')
                ->body('Odśwież stronę. Jeśli problem się powtarza, skontaktuj się z biurem.')
                ->warning()
                ->send();
        }
    }

    public function getRecord(): Event
    {
        return Event::query()
            ->with([
                'hotelStays.contractor',
                'hotelStays.programPoint',
                'hotelStays.roomLines',
                'hotelStays.event',
                'hotelStays.reservation',
                'activeSettlement.costs',
            ])
            ->findOrFail($this->eventId);
    }

    protected function settlementCostEvent(): Event
    {
        return $this->getRecord();
    }

    protected function invalidateSettlementCostCaches(): void
    {
        \App\Services\EventFinanceOverviewService::forgetOverviewCacheForEvent((int) $this->getRecord()->id);
        unset($this->selectedRow);
        $this->settlementCostCache = null;
        $this->stayFinanceViewDataCache = [];
        $this->hotelGroupFinanceViewDataCache = [];
        $this->dispatchSettlementFinanceChanged();
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

        $eventMode = $stay->event?->hotel_pricing_mode ?? 'lines';
        $stay->loadMissing(['roomLines', 'event']);
        $currencies = Currency::query()->pluck('symbol', 'id');
        $peoplePerNight = (int) app(EventHotelOccupancyService::class)
            ->forEvent($this->getRecord())['required_beds_per_night'];
        $stayTotal = EventHotelPlanFormatting::stayTotalDisplay(
            [
                'pricing_mode' => $stay->pricing_mode ?? 'lines',
                'flat_amount' => $stay->flat_amount,
                'flat_currency_id' => $stay->flat_currency_id,
                'flat_convert_to_pln' => $stay->flat_convert_to_pln,
                'room_lines' => $stay->roomLines->map(static fn ($line): array => [
                    'hotel_room_id' => $line->hotel_room_id,
                    'label' => $line->label,
                    'quantity' => $line->quantity,
                    'people_count' => $line->people_count,
                    'unit_price' => $line->unit_price,
                    'price_basis' => $line->price_basis,
                    'currency_id' => $line->currency_id,
                    'convert_to_pln' => $line->convert_to_pln,
                ])->all(),
            ],
            $eventMode,
            $currencies,
            $peoplePerNight,
        );

        $point = $stay->programPoint;
        $hotelCost = filled($stay->contractor_id) || $stay->exists
            ? app(HotelStaySettlementSync::class)->findForStay($this->getRecord(), $stay)
            : null;

        if ($hotelCost instanceof EventSettlementCost) {
            $allCosts = $this->getRecord()->activeSettlement?->costs ?? collect();
            if ($allCosts->isEmpty()) {
                $settlement = \App\Models\EventSettlement::findActiveForEvent($this->getRecord());
                $allCosts = $settlement?->costs ?? collect();
            }

            $health = app(\App\Services\SettlementPaymentHealthService::class);
            $plannedPln = $health->plannedPlnForCost($hotelCost);
            $paidPln = $health->paidPlnForPlanCost($hotelCost, $allCosts);
            $statusRaw = $hotelCost->payment_status;

            return $this->stayFinanceViewDataCache[$id] = [
                'planned' => $plannedPln > 0 ? number_format($plannedPln, 2, ',', ' ').' zł' : '—',
                'paid' => $paidPln > 0 ? number_format($paidPln, 2, ',', ' ').' zł' : '—',
                'statusLabel' => $statusRaw
                    ? (EventSettlementCost::$paymentStatuses[$statusRaw] ?? $statusRaw)
                    : '—',
                'statusColor' => match ($statusRaw) {
                    'paid' => 'success',
                    'partially_paid', 'advance_paid' => 'warning',
                    default => 'gray',
                },
                'stayTotal' => $stayTotal,
                'cost_id' => (int) $hotelCost->id,
            ];
        }

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

    /**
     * Grupy finansowe: jeden hotel (kontrahent) = jedna rezerwacja i jeden drawer wpłat.
     *
     * @return list<array<string, mixed>>
     */
    public function hotelFinanceGroups(): array
    {
        $event = $this->getRecord();
        $syncGroups = collect(app(HotelStayReservationSync::class)->hotelGroups($event))->keyBy('contractor_id');

        $groups = [];
        foreach ($event->hotelStays->sortBy('day') as $stay) {
            $contractorId = filled($stay->contractor_id) ? (int) $stay->contractor_id : 0;
            $groupKey = $contractorId > 0 ? 'hotel:'.$contractorId : 'stay:'.$stay->id;

            if (! isset($groups[$groupKey])) {
                $syncGroup = $contractorId > 0 ? $syncGroups->get($contractorId) : null;

                $groups[$groupKey] = [
                    'key' => $groupKey,
                    'contractor_id' => $contractorId > 0 ? $contractorId : null,
                    'hotel_name' => $stay->contractor?->displayLabel() ?? $stay->contractor?->name ?? 'Hotel nie wybrany',
                    'days' => [],
                    'stay_ids' => [],
                    'reservation_confirmed' => (bool) ($syncGroup['is_confirmed'] ?? false),
                    'reservation_status' => $syncGroup['reservation']?->status ?? null,
                ];
            }

            $groups[$groupKey]['days'][] = (int) $stay->day;
            $groups[$groupKey]['stay_ids'][] = (int) $stay->id;
        }

        return array_values(array_map(function (array $group): array {
            $group['days'] = collect($group['days'])->unique()->sort()->values()->all();
            $group['days_label'] = collect($group['days'])->map(fn (int $day): string => 'D'.$day)->implode(', ');
            $group['finance'] = $this->hotelGroupFinanceViewData($group);

            return $group;
        }, $groups));
    }

    /**
     * @param  array<string, mixed>  $group
     * @return array<string, mixed>
     */
    protected function hotelGroupFinanceViewData(array $group): array
    {
        $cacheKey = (int) ($group['contractor_id'] ?? 0) ?: (int) ($group['stay_ids'][0] ?? 0);
        if (isset($this->hotelGroupFinanceViewDataCache[$cacheKey])) {
            return $this->hotelGroupFinanceViewDataCache[$cacheKey];
        }

        $event = $this->getRecord();
        $stayIds = $group['stay_ids'] ?? [];
        $stays = $event->hotelStays->whereIn('id', $stayIds)->sortBy('day')->values();

        $stayTotalParts = [];
        foreach ($stays as $stay) {
            $stayTotalParts[] = $this->stayFinanceViewData($stay)['stayTotal'] ?? '—';
        }

        app(EventHotelPlanService::class)->linkStaysToProgramPoints($event->fresh(['hotelStays.programPoint']));
        $cost = filled($group['contractor_id'] ?? null)
            ? app(HotelStaySettlementSync::class)->ensureForContractor($event, (int) $group['contractor_id'])
            : ($stays->first() ? app(HotelStaySettlementSync::class)->ensureForStay($event, $stays->first()) : null);

        if (! $cost instanceof EventSettlementCost) {
            return $this->hotelGroupFinanceViewDataCache[$cacheKey] = [
                'stayTotal' => collect($stayTotalParts)->filter(fn ($part) => $part !== '—')->unique()->implode(' + ') ?: '—',
                'planned' => '—',
                'paid' => '—',
                'statusLabel' => 'Brak kosztu',
                'statusColor' => 'gray',
                'cost_id' => null,
            ];
        }

        $settlement = $event->activeSettlement;
        $allCosts = $settlement?->costs ?? collect();
        $plannedPln = (float) ($cost->planned_amount_pln ?? $cost->planned_amount ?? 0);
        $paidPln = (float) app(\App\Services\SettlementPaymentHealthService::class)
            ->paidPlnForPlanCost($cost, $allCosts);

        $statusRaw = $cost->payment_status;
        $statusLabel = $statusRaw
            ? (EventSettlementCost::$paymentStatuses[$statusRaw] ?? $statusRaw)
            : '—';
        $statusColor = match ($statusRaw) {
            'paid' => 'success',
            'partially_paid', 'advance_paid' => 'warning',
            default => 'gray',
        };

        return $this->hotelGroupFinanceViewDataCache[$cacheKey] = [
            'stayTotal' => collect($stayTotalParts)->filter(fn ($part) => $part !== '—')->unique()->implode(' + ') ?: '—',
            'planned' => $plannedPln > 0 ? number_format($plannedPln, 2, ',', ' ').' zł' : '—',
            'paid' => $paidPln > 0 ? number_format($paidPln, 2, ',', ' ').' zł' : '—',
            'statusLabel' => $statusLabel,
            'statusColor' => $statusColor,
            'cost_id' => (int) $cost->id,
        ];
    }

    public function openStayFinance(int $stayId): void
    {
        $event = $this->getRecord();
        $stay = $event->hotelStays->firstWhere('id', $stayId);

        if (! $stay instanceof EventHotelStay) {
            Notification::make()->title('Nie znaleziono noclegu')->warning()->send();

            return;
        }

        if (filled($stay->contractor_id)) {
            $this->openHotelGroupFinance((int) $stay->contractor_id);

            return;
        }

        $this->openStayFinanceDirect($stay);
    }

    public function openHotelGroupFinance(int $contractorId): void
    {
        $event = $this->getRecord();

        app(EventHotelPlanService::class)->linkStaysToProgramPoints($event->fresh(['hotelStays.programPoint']));

        $cost = app(HotelStaySettlementSync::class)->ensureForContractor($event, $contractorId);

        if (! $cost instanceof EventSettlementCost) {
            Notification::make()
                ->title('Brak kosztów hotelu w rozliczeniu')
                ->body('Uzupełnij plan noclegów z cenami pokoi — zbiorczy koszt utworzy się automatycznie.')
                ->warning()
                ->send();

            return;
        }

        $this->openCost((int) $cost->id);
    }

    protected function openStayFinanceDirect(EventHotelStay $stay): void
    {
        $event = $this->getRecord();
        app(EventHotelPlanService::class)->linkStaysToProgramPoints($event->fresh(['hotelStays.programPoint']));

        $cost = app(HotelStaySettlementSync::class)->ensureForStay($event, $stay->fresh());

        if (! $cost instanceof EventSettlementCost) {
            Notification::make()
                ->title('Brak kosztów noclegu w rozliczeniu')
                ->body('Uzupełnij plan noclegów z przypisanym hotelem i cenami pokoi.')
                ->warning()
                ->send();

            return;
        }

        $this->openCost((int) $cost->id);
    }

    public function render()
    {
        $event = $this->getRecord();
        $stays = $event->hotelStays->sortBy('day')->values();
        $occupancyByStayId = [];

        if ($this->variant === 'overview') {
            $occupancy = app(EventHotelOccupancyService::class)->forEvent($event);
            foreach ($occupancy['stays'] as $row) {
                $occupancyByStayId[(int) ($row['id'] ?? 0)] = $row;
            }
        }

        return view('livewire.event-hotel-stays-finance-panel', [
            'event' => $event,
            'stays' => $stays,
            'variant' => $this->variant,
            'occupancyByStayId' => $occupancyByStayId,
            'hotelFinanceGroups' => $this->variant === 'overview' ? $this->hotelFinanceGroups() : [],
            'financeViewData' => fn (EventHotelStay $stay): array => $this->stayFinanceViewData($stay),
        ]);
    }
}
