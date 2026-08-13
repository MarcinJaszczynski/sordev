<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\ParticipantPaymentBalanceService;
use App\Support\MoneyFormatter;

/**
 * Wspólne Należne / Wpłacone / Różnica dla widoków wpłat klienta.
 *
 * Opiekunowie/gratis nie płacą (due=0) — są w kosztach imprezy, nie w należności klienta.
 * Potrącenia przy rezygnacji są już w due_amount_pln (retention) przez ParticipantResignationSettlementSync.
 */
final class ParticipantPaymentBalancePresenter
{
    /**
     * @param  array{
     *   due_pln?: float|int|string|null,
     *   paid_pln?: float|int|string|null,
     *   remaining_pln?: float|int|string|null,
     *   difference_pln?: float|int|string|null
     * }|null  $row
     * @return array{
     *   due_pln: float,
     *   paid_pln: float,
     *   difference_pln: float,
     *   due_label: string,
     *   paid_label: string,
     *   difference_label: string,
     *   due_html: string,
     *   paid_html: string,
     *   difference_html: string
     * }
     */
    public static function fromRow(?array $row): array
    {
        $due = round((float) ($row['due_pln'] ?? 0), 2);
        $paid = round((float) ($row['paid_pln'] ?? 0), 2);
        $difference = array_key_exists('difference_pln', $row ?? [])
            ? round((float) $row['difference_pln'], 2)
            : (array_key_exists('remaining_pln', $row ?? [])
                ? round((float) $row['remaining_pln'], 2)
                : round($due - $paid, 2));

        return [
            'due_pln' => $due,
            'paid_pln' => $paid,
            'difference_pln' => $difference,
            'due_label' => MoneyFormatter::format($due, 'PLN'),
            'paid_label' => MoneyFormatter::format($paid, 'PLN'),
            'difference_label' => MoneyFormatter::format($difference, 'PLN'),
            'due_html' => MoneyFormatter::html($due, 'PLN'),
            'paid_html' => MoneyFormatter::html($paid, 'PLN'),
            'difference_html' => MoneyFormatter::html($difference, 'PLN'),
        ];
    }

    /**
     * @return array{
     *   due_pln: float,
     *   paid_pln: float,
     *   difference_pln: float,
     *   due_label: string,
     *   paid_label: string,
     *   difference_label: string,
     *   due_html: string,
     *   paid_html: string,
     *   difference_html: string
     * }
     */
    public static function forEventAggregate(\App\Models\Event $event): array
    {
        $agg = app(ParticipantPaymentBalanceService::class)->eventAggregate($event);

        return self::fromRow([
            'due_pln' => $agg['due_pln'] ?? 0,
            'paid_pln' => $agg['paid_pln'] ?? 0,
            'remaining_pln' => $agg['remaining_pln'] ?? null,
        ]);
    }
}
