<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Pages\EventsSalesPipelinePage;
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

/**
 * Taby = skróty operacyjne (max ~7).
 * Ścieżka oferty: kanonicznie EventsSalesPipelinePage (link w headerze).
 * „Bez wypłaty pilota”: filtr tabeli Wypłata pilotowi.
 */
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
        $weekEnd = now()->endOfWeek()->toDateString();

        return [
            'all' => Tab::make('Wszystkie'),
            'upcoming' => Tab::make('Nadchodzące')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereDate('start_date', '>=', $today)
                    ->whereNotIn('status', [Event::STATUS_CANCELLED, Event::STATUS_SETTLED])),
            'this_week' => Tab::make('Ten tydzień')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereDate('start_date', '>=', $today)
                    ->whereDate('start_date', '<=', $weekEnd)),
            'to_settle' => Tab::make('Nierozliczone')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereIn('status', [Event::STATUS_TO_SETTLE, 'in_progress'])),
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

        if (in_array($this->activeTab, ['all', 'cancelled', 'to_settle', 'mine'], true)) {
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
            Actions\Action::make('sales_pipeline')
                ->label('Ścieżka oferty')
                ->icon('heroicon-o-view-columns')
                ->color('gray')
                ->tooltip('Tablica sprzedaży: zapytanie → oferta → rezerwacja wstępna')
                ->url(EventsSalesPipelinePage::getUrl()),
            Actions\CreateAction::make()
                ->label('Nowa impreza'),
        ];
    }
}
