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
                $hint = 'Z planowanych / szablonu punktu';
            }
        }

        $currencyId = $existing?->currency_id
            ?? $cost?->planned_currency_id
            ?? $point->currency_id
            ?? Currency::defaultPlnId();

        $depositDue = $existing?->deposit_due_at?->toDateString()
            ?? ($cost?->advance_due_date?->toDateString());

        $defaultParticipants = max(1, (int) ($existing?->participant_count ?? 0));
        if ($defaultParticipants <= 0 && $event) {
            $defaultParticipants = self::defaultParticipantCountForPoint($point, $event);
        }

        return [
            'reserved_amount' => ($amount !== null && $amount > 0.009) ? $amount : null,
            'participant_count' => $defaultParticipants,
            'currency_id' => $currencyId ? (int) $currencyId : null,
            'amount_basis' => (string) ($existing?->amount_basis ?? 'lump_sum'),
            'participant_scope' => (string) ($existing?->participant_scope ?? 'all'),
            'convert_to_pln' => (bool) ($existing?->convert_to_pln ?? $cost?->planned_convert_to_pln ?? true),
            'status' => (string) ($existing?->status ?? 'pending'),
            'deposit_due_at' => $depositDue,
            'amount_hint' => $hint,
        ];
    }

    /**
     * Defaults dla rezerwacji podpiętej bezpośrednio do kosztu (hotel / transport bez punktu).
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
    public static function forSettlementCost(EventSettlementCost $cost, ?Reservation $existing = null): array
    {
        $cost->loadMissing(['settlement.event', 'plannedCurrency']);
        $event = $cost->settlement?->event;

        $planned = round((float) ($cost->planned_amount ?? $cost->planned_amount_pln ?? 0), 2);
        $advance = round((float) ($cost->advance_amount ?? 0), 2);
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
                $hint = 'Z planu kosztów';
            }
        }

        $currencyId = $existing?->currency_id
            ?? $cost->planned_currency_id
            ?? Currency::defaultPlnId();

        $participants = max(1, (int) ($existing?->participant_count
            ?? $event?->participant_count
            ?? 1));

        return [
            'reserved_amount' => ($amount !== null && $amount > 0.009) ? $amount : null,
            'participant_count' => $participants,
            'currency_id' => $currencyId ? (int) $currencyId : null,
            'amount_basis' => (string) ($existing?->amount_basis ?? 'lump_sum'),
            'participant_scope' => (string) ($existing?->participant_scope ?? 'all'),
            'convert_to_pln' => (bool) ($existing?->convert_to_pln ?? $cost->planned_convert_to_pln ?? true),
            'status' => (string) ($existing?->status ?? 'pending'),
            'deposit_due_at' => $existing?->deposit_due_at?->toDateString()
                ?? ($cost->advance_due_date?->toDateString()),
            'amount_hint' => $hint,
        ];
    }

    private static function defaultParticipantCountForPoint(EventProgramPoint $point, \App\Models\Event $event): int
    {
        if ((bool) ($point->is_hotel ?? false) && ! \App\Models\Event::isHotelTransferProgramPoint($point)) {
            return max(1, $event->resolveOperationalHeadcountForParticipantCount());
        }

        return max(1, (int) ($event->participant_count ?? 1));
    }
}
