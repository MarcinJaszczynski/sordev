<?php

namespace App\Services;

use App\Mail\ParticipantPaymentReminderMail;
use App\Models\Contract;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventPortalAccess;
use App\Models\EventSettlementParticipantPayment;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

final class ParticipantPaymentReminderService
{
    public function __construct(
        private ParticipantPaymentBalanceService $balanceService,
    ) {}

    /**
     * @return array{sent: bool, message: string}
     */
    public function sendReminder(EventSettlementParticipantPayment $payment): array
    {
        $payment->loadMissing(['settlement.event', 'eventParticipant', 'contracts']);

        $email = $this->resolveEmail($payment);
        if (! filled($email)) {
            return [
                'sent' => false,
                'message' => 'Brak adresu e-mail uczestnika.',
            ];
        }

        $event = $payment->settlement?->event;
        if (! $event instanceof Event) {
            return [
                'sent' => false,
                'message' => 'Nie znaleziono imprezy powiązanej z rozliczeniem.',
            ];
        }

        $balance = $this->balanceService->balanceRow($payment);
        $remaining = (float) ($balance['remaining_pln'] ?? 0);

        if ($remaining <= 0) {
            return [
                'sent' => false,
                'message' => 'Uczestnik nie ma zaległej kwoty do dopłaty.',
            ];
        }

        $amounts = [
            'due_pln' => (float) ($balance['due_pln'] ?? 0),
            'paid_pln' => (float) ($balance['paid_pln'] ?? 0),
            'remaining_pln' => $remaining,
        ];

        Mail::to($email)->queue(new ParticipantPaymentReminderMail(
            payment: $payment,
            event: $event,
            recipientName: $this->resolveRecipientName($payment),
            amounts: $amounts,
            portalUrl: $this->resolvePortalUrl($event, $email),
        ));

        return [
            'sent' => true,
            'message' => 'E-mail został zakolejkowany na adres '.$email.'.',
        ];
    }

    private function resolveEmail(EventSettlementParticipantPayment $payment): ?string
    {
        $participantEmail = trim((string) ($payment->eventParticipant?->email ?? ''));
        if ($participantEmail !== '') {
            return $participantEmail;
        }

        $contract = $payment->contracts()->first();
        if ($contract instanceof Contract) {
            foreach ([
                $contract->participant_email,
                $contract->signer_email,
                $contract->customer_email,
            ] as $candidate) {
                $candidate = trim((string) ($candidate ?? ''));
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }

        if (Schema::hasTable('event_participants')) {
            $fallbackParticipant = EventParticipant::query()
                ->where('event_id', $payment->settlement?->event_id)
                ->where(function ($query) use ($payment): void {
                    $query->where('participant_payment_id', $payment->id);

                    if (filled($payment->booking_reference)) {
                        $query->orWhere('booking_reference', $payment->booking_reference);
                    }
                })
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->first();

            if ($fallbackParticipant && filled($fallbackParticipant->email)) {
                return trim((string) $fallbackParticipant->email);
            }
        }

        return null;
    }

    private function resolveRecipientName(EventSettlementParticipantPayment $payment): string
    {
        $participant = $payment->eventParticipant;
        if ($participant instanceof EventParticipant) {
            $fullName = $participant->fullName();
            if ($fullName !== '') {
                return $fullName;
            }
        }

        return trim((string) ($payment->participant_name ?: 'Uczestnik'));
    }

    private function resolvePortalUrl(Event $event, string $email): ?string
    {
        if (! Schema::hasTable('event_portal_accesses')) {
            return null;
        }

        $user = User::query()->where('email', $email)->first();
        if (! $user) {
            return null;
        }

        $hasAccess = EventPortalAccess::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->exists();

        if (! $hasAccess) {
            return null;
        }

        return url('/portal/login');
    }
}
