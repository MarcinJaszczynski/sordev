<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Models\Event;
use App\Support\EventListFinanceColumn;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

class ListEvents extends ListRecords
{
    protected static string $resource = EventResource::class;

    public function getDefaultActiveTab(): string|int|null
    {
        return 'upcoming';
    }

    public function getTabs(): array
    {
        $today = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();
        $weekEnd = now()->endOfWeek()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        return [
            'all' => Tab::make('Wszystkie'),
            'today' => Tab::make('Dziś')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereDate('start_date', $today)),
            'tomorrow' => Tab::make('Jutro')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereDate('start_date', $tomorrow)),
            'this_week' => Tab::make('Ten tydzień')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereDate('start_date', '>=', $today)
                    ->whereDate('start_date', '<=', $weekEnd)),
            'this_month' => Tab::make('Ten miesiąc')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereDate('start_date', '>=', $today)
                    ->whereDate('start_date', '<=', $monthEnd)),
            'upcoming' => Tab::make('Nadchodzące')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereDate('start_date', '>=', $today)
                    ->whereNotIn('status', [Event::STATUS_CANCELLED, Event::STATUS_SETTLED])),
            'pipeline' => Tab::make('Pipeline sprzedaży')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereIn('status', [
                        Event::STATUS_INQUIRY,
                        Event::STATUS_OFFER,
                        Event::STATUS_PROVISIONAL_RESERVATION,
                    ])),
            'to_settle' => Tab::make('Nierozliczone')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereIn('status', [Event::STATUS_TO_SETTLE, 'in_progress'])),
            'no_pilot_funds' => Tab::make('Bez wypłaty pilota')
                ->modifyQueryUsing(function (Builder $query): Builder {
                    if (! Schema::hasColumn('events', 'pilot_funds_paid')) {
                        return $query->whereRaw('0 = 1');
                    }

                    return $query
                        ->where('pilot_funds_paid', false)
                        ->whereNotNull('assigned_to')
                        ->whereIn('status', [
                            Event::STATUS_CONFIRMED,
                            Event::STATUS_TO_SETTLE,
                            Event::STATUS_SETTLED,
                        ]);
                }),
            'mine' => Tab::make('Moje')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('assigned_to', auth()->id())),
            'cancelled' => Tab::make('Anulowane')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereIn('status', [Event::STATUS_CANCELLED, Event::STATUS_PENDING_CANCELLATION])),
            'settled' => Tab::make('Zakończone')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('status', Event::STATUS_SETTLED)),
        ];
    }

    public function table(Table $table): Table
    {
        $table = static::getResource()::table($table);

        $sortColumn = $this->activeTab === 'settled' ? 'updated_at' : 'start_date';
        $sortDirection = $this->activeTab === 'settled' ? 'desc' : 'asc';

        if (in_array($this->activeTab, ['all', 'cancelled', 'to_settle', 'no_pilot_funds', 'mine'], true)) {
            $sortColumn = 'updated_at';
            $sortDirection = 'desc';
        }

        return $table
            ->defaultSort($sortColumn, $sortDirection)
            ->persistFiltersInSession();
    }

    protected function paginateTableQuery(Builder $query): Paginator|CursorPaginator
    {
        /** @var LengthAwarePaginator|Paginator|CursorPaginator $records */
        $records = parent::paginateTableQuery($query);

        if ($records instanceof LengthAwarePaginator) {
            EventListFinanceColumn::warmForPage($records->getCollection());
        }

        return $records;
    }

    protected function getTableQuery(): ?Builder
    {
        $query = parent::getTableQuery();

        if (! $query) {
            return null;
        }

        $currencyCode = EventListFinanceColumn::resolveCurrencyCode($this);

        if ($currencyCode !== 'PLN') {
            $query->withSum([
                'agreements as agreements_amount_paid_filtered' => fn (Builder $agreementsQuery) => $agreementsQuery
                    ->whereRaw('UPPER(currency) = ?', [$currencyCode]),
            ], 'amount_paid');
        }

        return $query;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nowa impreza'),
        ];
    }
}
