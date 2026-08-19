<?php

namespace App\Support\Reservations;

use App\Models\Currency;
use App\Models\EventProgramPoint;
use App\Models\EventSettlementCost;
use App\Models\Reservation;

final class ReservationFormDefaults
{
    /**
     * Oczekiwane wartości do formularza rezerwacji (punkt programu / koszt).
     *
     * @return array{
     *     reserved_amount: ?float,
     *     participant_count: int,
     *     currency_id: ?int,
     *     amount_basis: string,
     *     participant_scope: string,
     *     convert_to_pln: bool,
     *     status: string,
     *     deposit_due_at: ?string,
     *     amount_hint: ?string
     * }
     */
    public static function forProgramPoint(EventProgramPoint $point, ?EventSettlementCost $cost = null, ?Reservation $existing = null): array
    {
        $point->loadMissing(['event', 'currency']);
        $event = $point->event;
        $cost ??= EventSettlementCost::query()
            ->where('source_type', 'program_point')
            ->where('source_id', $point->id)
            ->latest('id')
            ->first();

        $planned = round((float) ($cost?->planned_amount ?? $point->planned_price ?? $point->total_price ?? 0), 2);
        $advance = round((float) ($cost?->advance_amount ?? 0), 2);
        $existingAmount = $existing?->reserved_amount !== null
            ? round((float) $existing->reserved_amount, 2)
            : null;

        $amount = $existingAmount;
        $hint = null;

        if ($amount === null || $amount <= 0.009) {
            if ($advance > 0.009) {
                $amount = $advance;
                $hint = 'Z planu kosztów (zaliczka)';
            } elseif ($planned > 0.009) {
                $amount = $planned;
                $hint = 'Z planu / kalkulacji punktu';
            }
        }

        $currencyId = $existing?->currency_id
            ?? $cost?->planned_currency_id
            ?? $point->currency_id
            ?? Currency::defaultPlnId();

        $depositDue = $existing?->deposit_due_at?->toDateString()
            ?? ($cost?->advance_due_date?->toDateString());

        return [
            'reserved_amount' => ($amount !== null && $amount > 0.009) ? $amount : null,
            'participant_count' => max(1, (int) ($existing?->participant_count ?? $event?->participant_count ?? 1)),
            'currency_id' => $currencyId ? (int) $currencyId : null,
            'amount_basis' => (string) ($existing?->amount_basis ?? 'lump_sum'),
            'participant_scope' => (string) ($existing?->participant_scope ?? 'all'),
            'convert_to_pln' => (bool) ($existing?->convert_to_pln ?? $cost?->planned_convert_to_pln ?? true),
            'status' => (string) ($existing?->status ?? 'pending'),
            'deposit_due_at' => $depositDue,
            'amount_hint' => $hint,
        ];
    }
}
