<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Services\ProgramPointPricingCalculator;

/**
 * Jednolite liczenie kosztu punktu programu pod finanse / plan / ofertę.
 *
 * Zasady biznesowe:
 * - group_size = 1  → cena × liczba osób koszowych
 * - group_size > 1  → cena × ceil(osoby / group_size)  (np. 15 za 10 zł → 45 os. = 30 zł)
 * - group_size = 0  → cena × sztuki (quantity)
 * - osoby koszowe = uczestnicy płacący + gratisy/opiekunowie (jedzą, śpią, bilety),
 *   mimo że gratisy nie wchodzą w cenę/os. dla klienta.
 */
final class ProgramPointCostPricing
{
    /**
     * Liczba osób, za które realnie płacimy podwykonawcy.
     */
    public static function costHeadcount(Event $event, ?int $payingParticipants = null): int
    {
        $paying = max(1, (int) ($payingParticipants ?? $event->participant_count ?? 1));
        $gratis = max(0, $event->resolveGratisCountForParticipantCount($paying));

        return max(1, $paying + $gratis);
    }

    /**
     * @return array{
     *   paying: int,
     *   gratis: int,
     *   headcount: int,
     *   group_size: int|null,
     *   billable_units: int,
     *   unit_price: float,
     *   total: float,
     *   total_pln_offer: float,
     *   total_pln_finance: float,
     *   currency_code: string,
     *   convert_to_pln: bool,
     *   pricing_mode: string,
     *   basis: string,
     *   hint: string
     * }
     */
    public static function breakdown(EventProgramPoint $point, Event $event, ?int $payingParticipants = null): array
    {
        $point->loadMissing(['currency', 'templatePoint']);

        $paying = max(1, (int) ($payingParticipants ?? $event->participant_count ?? 1));
        $gratis = max(0, $event->resolveGratisCountForParticipantCount($paying));
        $headcount = max(1, $paying + $gratis);

        $groupSize = $point->group_size;
        $unitPrice = (float) ($point->unit_price ?? 0);
        $fixedQty = max(1, (int) ($point->quantity ?? 1));
        $billable = ProgramPointPricingCalculator::billableUnits($headcount, $groupSize, $fixedQty);
        $total = ProgramPointPricingCalculator::totalPrice($unitPrice, $headcount, $groupSize, $fixedQty);

        $currency = $point->currency;
        $code = strtoupper((string) ($currency?->code ?? $currency?->symbol ?? 'PLN'));
        if ($code === '') {
            $code = 'PLN';
        }
        $convert = (bool) ($point->convert_to_pln ?? false);
        $rate = (float) ($currency?->exchange_rate ?? 1);

        $isPln = $code === 'PLN';
        $converted = (! $isPln && $rate > 0) ? round($total * $rate, 2) : round($total, 2);

        // Oferta (cena/os.): waluta obca bez convert_to_pln nie wchodzi do sumy PLN.
        $offerPln = $isPln ? round($total, 2) : ($convert ? $converted : 0.0);
        // Finanse: zawsze ekwiwalent PLN po kursie, żeby porównać plan ↔ kalkulację.
        $financePln = $isPln ? round($total, 2) : $converted;

        $basis = ProgramPointPricingCalculator::pricingBasisFromGroupSize(
            $groupSize === null ? null : (int) $groupSize
        );

        $pricingMode = match ($basis) {
            ProgramPointPricingCalculator::BASIS_PER_PIECE => 'Za sztukę',
            ProgramPointPricingCalculator::BASIS_PER_GROUP => 'Za grupę ('.ProgramPointPricingCalculator::normalizedGroupSize((int) $groupSize).' os.)',
            default => 'Za osobę',
        };

        $unitFmt = number_format($unitPrice, 2, ',', ' ');
        $hint = sprintf(
            '%s · %d os. (%d+%d gratis) · %d jedn. × %s %s',
            $pricingMode,
            $headcount,
            $paying,
            $gratis,
            $billable,
            $unitFmt,
            $code,
        );

        return [
            'paying' => $paying,
            'gratis' => $gratis,
            'headcount' => $headcount,
            'group_size' => $groupSize === null ? null : (int) $groupSize,
            'billable_units' => $billable,
            'unit_price' => round($unitPrice, 2),
            'total' => round($total, 2),
            'total_pln_offer' => $offerPln,
            'total_pln_finance' => $financePln,
            'currency_code' => $code,
            'convert_to_pln' => $convert,
            'pricing_mode' => $pricingMode,
            'basis' => $basis,
            'hint' => $hint,
        ];
    }

    public static function totalPlnForFinance(EventProgramPoint $point, Event $event, ?int $payingParticipants = null): float
    {
        return self::breakdown($point, $event, $payingParticipants)['total_pln_finance'];
    }

    public static function totalPlnForOffer(EventProgramPoint $point, Event $event, ?int $payingParticipants = null): float
    {
        return self::breakdown($point, $event, $payingParticipants)['total_pln_offer'];
    }
}
