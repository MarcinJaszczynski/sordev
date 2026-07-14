<?php

namespace App\Filament\Pages;

use App\Models\EventSettlementCost;
use App\Support\FilamentNavigation;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Number;

class ExpensesAnalytics extends Page implements HasTable
{
    use InteractsWithTable;

    private const PAGE_FILTER_FIELDS = [
        'selectedDateFrom',
        'selectedDateTo',
        'selectedPaymentStatus',
        'selectedContractor',
        'selectedEvent',
        'selectedPaidBy',
    ];

    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $navigationLabel = 'Raport wydatków';

    protected static string $view = 'filament.pages.expenses-analytics';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_FINANCE;

    protected static ?int $navigationSort = 8;

    public ?string $selectedDateFrom = null;

    public ?string $selectedDateTo = null;

    public ?string $selectedPaymentStatus = null;

    public ?string $selectedContractor = null;

    public ?string $selectedEvent = null;

    public ?string $selectedPaidBy = null;

    public function mount(): void
    {
        $state = session()->get($this->filtersSessionKey(), []);

        $this->selectedDateFrom = $state['selectedDateFrom'] ?? now()->subMonth()->toDateString();
        $this->selectedDateTo = $state['selectedDateTo'] ?? now()->toDateString();
        $this->selectedPaymentStatus = $state['selectedPaymentStatus'] ?? null;
        $this->selectedContractor = $state['selectedContractor'] ?? null;
        $this->selectedEvent = $state['selectedEvent'] ?? null;
        $this->selectedPaidBy = $state['selectedPaidBy'] ?? null;
    }

    public function updated(string $name): void
    {
        if (! in_array($name, self::PAGE_FILTER_FIELDS, true)) {
            return;
        }

        $this->persistCurrentFiltersState();
    }

    public function resetLayoutState(): void
    {
        session()->forget([
            $this->filtersSessionKey(),
            $this->getTableFiltersSessionKey(),
            $this->getTableSearchSessionKey(),
            $this->getTableColumnSearchesSessionKey(),
            $this->getTableSortSessionKey(),
            $this->getTableColumnToggleFormStateSessionKey(),
        ]);

        $this->selectedDateFrom = now()->subMonth()->toDateString();
        $this->selectedDateTo = now()->toDateString();
        $this->selectedPaymentStatus = null;
        $this->selectedContractor = null;
        $this->selectedEvent = null;
        $this->selectedPaidBy = null;

        $this->tableSortColumn = null;
        $this->tableSortDirection = null;
        $this->tableSearch = '';
        $this->tableColumnSearches = [];
        $this->tableFilters = [];
        $this->tableDeferredFilters = [];

        $this->toggledTableColumns = $this->getDefaultTableColumnToggleState();

        $this->persistCurrentFiltersState();

        $this->resetTable();
    }

