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
 * - osoby koszowe = domyślnie tylko płacący;
 *   opiekunowie / pilot / kierowca tylko gdy odpowiednie flagi na punkcie są włączone.
 */
final class ProgramPointCostPricing
{
    /**
     * Składa headcount z płacących i zaznaczonych dodatków.
     */
    public static function applyIncludedExtras(
        int $paying,
        int $gratis,
        int $pilot,
        int $driver,
        bool $includeGratis,
        bool $includePilot,
        bool $includeDriver,
    ): int {
        $total = max(1, $paying);

        if ($includeGratis) {
            $total += max(0, $gratis);
        }
        if ($includePilot) {
            $total += max(0, $pilot);
        }
        if ($includeDriver) {
            $total += max(0, $driver);
        }

        return max(1, $total);
    }

    /**
     * Liczba osób, za które liczymy koszt punktu.
     */
    public static function costHeadcount(
        Event $event,
        ?int $payingParticipants = null,
        bool $includeGratis = false,
        bool $includePilot = false,
        bool $includeDriver = false,
    ): int {
        $paying = max(1, (int) ($payingParticipants ?? $event->participant_count ?? 1));

        return self::applyIncludedExtras(
            $paying,
            $event->resolveGratisCountForParticipantCount($paying),
            self::pilotCount($event),
            $event->resolveDriverCountForParticipantCount($paying),
            $includeGratis,
            $includePilot,
            $includeDriver,
        );
    }

    /**
     * Headcount dla konkretnego punktu (respektuje flagi doliczania osób).
     */
    public static function costHeadcountForPoint(
        EventProgramPoint $point,
        Event $event,
        ?int $payingParticipants = null,
    ): int {
        return self::costHeadcount(
            $event,
            $payingParticipants,
            (bool) ($point->include_gratis_in_cost ?? false),
            (bool) ($point->include_pilot_in_cost ?? false),
            (bool) ($point->include_driver_in_cost ?? false),
        );
    }

    public static function pilotCount(Event $event): int
    {
        return filled($event->assigned_to) ? 1 : 0;
    }

    /**
     * @return array{
     *   paying: int,
     *   gratis: int,
     *   pilot: int,
     *   driver: int,
     *   headcount: int,
     *   include_gratis: bool,
     *   include_pilot: bool,
     *   include_driver: bool,
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
        $gratisAvailable = max(0, $event->resolveGratisCountForParticipantCount($paying));
        $pilotAvailable = self::pilotCount($event);
        $driverAvailable = max(0, $event->resolveDriverCountForParticipantCount($paying));

        $includeGratis = (bool) ($point->include_gratis_in_cost ?? false);
        $includePilot = (bool) ($point->include_pilot_in_cost ?? false);
        $includeDriver = (bool) ($point->include_driver_in_cost ?? false);

        $gratis = $includeGratis ? $gratisAvailable : 0;
        $pilot = $includePilot ? $pilotAvailable : 0;
        $driver = $includeDriver ? $driverAvailable : 0;
        $headcount = self::applyIncludedExtras(
            $paying,
            $gratisAvailable,
            $pilotAvailable,
            $driverAvailable,
            $includeGratis,
            $includePilot,
            $includeDriver,
        );

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
        $extras = self::extrasHint($paying, $gratis, $pilot, $driver, $includeGratis, $includePilot, $includeDriver);
        $hint = sprintf(
            '%s · %d os. (%s) · %d jedn. × %s %s',
            $pricingMode,
            $headcount,
            $extras,
            $billable,
            $unitFmt,
            $code,
        );

        return [
            'paying' => $paying,
            'gratis' => $gratis,
            'pilot' => $pilot,
            'driver' => $driver,
            'headcount' => $headcount,
            'include_gratis' => $includeGratis,
            'include_pilot' => $includePilot,
            'include_driver' => $includeDriver,
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

    private static function extrasHint(
        int $paying,
        int $gratis,
        int $pilot,
        int $driver,
        bool $includeGratis,
        bool $includePilot,
        bool $includeDriver,
    ): string {
        if (! $includeGratis && ! $includePilot && ! $includeDriver) {
            return 'tylko płacący';
        }

        $parts = [$paying.' płac.'];
        if ($includeGratis) {
            $parts[] = $gratis.' opiekun.';
        }
        if ($includePilot) {
            $parts[] = $pilot.' pilot';
        }
        if ($includeDriver) {
            $parts[] = $driver.' kierowca';
        }

        return implode(' + ', $parts);
    }
}
