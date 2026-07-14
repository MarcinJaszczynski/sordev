<?php

namespace App\Support;

use App\Models\Currency;

final class CurrencyAmountDisplay
{
    public static function symbol(?Currency $currency): string
    {
        if (! $currency) {
            return 'PLN';
        }

        return $currency->symbol ?: ($currency->code ?? 'PLN');
    }

    public static function rate(?Currency $currency): float
    {
        return (float) ($currency?->exchange_rate ?? 1);
    }

    public static function plnEquivalent(float $amount, ?Currency $currency, bool $convertToPln): ?float
    {
        if ($amount <= 0) {
            return null;
        }

        $symbol = self::symbol($currency);

        if ($symbol === 'PLN') {
            return round($amount, 2);
        }

        if (! $convertToPln) {
            return null;
        }

        return round($amount * self::rate($currency), 2);
    }

    /**
     * Zawsze pokazuje kwotę w walucie źródłowej; przy przeliczeniu dopisuje PLN.
     */
    public static function format(float $amount, ?Currency $currency, bool $convertToPln, int $decimals = 2): string
    {
        if ($amount <= 0) {
            return '—';
        }

        $symbol = self::symbol($currency);
        $formatted = number_format($amount, $decimals, ',', ' ');

        if ($symbol === 'PLN') {
            return $formatted.' PLN';
        }

        $base = $formatted.' '.$symbol;
        $pln = self::plnEquivalent($amount, $currency, $convertToPln);

        if ($pln === null) {
            return $base;
        }

        return $base.' (≈ '.number_format($pln, $decimals, ',', ' ').' PLN)';
    }

    /**
     * @param  array<string, float>  $foreignBuckets  symbol => amount
     */
    public static function formatMixedTotal(float $plnPart, array $foreignBuckets, int $decimals = 0): string
    {
        $parts = [];

        if ($plnPart > 0) {
            $parts[] = number_format($plnPart, $decimals, ',', ' ').' PLN';
        }

        foreach ($foreignBuckets as $symbol => $amount) {
            if ($amount > 0) {
                $parts[] = number_format($amount, $decimals, ',', ' ').' '.$symbol;
            }
        }

        return $parts !== [] ? implode(' + ', $parts) : '0 PLN';
    }
}
