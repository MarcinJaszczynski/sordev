<?php

namespace App\Filament\Pages;

use App\Models\Reservation;
use App\Support\ExecutiveAccess;
use App\Support\FilamentNavigation;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class ReservationsAnalytics extends Page implements HasTable
{
    use InteractsWithTable;

    private const PAGE_FILTER_FIELDS = [
        'selectedDateFrom',
        'selectedDateTo',
        'selectedStatus',
        'selectedContractor',
        'selectedEvent',
    ];

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Raport rezerwacji';

    protected static string $view = 'filament.pages.reservations-analytics';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_OPERATIONS;

    protected static ?int $navigationSort = 7;

    public static function canAccess(): bool
    {
        return ExecutiveAccess::canAccessSensitiveAnalytics();
    }

    public ?string $selectedDateFrom = null;

    public ?string $selectedDateTo = null;

    public ?string $selectedStatus = null;

    public ?string $selectedContractor = null;

    public ?string $selectedEvent = null;

    public function mount(): void
    {
        $state = session()->get($this->filtersSessionKey(), []);

        $this->selectedDateFrom = $state['selectedDateFrom'] ?? now()->subMonth()->toDateString();
        $this->selectedDateTo = $state['selectedDateTo'] ?? now()->toDateString();
        $this->selectedStatus = $state['selectedStatus'] ?? null;
        $this->selectedContractor = $state['selectedContractor'] ?? null;
        $this->selectedEvent = $state['selectedEvent'] ?? null;
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
        $this->selectedStatus = null;
        $this->selectedContractor = null;
        $this->selectedEvent = null;

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
        $summary = $this->baseQuery()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status IN ('confirmed', 'partially_confirmed', 'completed') THEN 1 ELSE 0 END) as confirmed")
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending")
            ->selectRaw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled")
            ->selectRaw("SUM(CASE WHEN status = 'not_required' THEN 1 ELSE 0 END) as not_required")
            ->selectRaw('COALESCE(SUM(reserved_amount), 0) as total_amount')
            ->selectRaw('COALESCE(AVG(participant_count), 0) as avg_participants')
            ->first();

        $total = (int) ($summary?->total ?? 0);
        $confirmed = (int) ($summary?->confirmed ?? 0);
        $pending = (int) ($summary?->pending ?? 0);
        $cancelled = (int) ($summary?->cancelled ?? 0);
        $notRequired = (int) ($summary?->not_required ?? 0);
        $totalAmount = (float) ($summary?->total_amount ?? 0);
        $avgAmount = $total > 0 ? $totalAmount / $total : 0;
        $avgParticipants = (float) ($summary?->avg_participants ?? 0);

        return [
            Stat::make('Razem rezerwacji', $total)
                ->description('W wybranym okresie')
                ->icon('heroicon-o-calendar-days')
                ->color('info'),

            Stat::make('Potwierdzone', $confirmed)
                ->description($total > 0 ? round(($confirmed / $total) * 100).'%' : '0%')
                ->icon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Oczekujące', $pending)
                ->description($total > 0 ? round(($pending / $total) * 100).'%' : '0%')
                ->icon('heroicon-o-clock')
                ->color('warning'),

            Stat::make('Anulowane', $cancelled)
                ->description($total > 0 ? round(($cancelled / $total) * 100).'%' : '0%')
                ->icon('heroicon-o-x-circle')
                ->color('danger'),

            Stat::make('Nie wymaga', $notRequired)
                ->description($total > 0 ? round(($notRequired / $total) * 100).'%' : '0%')
                ->icon('heroicon-o-minus-circle')
                ->color('gray'),

            Stat::make('Łączna kwota', Number::currency($totalAmount, 'PLN'))
                ->description('Zarezerwowanych środków')
                ->icon('heroicon-o-calculator')
                ->color('primary'),

            Stat::make('Średnia rezerwacja', Number::currency($avgAmount, 'PLN'))
                ->description(round($avgParticipants).' osób średnio')
                ->icon('heroicon-o-chart-pie')
                ->color('secondary'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->baseQuery())
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->width(60),

                Tables\Columns\TextColumn::make('booking_reference')
                    ->label('Nr rezerwacji')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('event.name')
                    ->label('Impreza')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('contractor.name')
                    ->label('Kontrahent')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('participant_count')
                    ->label('Uczestnicy')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('reserved_amount')
                    ->label('Kwota')
                    ->money('PLN')
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => Reservation::$statuses[$state] ?? $state)
                    ->colors([
                        'gray' => 'pending',
                        'warning' => 'partially_confirmed',
                        'success' => ['confirmed', 'completed'],
                        'danger' => 'cancelled',
                        'info' => 'not_required',
                    ]),

                Tables\Columns\TextColumn::make('reserved_at')
                    ->label('Data rezerwacji')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Wygasa')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(Reservation::$statuses),

                Tables\Filters\SelectFilter::make('contractor_id')
                    ->label('Kontrahent')
                    ->relationship('contractor', 'name'),

                Tables\Filters\SelectFilter::make('event_id')
                    ->label('Impreza')
                    ->relationship('event', 'name'),

                Tables\Filters\Filter::make('reserved_at')
                    ->label('Data rezerwacji')
                    ->form([
                        Forms\Components\DatePicker::make('reserved_from')
                            ->label('Od'),
                        Forms\Components\DatePicker::make('reserved_until')
                            ->label('Do'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['reserved_from'],
                                fn (Builder $q, $date) => $q->whereDate('reserved_at', '>=', $date),
                            )
                            ->when(
                                $data['reserved_until'],
                                fn (Builder $q, $date) => $q->whereDate('reserved_at', '<=', $date),
                            );
                    }),

                Tables\Filters\Filter::make('expires_at')
                    ->label('Data wygaśnięcia')
                    ->form([
                        Forms\Components\DatePicker::make('expires_from')
                            ->label('Od'),
                        Forms\Components\DatePicker::make('expires_until')
                            ->label('Do'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['expires_from'],
                                fn (Builder $q, $date) => $q->whereDate('expires_at', '>=', $date),
                            )
                            ->when(
                                $data['expires_until'],
                                fn (Builder $q, $date) => $q->whereDate('expires_at', '<=', $date),
                            );
                    }),
            ])
            ->defaultSort('reserved_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    public function getReservationsByStatusData(): array
    {
        $statuses = Reservation::$statuses;
        $counts = array_fill_keys(array_keys($statuses), 0);

        // Dla wykresu statusów celowo nie zawężamy po selectedStatus.
        $grouped = $this->baseQuery(applyStatus: false)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        foreach ($grouped as $status => $count) {
            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int) $count;
            }
        }

        return $counts;
    }

    public function getReservationsByDateData(): array
    {
        $data = $this->baseQuery()
            ->selectRaw('DATE(reserved_at) as date, COUNT(*) as count, SUM(reserved_amount) as total')
            ->groupByRaw('DATE(reserved_at)')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date')
            ->toArray();

        return $data;
    }

    public function getReservationsByDateChartData(): array
    {
        $rows = $this->baseQuery()
            ->selectRaw('DATE(reserved_at) as date, COUNT(*) as count, COALESCE(SUM(reserved_amount), 0) as total')
            ->groupByRaw('DATE(reserved_at)')
            ->orderBy('date')
            ->get();

        return [
            'labels' => $rows->pluck('date')->map(fn ($date) => (string) $date)->values()->all(),
            'counts' => $rows->pluck('count')->map(fn ($count) => (int) $count)->values()->all(),
            'totals' => $rows->pluck('total')->map(fn ($total) => (float) $total)->values()->all(),
        ];
    }

    public function getReservationsByContractorData(): array
    {
        $data = $this->baseQuery(applyContractor: false)
            ->join('contractors', 'reservations.contractor_id', '=', 'contractors.id')
            ->selectRaw('contractors.name, COUNT(*) as count, SUM(reserved_amount) as total')
            ->groupBy('contractors.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->pluck('total', 'name')
            ->toArray();

        return $data;
    }

    private function baseQuery(bool $applyStatus = true, bool $applyContractor = true): Builder
    {
        return Reservation::query()
            ->when($this->selectedDateFrom, fn (Builder $q) => $q->whereDate('reserved_at', '>=', $this->selectedDateFrom))
            ->when($this->selectedDateTo, fn (Builder $q) => $q->whereDate('reserved_at', '<=', $this->selectedDateTo))
            ->when($applyStatus && $this->selectedStatus, fn (Builder $q) => $q->where('status', $this->selectedStatus))
            ->when($applyContractor && $this->selectedContractor, fn (Builder $q) => $q->where('contractor_id', $this->selectedContractor))
            ->when($this->selectedEvent, fn (Builder $q) => $q->where('event_id', $this->selectedEvent));
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
            'selectedStatus' => $this->selectedStatus,
            'selectedContractor' => $this->selectedContractor,
            'selectedEvent' => $this->selectedEvent,
        ]);
    }
}
