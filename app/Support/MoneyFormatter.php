<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

final class MoneyFormatter
{
    public static function format(float|int|string|null $amount, ?string $currencyCode = 'PLN', int $decimals = 2): string
    {
        $value = (float) ($amount ?? 0);
        $formatted = number_format($value, $decimals, ',', ' ');

        if ($currencyCode === null || $currencyCode === '') {
            return $formatted;
        }

        return $formatted.' '.$currencyCode;
    }

    public static function html(float|int|string|null $amount, ?string $currencyCode = 'PLN', int $decimals = 2): HtmlString
    {
        $text = self::format($amount, $currencyCode, $decimals);
        $nbsp = "\u{00A0}";

        return new HtmlString(
            '<span class="money-nowrap" style="white-space:nowrap">'.e(str_replace(' ', $nbsp, $text)).'</span>'
        );
    }

    public static function currencyCode(?\App\Models\Currency $currency): string
    {
        if (! $currency) {
            return 'PLN';
        }

        return $currency->code ?? $currency->symbol ?? 'PLN';
    }
}
