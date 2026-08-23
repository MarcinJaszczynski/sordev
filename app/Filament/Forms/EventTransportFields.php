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

            Forms\Components\Textarea::make('adress_transport_start')
                ->label('Adres podstawienia')
                ->rows(3)
                ->columnSpanFull()
                ->visible(fn (): bool => Schema::hasColumn('events', 'adress_transport_start'))
                ->helperText('Wpisz adres miejsca, z którego startuje transport.'),

            Forms\Components\Textarea::make('adress_transport_end')
                ->label('Adres docelowy')
                ->rows(3)
                ->columnSpanFull()
                ->visible(fn (): bool => Schema::hasColumn('events', 'adress_transport_end'))
                ->helperText('Wpisz adres miejsca docelowego transportu.'),
        ];
    }

    /**
     * Jedyna definicja godzin transportu/imprezy — nie duplikować w innych sekcjach.
     * Native input + bez live(): Flatpickr + live datepickery obok powodowały „przeskakiwanie” wartości.
     *
     * @return array<int, Forms\Components\Component>
     */
    public static function transportTimeFields(): array
    {
        $fields = [];

        if (Schema::hasColumn('events', 'substitution_time')) {
            $fields[] = self::stableTimePicker('substitution_time', 'Godzina podstawienia')
                ->helperText('Zbiórka / podstawienie autokaru.');
        }

        if (Schema::hasColumn('events', 'departure_time')) {
            $fields[] = self::stableTimePicker('departure_time', 'Godzina odjazdu');
        }

        if (Schema::hasColumn('events', 'return_time')) {
            $fields[] = self::stableTimePicker('return_time', 'Godzina powrotu');
        }

        return $fields;
    }

    private static function stableTimePicker(string $name, string $label): Forms\Components\TimePicker
    {
        return Forms\Components\TimePicker::make($name)
            ->label($label)
            ->seconds(false)
            ->native(true)
            ->nullable()
            ->columnSpan(1);
    }
}
