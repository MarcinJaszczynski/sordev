<?php

namespace App\Filament\Forms;

use Filament\Forms;
use Filament\Forms\Get;
use Illuminate\Support\Facades\Schema;

class EventTransportFields
{
    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function manualTransportCostFields(): array
    {
        if (! Schema::hasColumn('events', 'use_manual_transport_cost')) {
            return [];
        }

        return [
            Forms\Components\Toggle::make('use_manual_transport_cost')
                ->label('Ustal ręcznie koszt transportu (ryczałt)')
                ->default(false)
                ->live()
                ->afterStateUpdated(fn ($livewire) => $livewire->dispatch('event-price-table-refresh'))
                ->helperText('Włącz, gdy koszt transportu jest ustalany indywidualnie za całą imprezę (np. różne ryczałty).'),

            Forms\Components\TextInput::make('manual_transport_cost')
                ->label('Koszt transportu (ryczałt za imprezę)')
                ->numeric()
                ->minValue(0)
                ->suffix('PLN')
                ->visible(fn (Get $get): bool => (bool) $get('use_manual_transport_cost'))
                ->required(fn (Get $get): bool => (bool) $get('use_manual_transport_cost'))
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($livewire) => $livewire->dispatch('event-price-table-refresh'))
                ->helperText('Ta kwota trafia do kalkulacji imprezy zamiast automatycznego liczenia z autokaru.'),
        ];
    }
}
