<?php

namespace App\Filament\Forms;

use App\Models\Currency;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;

final class CurrencyConversionFields
{
    public static function currencySelect(string $name = 'currency_id', bool $required = true): Forms\Components\Select
    {
        return Forms\Components\Select::make($name)
            ->label('Waluta')
            ->options(fn (): array => Currency::filamentSelectOptions())
            ->default(fn (): ?int => Currency::defaultPlnId())
            ->searchable()
            ->required($required)
            ->live();
    }

    public static function convertToggle(
        string $name = 'convert_to_pln',
        string $currencyField = 'currency_id',
    ): Forms\Components\Toggle {
        return Forms\Components\Toggle::make($name)
            ->label('Przelicz na PLN w kalkulacji')
            ->default(true)
            ->inline(false)
            ->helperText(fn (Get $get): string => self::isForeignCurrency($get($currencyField))
                ? 'Włączone: kwota obca × kurs NBP trafia do sumy PLN.'
                : 'Dla PLN przełącznik nie zmienia kwoty.')
            ->visible(fn (Get $get): bool => self::isForeignCurrency($get($currencyField)));
    }

    public static function plnPreview(
        string $amountField,
        string $currencyField = 'currency_id',
        string $convertField = 'convert_to_pln',
    ): Forms\Components\Placeholder {
        return Forms\Components\Placeholder::make($amountField.'_pln_preview')
            ->label('Kwota')
            ->content(function (Get $get) use ($amountField, $currencyField, $convertField): string {
                $amount = (float) ($get($amountField) ?: 0);
                if ($amount <= 0) {
                    return '—';
                }

                $currencyId = $get($currencyField);
                $currency = $currencyId ? Currency::find($currencyId) : null;

                return \App\Support\CurrencyAmountDisplay::format(
                    $amount,
                    $currency,
                    (bool) ($get($convertField) ?? true),
                );
            })
            ->visible(fn (Get $get): bool => filled($get($amountField)));
    }

    public static function settlementPlannedCurrencyFields(): array
    {
        return [
            self::currencySelect('planned_currency_id'),
            Forms\Components\Toggle::make('planned_convert_to_pln')
                ->label('Przelicz plan na PLN')
                ->default(true)
                ->inline(false)
                ->helperText('Przy walucie obcej: mnożenie kwoty planowanej przez kurs do PLN.')
                ->visible(fn (Get $get): bool => self::isForeignCurrency($get('planned_currency_id'))),
            Forms\Components\TextInput::make('planned_rate')
                ->label('Kurs do PLN')
                ->numeric()
                ->default(1)
                ->live(onBlur: true)
                ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                    $amount = (float) ($get('planned_amount') ?: 0);
                    $set('planned_amount_pln', self::plannedPlnAmount($amount, (float) $state, $get));
                }),
            Forms\Components\TextInput::make('planned_amount_pln')
                ->label('= PLN')
                ->numeric()
                ->readOnly()
                ->suffix('PLN'),
        ];
    }

    public static function recalculatePlannedPln(Set $set, Get $get): void
    {
        $amount = (float) ($get('planned_amount') ?: 0);
        $rate = (float) ($get('planned_rate') ?: 1);
        $pln = self::plannedPlnAmount($amount, $rate, $get);
        $set('planned_amount_pln', $pln);
    }

    public static function plannedPlnAmount(float $amount, float $rate, Get $get): ?float
    {
        if (! self::isForeignCurrency($get('planned_currency_id'))) {
            return round($amount, 2);
        }

        if (! (bool) ($get('planned_convert_to_pln') ?? true)) {
            return null;
        }

        return round($amount * $rate, 2);
    }

    public static function isForeignCurrency(mixed $currencyId): bool
    {
        return \App\Support\CurrencyAmountDisplay::isForeignCurrency($currencyId);
    }
}
