<?php

namespace App\Support;

use App\Models\Reservation;

final class ReservationPricingLabel
{
    public static function format(?Reservation $reservation): string
    {
        if (! $reservation || $reservation->reserved_amount === null) {
            return '—';
        }

        $basis = Reservation::$amountBases[$reservation->amount_basis ?? 'lump_sum'] ?? '';
        $scope = Reservation::$participantScopes[$reservation->participant_scope ?? 'all'] ?? '';

        if (($reservation->amount_basis ?? 'lump_sum') === 'per_person') {
            $count = max(1, (int) ($reservation->participant_count ?? 1));
            $unit = round((float) $reservation->reserved_amount / $count, 2);
            $unitLabel = CurrencyAmountDisplay::format(
                $unit,
                $reservation->currency,
                (bool) ($reservation->convert_to_pln ?? true),
            );
            $totalLabel = CurrencyAmountDisplay::format(
                (float) $reservation->reserved_amount,
                $reservation->currency,
                (bool) ($reservation->convert_to_pln ?? true),
            );

            return $unitLabel.'/os. → '.$totalLabel.' ('.$scope.', '.$count.' os.)';
        }

        $amount = CurrencyAmountDisplay::format(
            (float) $reservation->reserved_amount,
            $reservation->currency,
            (bool) ($reservation->convert_to_pln ?? true),
        );

        return $amount.' ('.$basis.', '.$scope.')';
    }
}
