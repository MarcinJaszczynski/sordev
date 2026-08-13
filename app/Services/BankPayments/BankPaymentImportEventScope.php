<?php

namespace App\Services\BankPayments;

use App\Models\BankPaymentImportLine;

final class BankPaymentImportEventScope
{
    public static function lineBelongsToEvent(BankPaymentImportLine $line, int $eventId): bool
    {
        if ((int) $line->event_id === $eventId) {
            return true;
        }

        if ($line->relationLoaded('contract') || $line->contract_id) {
            $line->loadMissing('contract');
            if ((int) ($line->contract?->event_id ?? 0) === $eventId) {
                return true;
            }
        }

        if ($line->relationLoaded('participantPayment') || $line->participant_payment_id) {
            $line->loadMissing('participantPayment.settlement');
            if ((int) ($line->participantPayment?->settlement?->event_id ?? 0) === $eventId) {
                return true;
            }
        }

        return false;
    }

    /** Linia bez żadnego przypisania — widoczna w panelu imprezy do ręcznego dopasowania. */
    public static function isUnassigned(BankPaymentImportLine $line): bool
    {
        return $line->match_status === 'unmatched'
            && blank($line->event_id)
            && blank($line->contract_id)
            && blank($line->participant_payment_id);
    }
}
