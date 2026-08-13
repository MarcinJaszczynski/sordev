<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ContractPaymentSchedule;
use App\Models\EventAgreementPaymentSchedule;
use App\Models\EventParticipant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Magic link dla rodzica/opiekuna — dostęp do zgód i płatności bez konta.
 */
final class ParentParticipantAccessService
{
    public function issueToken(EventParticipant $participant, int $ttlDays = 60): EventParticipant
    {
        if (! Schema::hasColumn('event_participants', 'parent_access_token')) {
            throw new \RuntimeException('Kolumna parent_access_token niedostępna — uruchom migracje.');
        }

        do {
            $token = Str::random(48);
        } while (EventParticipant::query()->where('parent_access_token', $token)->exists());

        $participant->forceFill([
            'parent_access_token' => $token,
            'parent_access_token_expires_at' => now()->addDays(max(1, $ttlDays)),
        ])->save();

        return $participant->fresh() ?? $participant;
    }

    public function urlFor(EventParticipant $participant): string
    {
        if (blank($participant->parent_access_token)
            || ($participant->parent_access_token_expires_at && $participant->parent_access_token_expires_at->isPast())
        ) {
            $participant = $this->issueToken($participant);
        }

        return route('parent.portal.show', ['token' => $participant->parent_access_token]);
    }

    public function findByToken(string $token): ?EventParticipant
    {
        if (! Schema::hasColumn('event_participants', 'parent_access_token') || blank($token)) {
            return null;
        }

        $participant = EventParticipant::query()
            ->where('parent_access_token', $token)
            ->where('status', EventParticipant::STATUS_ACTIVE)
            ->with(['event', 'participantPayment', 'contract.paymentSchedules', 'eventAgreement.paymentSchedules'])
            ->first();

        if (! $participant) {
            return null;
        }

        if ($participant->parent_access_token_expires_at && $participant->parent_access_token_expires_at->isPast()) {
            return null;
        }

        return $participant;
    }

    /**
     * Pierwsza nieopłacona rata powiązanej umowy (Contract lub EventAgreement).
     */
    public function nextPayableSchedule(EventParticipant $participant): ?Model
    {
        $contract = $participant->contract;
        if ($contract) {
            $contract->loadMissing('paymentSchedules');
            foreach ($contract->paymentSchedules->sortBy('sort_order') as $schedule) {
                if ($this->scheduleRemaining($schedule) > 0.01) {
                    return $schedule;
                }
            }
        }

        $agreement = $participant->eventAgreement;
        if ($agreement) {
            $agreement->loadMissing('paymentSchedules');
            foreach ($agreement->paymentSchedules->sortBy('sort_order') as $schedule) {
                if ($this->scheduleRemaining($schedule) > 0.01) {
                    return $schedule;
                }
            }
        }

        return null;
    }

    private function scheduleRemaining(ContractPaymentSchedule|EventAgreementPaymentSchedule $schedule): float
    {
        $amount = round((float) ($schedule->amount ?? 0), 2);
        $paid = round((float) ($schedule->paid_amount ?? 0), 2);

        return max(0, round($amount - $paid, 2));
    }
}