    public function getStats(): array
    {
        $query = EventSettlementCost::query()
            ->paymentsOnly()
            ->when($this->selectedDateFrom, fn ($q) => $q->whereDate('event_settlement_costs.created_at', '>=', $this->selectedDateFrom))
            ->when($this->selectedDateTo, fn ($q) => $q->whereDate('event_settlement_costs.created_at', '<=', $this->selectedDateTo))
            ->when($this->selectedPaymentStatus, fn ($q) => $q->where('payment_status', $this->selectedPaymentStatus))
            ->when($this->selectedContractor, fn ($q) => $q->where('contractor_id', $this->selectedContractor))
            ->when($this->selectedEvent, fn ($q) => $q->whereHas('settlement', fn ($q) => $q->where('event_id', $this->selectedEvent)))
            ->when($this->selectedPaidBy, fn ($q) => $q->where('paid_by', $this->selectedPaidBy));

        $totalPlanned = (clone $query)->sum('planned_amount_pln') ?? 0;
        $totalActual = (clone $query)->sum('actual_amount_pln') ?? 0;
        $totalAdvance = (clone $query)->sum('advance_amount') ?? 0;
        $totalDiff = $totalActual - $totalPlanned;
        $paid = (clone $query)->where('payment_status', 'paid')->count();
        $pending = (clone $query)->whereNotIn('payment_status', ['paid', 'cancelled'])->count();
        $cancelled = (clone $query)->where('payment_status', 'cancelled')->count();

        return [
            Stat::make('Razem wydatków (plan)', Number::currency($totalPlanned, 'PLN'))
                ->description('Zaplanowanych')
                ->icon('heroicon-o-calculator')
                ->color('info'),

            Stat::make('Razem wydatków (rzeczywiste)', Number::currency($totalActual, 'PLN'))
                ->description('Zapłaconych')
                ->icon('heroicon-o-calculator')
                ->color('primary'),

            Stat::make('Różnica', Number::currency(abs($totalDiff), 'PLN'))
                ->description($totalDiff < 0 ? 'Mniej wydane ✓' : 'Więcej wydane ⚠')
                ->icon($totalDiff < 0 ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-circle')
                ->color($totalDiff < 0 ? 'success' : 'warning'),

            Stat::make('Razem zaliczek', Number::currency($totalAdvance, 'PLN'))
                ->description('Zaliczone w rozliczeniu')
                ->icon('heroicon-o-calculator')
                ->color('secondary'),

            Stat::make('Zapłacone', $paid)
                ->description('Pozycji')
                ->icon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Oczekujące', $pending)
                ->description('Pozycji')
                ->icon('heroicon-o-clock')
                ->color('warning'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                EventSettlementCost::query()
                    ->when($this->selectedDateFrom, fn ($q) => $q->whereDate('event_settlement_costs.created_at', '>=', $this->selectedDateFrom))
                    ->when($this->selectedDateTo, fn ($q) => $q->whereDate('event_settlement_costs.created_at', '<=', $this->selectedDateTo))
                    ->when($this->selectedPaymentStatus, fn ($q) => $q->where('payment_status', $this->selectedPaymentStatus))
                    ->when($this->selectedContractor, fn ($q) => $q->where('contractor_id', $this->selectedContractor))
                    ->when($this->selectedEvent, fn ($q) => $q->whereHas('settlement', fn ($q) => $q->where('event_id', $this->selectedEvent)))
                    ->when($this->selectedPaidBy, fn ($q) => $q->where('paid_by', $this->selectedPaidBy))
            )
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->width(60),

                Tables\Columns\TextColumn::make('name')
                    ->label('Pozycja')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('settlement.event.name')
                    ->label('Impreza')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('contractor.name')
                    ->label('Kontraktor')
                    ->sortable()
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('planned_amount_pln')
                    ->label('Plan PLN')
                    ->money('PLN')
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('actual_amount_pln')
                    ->label('Rzeczywiste PLN')
                    ->money('PLN')
                    ->sortable()
                    ->alignEnd()
                    ->color(fn ($state) => $state === null ? 'gray' : null),

                Tables\Columns\TextColumn::make('diff_pln')
                    ->label('Różnica PLN')
                    ->state(fn ($record) => $record->actual_amount_pln - $record->planned_amount_pln)
                    ->numeric(2)
                    ->suffix(' PLN')
                    ->alignEnd()
                    ->color(fn ($state) => $state > 0 ? 'danger' : ($state < 0 ? 'success' : 'gray')),

                Tables\Columns\BadgeColumn::make('payment_status')
                    ->label('Status płatności')
                    ->formatStateUsing(fn ($state) => EventSettlementCost::$paymentStatuses[$state] ?? $state)
                    ->colors([
                        'gray' => 'planned',
                        'warning' => ['reservation_required', 'advance_required', 'advance_paid', 'partially_paid'],
                        'success' => 'paid',
                        'danger' => 'cancelled',
                    ]),

                Tables\Columns\BadgeColumn::make('approval_status')
                    ->label('Kontrola')
                    ->formatStateUsing(fn (?string $state) => EventSettlementCost::$approvalStatuses[$state ?? 'pending'] ?? $state)
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('payment_status')
                    ->label('Status płatności')
                    ->options(EventSettlementCost::$paymentStatuses),

                Tables\Filters\SelectFilter::make('approval_status')
                    ->label('Status kontroli')
                    ->options(EventSettlementCost::$approvalStatuses),

                Tables\Filters\SelectFilter::make('advance_type')
                    ->label('Typ płatności')
                    ->options(EventSettlementCost::$advanceTypes),

                Tables\Filters\SelectFilter::make('contractor_id')
                    ->label('Kontraktor')
                    ->relationship('contractor', 'name'),

                Tables\Filters\SelectFilter::make('paid_by')
                    ->label('Płaci')
                    ->options(EventSettlementCost::$paidByOptions),

                Tables\Filters\Filter::make('created_at')
                    ->label('Data utworzenia')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Od'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Do'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $q, $date): Builder => $q->whereDate('event_settlement_costs.created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $q, $date): Builder => $q->whereDate('event_settlement_costs.created_at', '<=', $date),
                            );
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    public function getExpensesByStatusData(): array
    {
        $statuses = EventSettlementCost::$paymentStatuses;
        $counts = [];

        $query = EventSettlementCost::query()
            ->paymentsOnly()
            ->when($this->selectedDateFrom, fn ($q) => $q->whereDate('event_settlement_costs.created_at', '>=', $this->selectedDateFrom))
            ->when($this->selectedDateTo, fn ($q) => $q->whereDate('event_settlement_costs.created_at', '<=', $this->selectedDateTo))
            ->when($this->selectedContractor, fn ($q) => $q->where('contractor_id', $this->selectedContractor))
            ->when($this->selectedEvent, fn ($q) => $q->whereHas('settlement', fn ($q) => $q->where('event_id', $this->selectedEvent)))
            ->when($this->selectedPaidBy, fn ($q) => $q->where('paid_by', $this->selectedPaidBy));

        foreach (array_keys($statuses) as $status) {
            $counts[$status] = (clone $query)->where('payment_status', $status)->count();
        }

        return $counts;
    }

    public function getExpensesByContractorData(): array
    {
        $data = EventSettlementCost::query()
            ->when($this->selectedDateFrom, fn ($q) => $q->whereDate('event_settlement_costs.created_at', '>=', $this->selectedDateFrom))
            ->when($this->selectedDateTo, fn ($q) => $q->whereDate('event_settlement_costs.created_at', '<=', $this->selectedDateTo))
            ->when($this->selectedPaymentStatus, fn ($q) => $q->where('payment_status', $this->selectedPaymentStatus))
            ->when($this->selectedEvent, fn ($q) => $q->whereHas('settlement', fn ($q) => $q->where('event_id', $this->selectedEvent)))
            ->when($this->selectedPaidBy, fn ($q) => $q->where('paid_by', $this->selectedPaidBy))
            ->join('contractors', 'event_settlement_costs.contractor_id', '=', 'contractors.id')
            ->selectRaw('contractors.name, COUNT(*) as count, SUM(actual_amount_pln) as total')
            ->groupBy('contractors.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->pluck('total', 'name')
            ->toArray();

        return $data;
    }

    public function getExpensesByDateData(): array
    {
        $data = EventSettlementCost::query()
            ->when($this->selectedDateFrom, fn ($q) => $q->whereDate('event_settlement_costs.created_at', '>=', $this->selectedDateFrom))
            ->when($this->selectedDateTo, fn ($q) => $q->whereDate('event_settlement_costs.created_at', '<=', $this->selectedDateTo))
            ->when($this->selectedPaymentStatus, fn ($q) => $q->where('payment_status', $this->selectedPaymentStatus))
            ->when($this->selectedContractor, fn ($q) => $q->where('contractor_id', $this->selectedContractor))
            ->when($this->selectedEvent, fn ($q) => $q->whereHas('settlement', fn ($q) => $q->where('event_id', $this->selectedEvent)))
            ->when($this->selectedPaidBy, fn ($q) => $q->where('paid_by', $this->selectedPaidBy))
            ->selectRaw('DATE(event_settlement_costs.created_at) as date, COUNT(*) as count, SUM(actual_amount_pln) as total')
            ->groupByRaw('DATE(event_settlement_costs.created_at)')
            ->orderBy('date')
            ->get()
            ->pluck('total', 'date')
            ->toArray();

        return $data;
    }

    public function getExpensesByPaidByData(): array
    {
        $options = EventSettlementCost::$paidByOptions;
        $data = [];

        $query = EventSettlementCost::query()
            ->paymentsOnly()
            ->when($this->selectedDateFrom, fn ($q) => $q->whereDate('event_settlement_costs.created_at', '>=', $this->selectedDateFrom))
            ->when($this->selectedDateTo, fn ($q) => $q->whereDate('event_settlement_costs.created_at', '<=', $this->selectedDateTo))
            ->when($this->selectedPaymentStatus, fn ($q) => $q->where('payment_status', $this->selectedPaymentStatus))
            ->when($this->selectedContractor, fn ($q) => $q->where('contractor_id', $this->selectedContractor))
            ->when($this->selectedEvent, fn ($q) => $q->whereHas('settlement', fn ($q) => $q->where('event_id', $this->selectedEvent)));

        foreach (array_keys($options) as $paidBy) {
            $data[$paidBy] = (clone $query)->where('paid_by', $paidBy)->sum('actual_amount_pln') ?? 0;
        }

        return array_filter($data);
    }

    private function filtersSessionKey(): string
    {
        return static::class.'.filters';
    }

    private function persistCurrentFiltersState(): void
    {
        session()->put($this->filtersSessionKey(), [
            'selectedDateFrom' => $this->selectedDateFrom,
            'selectedDateTo' => $this->selectedDateTo,
            'selectedPaymentStatus' => $this->selectedPaymentStatus,
            'selectedContractor' => $this->selectedContractor,
            'selectedEvent' => $this->selectedEvent,
            'selectedPaidBy' => $this->selectedPaidBy,
        ]);
    }
}
