<?php

namespace App\Support;

use App\Models\Currency;

final class SetFinanceCurrencyBuckets
{
    private float $pln = 0.0;

    /** @var array<string, float> */
    private array $foreign = [];

    public function add(float $amount, ?Currency $currency, bool $convertToPln): void
    {
        if ($amount <= 0) {
            return;
        }

        $symbol = CurrencyAmountDisplay::symbol($currency);

        if ($symbol === 'PLN') {
            $this->pln += $amount;

            return;
        }

        $plnEquivalent = CurrencyAmountDisplay::plnEquivalent($amount, $currency, $convertToPln);

        if ($plnEquivalent !== null) {
            $this->pln += $plnEquivalent;

            return;
        }

        $this->foreign[$symbol] = ($this->foreign[$symbol] ?? 0) + $amount;
    }

    public function subtract(self $other): void
    {
        $this->pln = max(0, $this->pln - $other->pln);

        foreach ($other->foreign as $symbol => $amount) {
            $this->foreign[$symbol] = max(0, ($this->foreign[$symbol] ?? 0) - $amount);

            if ($this->foreign[$symbol] <= 0) {
                unset($this->foreign[$symbol]);
            }
        }
    }

    public function plnEquivalentTotal(): float
    {
        return round($this->pln, 2);
    }

    public function hasAmount(): bool
    {
        return $this->pln > 0.00001 || $this->foreign !== [];
    }

    public function formatMixed(int $decimals = 0): string
    {
        return CurrencyAmountDisplay::formatMixedTotal($this->pln, $this->foreign, $decimals);
    }
}
