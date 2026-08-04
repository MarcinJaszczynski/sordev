<?php

namespace App\Filament\Forms;

use App\Models\Event;
use Carbon\Carbon;
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

    /**
     * Godziny: podstawienia, wyjazdu i powrotu — z kontekstem daty („kiedy”).
     *
     * @return array<int, Forms\Components\Component>
     */
    public static function transportTimeFields(): array
    {
        $fields = [];

        if (Schema::hasColumn('events', 'substitution_time')) {
            $fields[] = self::timePicker(
                'substitution_time',
                'Godzina podstawienia',
                'start_date',
                'Data rozpoczęcia imprezy'
            );
        }

        if (Schema::hasColumn('events', 'departure_time')) {
            $fields[] = self::timePicker(
                'departure_time',
                'Godzina wyjazdu',
                'start_date',
                'Data rozpoczęcia imprezy'
            );
        }

        if (Schema::hasColumn('events', 'return_time')) {
            $fields[] = self::timePicker(
                'return_time',
                'Godzina powrotu',
                'end_date',
                'Data zakończenia imprezy',
                'start_date'
            );
        }

        return $fields;
    }

    protected static function timePicker(
        string $name,
        string $label,
        string $primaryDateField,
        string $fallbackLabel,
        ?string $secondaryDateField = null,
    ): Forms\Components\TimePicker {
        return Forms\Components\TimePicker::make($name)
            ->label($label)
            ->seconds(false)
            ->native(false)
            ->nullable()
            ->format('H:i')
            ->displayFormat('H:i')
            ->formatStateUsing(fn ($state) => self::normalizeClockTime($state))
            ->dehydrateStateUsing(fn ($state) => self::normalizeClockTime($state))
            ->helperText(fn (Get $get, ?Event $record): string => self::whenLabel(
                $get,
                $record,
                $primaryDateField,
                $fallbackLabel,
                $secondaryDateField
            ));
    }

    /**
     * Normalizuje wartość TimePickera (H:i, H:i:s lub datetime) do H:i.
     */
    public static function normalizeClockTime(mixed $state): ?string
    {
        if ($state === null || $state === '') {
            return null;
        }

        if ($state instanceof Carbon) {
            return $state->format('H:i');
        }

        $raw = trim((string) $state);

        try {
            if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $raw) === 1) {
                return Carbon::createFromFormat(strlen($raw) > 5 ? 'H:i:s' : 'H:i', substr($raw, 0, 8))->format('H:i');
            }

            return Carbon::parse($raw)->format('H:i');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Kontekst daty dla godzin operacyjnych (podstawienie / wyjazd / powrót).
     */
    protected static function whenLabel(
        Get $get,
        ?Event $record,
        string $primaryDateField,
        string $fallbackLabel,
        ?string $secondaryDateField = null,
    ): string {
        $raw = $get($primaryDateField)
            ?? $record?->{$primaryDateField}
            ?? ($secondaryDateField ? ($get($secondaryDateField) ?? $record?->{$secondaryDateField}) : null);

        if (blank($raw)) {
            return 'Kiedy: '.$fallbackLabel.' (ustaw datę powyżej)';
        }

        try {
            $formatted = $raw instanceof Carbon
                ? $raw->format('d.m.Y')
                : Carbon::parse((string) $raw)->format('d.m.Y');
        } catch (\Throwable) {
            return 'Kiedy: '.$fallbackLabel;
        }

        return 'Kiedy: '.$formatted;
    }
}
