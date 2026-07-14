<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Support\EventListFinanceColumn;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListEvents extends ListRecords
{
    protected static string $resource = EventResource::class;

    public function getTabs(): array
    {
        // Zakładki wyłączone — renderujemy listę bez paska filtrów zakładkowych
        return [];
    }

    public function table(Table $table): Table
    {
        $table = static::getResource()::table($table);

        if ($this->activeTab === 'completed') {
            return $table->defaultSort('updated_at', 'desc')->persistFiltersInSession();
        }

        return $table->defaultSort('updated_at', 'desc')->persistFiltersInSession();
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
