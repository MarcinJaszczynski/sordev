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
                ->label('Ustal ręcznie koszt transportu')
                ->default(false)
                ->live()
                ->afterStateUpdated(fn ($livewire) => $livewire->dispatch('event-price-table-refresh'))
                ->hintIcon(
                    'heroicon-m-information-circle',
                    tooltip: 'Włącz, gdy koszt transportu jest ustalany indywidualnie za całą imprezę (zamiast liczenia z autokaru).',
                ),

            Forms\Components\TextInput::make('manual_transport_cost')
                ->label('Koszt transportu (kwota za imprezę)')
                ->numeric()
                ->minValue(0)
                ->suffix('PLN')
                ->visible(fn (Get $get): bool => (bool) $get('use_manual_transport_cost'))
                ->required(fn (Get $get): bool => (bool) $get('use_manual_transport_cost'))
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($livewire) => $livewire->dispatch('event-price-table-refresh'))
                ->hintIcon(
                    'heroicon-m-information-circle',
                    tooltip: 'Ta kwota trafia do kosztów imprezy zamiast automatycznego liczenia z autokaru.',
                ),

            // Adres podstawienia: jedno pole w sekcji kierowcy (pickup_place_details).
            // adress_transport_start zostaje w DB (legacy / dokumenty), bez drugiego inputu w UI.

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
     * @return array{substitution: ?Forms\Components\TimePicker, departure: ?Forms\Components\TimePicker, return: ?Forms\Components\TimePicker}
     */
    public static function transportTimeFieldsKeyed(): array
    {
        return [
            'substitution' => Schema::hasColumn('events', 'substitution_time')
                ? self::stableTimePicker('substitution_time', 'Godzina podstawienia')
                    ->hintIcon('heroicon-m-information-circle', tooltip: 'Zbiórka / podstawienie autokaru.')
                : null,
            'departure' => Schema::hasColumn('events', 'departure_time')
                ? self::stableTimePicker('departure_time', 'Godzina odjazdu')
                : null,
            'return' => Schema::hasColumn('events', 'return_time')
                ? self::stableTimePicker('return_time', 'Godzina powrotu')
                : null,
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function transportTimeFields(): array
    {
        return array_values(array_filter(self::transportTimeFieldsKeyed()));
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
