<?php

use App\Support\CurrencyAmountDisplay;

test('foreign amount without conversion shows only currency symbol', function (): void {
    $currency = new \App\Models\Currency(['symbol' => 'EUR', 'exchange_rate' => 4.30]);

    expect(CurrencyAmountDisplay::format(100, $currency, false))
        ->toBe('100,00 EUR');
});

test('foreign amount with conversion shows currency and pln', function (): void {
    $currency = new \App\Models\Currency(['symbol' => 'EUR', 'exchange_rate' => 4.30]);

    expect(CurrencyAmountDisplay::format(100, $currency, true))
        ->toBe('100,00 EUR (≈ 430,00 PLN)');
});

test('mixed total shows pln and foreign buckets', function (): void {
    expect(CurrencyAmountDisplay::formatMixedTotal(1000, ['EUR' => 500], 0))
        ->toBe('1 000 PLN + 500 EUR');
});

test('indicative format always shows foreign and approximate pln', function (): void {
    $currency = new \App\Models\Currency(['symbol' => 'EUR', 'exchange_rate' => 4.30]);

    expect(CurrencyAmountDisplay::formatIndicative(100, $currency, 4.30))
        ->toBe('100,00 EUR (≈ 430,00 PLN)');
});
