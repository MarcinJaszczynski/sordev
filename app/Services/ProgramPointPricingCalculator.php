<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\EventProgramPoint;
use App\Models\EventTemplateProgramPoint;
use App\Support\CurrencyAmountDisplay;

/**
 * Jednolity algorytm wyceny punktu programu (szablon + impreza).
 *
 * group_size = 1  → cena × liczba płacących uczestników
 * group_size > 1  → cena × ⌈uczestnicy / group_size⌉ (cena za grupę)
 * group_size = 0  → cena × liczba sztuk (np. butelki alkoholu)
 */
final class ProgramPointPricingCalculator
{
    public const BASIS_PER_PERSON = 'per_person';

    public const BASIS_PER_GROUP = 'per_group';

    public const BASIS_PER_PIECE = 'per_piece';

    public static function pricingBasisFromGroupSize(?int $groupSize): string
    {
        if ($groupSize === null) {
            return self::BASIS_PER_PERSON;
        }

        $groupSize = (int) $groupSize;

        if ($groupSize === 0) {
            return self::BASIS_PER_PIECE;
        }

        if ($groupSize > 1) {
            return self::BASIS_PER_GROUP;
        }

        return self::BASIS_PER_PERSON;
    }

    public static function unitPriceLabelForBasis(string $basis): string
    {
        return match ($basis) {
            self::BASIS_PER_PIECE => 'Cena za sztukę',
            self::BASIS_PER_GROUP => 'Cena za grupę',
            default => 'Cena za osobę',
        };
    }
    public static function normalizedGroupSize(?int $groupSize): int
    {
        $groupSize = (int) ($groupSize ?? 0);

        return $groupSize <= 0 ? 1 : $groupSize;
    }

    public static function usesGroupPricing(?int $groupSize): bool
    {
        return (int) ($groupSize ?? 0) > 1;
    }

    public static function usesFixedQuantity(?int $groupSize): bool
    {
        return $groupSize !== null && (int) $groupSize === 0;
    }

    public static function billableUnits(int $participantQty, ?int $groupSize, ?int $fixedQuantity = null): int
    {
        $participantQty = max(1, $participantQty);

        if (self::usesFixedQuantity($groupSize)) {
            return max(1, (int) ($fixedQuantity ?? 1));
        }

        $groupSize = self::normalizedGroupSize($groupSize);

        return max(1, (int) ceil($participantQty / $groupSize));
    }

    public static function totalPrice(
        float $unitPrice,
        int $participantQty,
        ?int $groupSize,
        ?int $fixedQuantity = null,
    ): float {
        return round($unitPrice * self::billableUnits($participantQty, $groupSize, $fixedQuantity), 2);
    }

    public static function unitPriceLabel(?int $groupSize): string
    {
        return self::unitPriceLabelForBasis(self::pricingBasisFromGroupSize($groupSize));
    }

    /**
     * @return array{
     *     participant_qty: int,
     *     group_size: int,
     *     billable_units: int,
     *     unit_price: float,
     *     total: float,
     *     pricing_mode: string,
     *     convert_to_pln: bool,
     *     currency_code: string,
     *     pln_equivalent: ?float,
     *     includes_pln: bool,
     * }
     */
    public static function breakdown(
        float $unitPrice,
        int $participantQty,
        ?int $groupSize,
        ?int $fixedQuantity,
        ?Currency $currency,
        bool $convertToPln,
    ): array {
        $participantQty = max(1, $participantQty);
        $billableUnits = self::billableUnits($participantQty, $groupSize, $fixedQuantity);
        $total = round($unitPrice * $billableUnits, 2);
        $currencyCode = CurrencyAmountDisplay::symbol($currency);
        $plnEquivalent = CurrencyAmountDisplay::plnEquivalent($total, $currency, $convertToPln);

        $pricingMode = match (true) {
            self::usesFixedQuantity($groupSize) => 'Za sztukę',
            self::usesGroupPricing($groupSize) => 'Za grupę ('.self::normalizedGroupSize($groupSize).' os.)',
            default => 'Za osobę',
        };

        return [
            'participant_qty' => $participantQty,
            'group_size' => self::usesFixedQuantity($groupSize) ? 0 : self::normalizedGroupSize($groupSize),
            'billable_units' => $billableUnits,
            'unit_price' => round($unitPrice, 2),
            'total' => $total,
            'pricing_mode' => $pricingMode,
            'convert_to_pln' => $convertToPln,
            'currency_code' => $currencyCode,
            'pln_equivalent' => $plnEquivalent,
            'includes_pln' => $currencyCode === 'PLN' || ($convertToPln && $plnEquivalent !== null),
        ];
    }

    public static function breakdownForEventPoint(EventProgramPoint $point, ?int $participantCount = null): array
    {
        $participantCount = max(1, (int) ($participantCount ?? $point->event?->participant_count ?? 1));

        return self::breakdown(
            (float) ($point->unit_price ?? 0),
            $participantCount,
            $point->group_size,
            max(1, (int) ($point->quantity ?? 1)),
            $point->currency,
            (bool) ($point->convert_to_pln ?? false),
        );
    }

    public static function breakdownForTemplatePoint(
        EventTemplateProgramPoint $point,
        int $participantQty,
    ): array {
        return self::breakdown(
            (float) ($point->unit_price ?? 0),
            $participantQty,
            $point->group_size,
            null,
            $point->currency,
            (bool) ($point->convert_to_pln ?? false),
        );
    }

    public static function describeBreakdown(array $breakdown): string
    {
        if (($breakdown['unit_price'] ?? 0) <= 0) {
            return 'Brak ceny jednostkowej';
        }

        $unitFmt = number_format((float) $breakdown['unit_price'], 2, ',', ' ');
        $totalFmt = CurrencyAmountDisplay::format(
            (float) $breakdown['total'],
            Currency::query()->where('symbol', $breakdown['currency_code'])->first(),
            (bool) ($breakdown['convert_to_pln'] ?? false),
        );

        $lines = [
            'Tryb: '.$breakdown['pricing_mode'],
        ];

        if (($breakdown['pricing_mode'] ?? '') !== 'Za sztukę') {
            $lines[] = 'Uczestników (płacących): '.(int) $breakdown['participant_qty'];
        }

        if ((int) ($breakdown['group_size'] ?? 0) > 1) {
            $lines[] = 'Wielkość grupy: '.(int) $breakdown['group_size'].' os.';
        }

        if (($breakdown['pricing_mode'] ?? '') === 'Za sztukę') {
            $lines[] = 'Liczba sztuk: '.(int) $breakdown['billable_units'];
        }

        $unitLabel = ($breakdown['pricing_mode'] ?? '') === 'Za sztukę' ? 'szt.' : 'jedn.';
        $lines[] = 'Jednostek: '.(int) $breakdown['billable_units'].' '.$unitLabel.' × '.$unitFmt.' '.$breakdown['currency_code'];
        $lines[] = 'Suma: '.$totalFmt;

        if ($breakdown['currency_code'] !== 'PLN') {
            $lines[] = (bool) ($breakdown['convert_to_pln'] ?? false)
                ? 'W kalkulacji PLN: tak (kurs × kwota)'
                : 'W kalkulacji PLN: nie (kwota tylko w '.$breakdown['currency_code'].')';
        }

        return implode("\n", $lines);
    }
}
