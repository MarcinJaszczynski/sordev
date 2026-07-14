<?php

namespace App\Filament\Forms;

use App\Models\Currency;
use App\Support\ReservationAmountParser;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;

final class ReservationAmountFields
{
    /** @return array<int, Forms\Components\Component> */
    public static function schema(string $amountName = 'reserved_amount', string $currencyName = 'currency_id'): array
    {
        return [
            self::currencySelect($currencyName),
            self::make($amountName, $currencyName),
        ];
    }

    public static function currencySelect(string $name = 'currency_id'): Forms\Components\Select
    {
        return Forms\Components\Select::make($name)
            ->label('Waluta')
            ->options(fn (): array => Currency::filamentSelectOptions())
            ->default(fn (): ?int => Currency::defaultPlnId())
            ->searchable()
            ->required()
            ->live();
    }

    public static function make(string $name = 'reserved_amount', string $currencyName = 'currency_id'): TextInput
    {
        return TextInput::make($name)
            ->label(fn (Get $get): string => $get('amount_basis') === 'per_person'
                ? 'Stawka za osobę'
                : 'Kwota łączna za grupę')
            ->maxLength(32)
            ->suffix(fn (Get $get): string => Currency::find($get($currencyName))?->symbol ?? 'PLN')
            ->helperText(fn (Get $get): ?string => $get('amount_basis') === 'per_person'
                ? 'Mnożone przez liczbę uczestników/płacących z formularza.'
                : 'Kwota za całą grupę — bez mnożenia.')
            ->live(onBlur: true)
            ->afterStateHydrated(function (TextInput $component, $state, Get $get): void {
                if ($get('amount_basis') !== 'per_person' || $state === null || $state === '') {
                    return;
                }

                $count = max(1, (int) ($get('participant_count') ?? 1));
                $component->state(round((float) $state / $count, 2));
            })
            ->dehydrateStateUsing(function ($state, Get $get): ?float {
                $count = max(1, (int) ($get('participant_count') ?? 1));
                $basis = (string) ($get('amount_basis') ?? 'lump_sum');

                if ($basis === 'per_person') {
                    $unit = ReservationAmountParser::resolve($state, 1);

                    return $unit !== null ? round($unit * $count, 2) : null;
                }

                return ReservationAmountParser::resolve($state, 1);
            });
    }
}
