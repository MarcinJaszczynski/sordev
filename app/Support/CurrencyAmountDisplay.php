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

    /**
     * Waluta inna niż PLN (po symbolu) — logika współdzielona z polami formularzy.
     */
    public static function isForeignCurrency(mixed $currencyId): bool
    {
        if (! $currencyId) {
            return false;
        }

        $currency = Currency::find($currencyId);

        return $currency && $currency->symbol !== 'PLN';
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
 * Kwota w walucie źródłowej; dla obcej zawsze dopisuje orientacyjne PLN (kurs).
 * Używane gdy trzeba pokazać ekwiwalent niezależnie od convert_to_pln
 * (np. porównania wewnętrzne). Etykiety UI z flagą: {@see format()}.
 */
    public static function formatIndicative(
        float $amount,
        ?Currency $currency,
        ?float $rate = null,
        int $decimals = 2,
    ): string {
        if ($amount <= 0) {
            return '—';
        }

        $symbol = self::symbol($currency);
        $formatted = number_format($amount, $decimals, ',', ' ');

        if ($symbol === 'PLN') {
            return $formatted.' PLN';
        }

        $effectiveRate = ($rate !== null && $rate > 0) ? $rate : self::rate($currency);
        $pln = round($amount * $effectiveRate, $decimals);

        return $formatted.' '.$symbol.' (≈ '.number_format($pln, $decimals, ',', ' ').' PLN)';
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
