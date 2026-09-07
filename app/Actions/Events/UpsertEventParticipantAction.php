<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Actions\Finance\RecordParticipantPaymentAction;
use App\Data\RecordParticipantPaymentData;
use App\Data\UpsertEventParticipantData;
use App\Models\EventParticipant;
use App\Models\EventSettlement;
use App\Models\EventSettlementParticipantPayment;
use App\Models\EventSettlementParticipantPaymentEntry;
use App\Support\ParticipantNameMatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

final class UpsertEventParticipantAction
{
    public function __construct(
        private readonly RecordParticipantPaymentAction $recordPayment,
    ) {}

    public function __invoke(UpsertEventParticipantData $data): EventParticipant
    {
        $firstName = trim((string) ($data->firstName ?? ''));
        $lastName = trim((string) ($data->lastName ?? ''));

        if ($firstName === '' && $lastName === '') {
            throw new InvalidArgumentException('Podaj imię lub nazwisko uczestnika.');
        }

        return DB::transaction(function () use ($data, $firstName, $lastName): EventParticipant {
            $payload = [
                'first_name' => $firstName !== '' ? $firstName : null,
                'last_name' => $lastName !== '' ? $lastName : null,
                'birth_date' => filled($data->birthDate) ? Carbon::parse($data->birthDate)->toDateString() : null,
                'pesel' => ($digits = preg_replace('/\D/', '', (string) ($data->pesel ?? ''))) ? $digits : null,
                'email' => ($email = trim((string) ($data->email ?? ''))) !== '' ? $email : null,
                'phone' => ($phone = trim((string) ($data->phone ?? ''))) !== '' ? $phone : null,
                'booking_reference' => ($ref = trim((string) ($data->bookingReference ?? ''))) !== '' ? $ref : null,
                'diet' => ($diet = trim((string) ($data->diet ?? ''))) !== '' ? $diet : null,
            ];

            if (Schema::hasColumn('event_participants', 'gender')) {
                $payload['gender'] = EventParticipant::normalizeGender($data->gender);
            }

            if (Schema::hasColumn('event_participants', 'consents')) {
                $flags = $data->consentFlags;
                if ($flags === null && $data->parentConsent) {
                    $flags = array_fill_keys(\App\Support\EventParticipantConsents::requiredKeys(), true);
                }
                if ($flags === null && ! $data->parentConsent && $data->participant === null) {
                    $flags = array_fill_keys(\App\Support\EventParticipantConsents::allKeys(), false);
                }
                if (is_array($flags)) {
                    $payload['consents'] = \App\Support\EventParticipantConsents::applyFlags(
                        $flags,
                        $data->participant?->consents,
                    );
                    $hasRequired = \App\Support\EventParticipantConsents::hasRequired($payload['consents']);
                    if (Schema::hasColumn('event_participants', 'parent_consent_at')) {
                        if ($hasRequired) {
                            $payload['parent_consent_at'] = $data->participant?->parent_consent_at ?? now();
                            $payload['parent_consent_ip'] = $data->participant?->parent_consent_ip ?? request()->ip();
                        } else {
                            $payload['parent_consent_at'] = null;
                            $payload['parent_consent_ip'] = null;
                        }
                    }
                }
            } elseif (Schema::hasColumn('event_participants', 'parent_consent_at')) {
                if ($data->parentConsent) {
                    $payload['parent_consent_at'] = now();
                    $payload['parent_consent_ip'] = request()->ip();
                } else {
                    $payload['parent_consent_at'] = null;
                    $payload['parent_consent_ip'] = null;
                }
            }

            if ($data->participant) {
                $participant = $data->participant;
                abort_unless((int) $participant->event_id === (int) $data->event->id, 404);
                $participant->update($payload);
            } else {
                $participant = EventParticipant::query()->create([
                    ...$payload,
                    'event_id' => $data->event->id,
                    'source' => $data->source,
                    'status' => EventParticipant::STATUS_ACTIVE,
                ]);
            }

            $participant = $participant->fresh() ?? $participant;

            if ($data->ensurePayment && Schema::hasTable('event_settlement_participant_payments')) {
                $payment = $this->ensurePaymentRow($participant, $data);

                if ($data->firstPaymentAmountPln !== null && $data->firstPaymentAmountPln > 0) {
                    ($this->recordPayment)(new RecordParticipantPaymentData(
                        payment: $payment,
                        amount: (float) $data->firstPaymentAmountPln,
                        paidAt: $data->firstPaymentPaidAt ?? now(),
                        paymentMethod: $data->firstPaymentMethod ?? 'transfer',
                        payerName: $participant->fullName() ?: null,
                        source: EventSettlementParticipantPaymentEntry::SOURCE_MANUAL,
                    ));
                }
            }

            $fresh = $participant->fresh(['participantPayment', 'contract']) ?? $participant;
            app(\App\Services\ContractExtrasSurchargeService::class)->applyForParticipant($fresh);

            return $fresh->fresh(['participantPayment']) ?? $fresh;
        });
    }

    private function ensurePaymentRow(EventParticipant $participant, UpsertEventParticipantData $data): EventSettlementParticipantPayment
    {
        $settlement = EventSettlement::findOrCreateActiveForEvent($data->event);

        if ($participant->participant_payment_id) {
            $payment = EventSettlementParticipantPayment::query()
                ->whereKey($participant->participant_payment_id)
                ->where('settlement_id', $settlement->id)
                ->first();

            if ($payment) {
                $updates = [
                    'participant_name' => $participant->fullName(),
                    'booking_reference' => $participant->booking_reference ?: $payment->booking_reference,
                ];

                if ($data->dueAmountPln !== null) {
                    $updates['due_amount_pln'] = round($data->dueAmountPln, 2);
                }

                $payment->update($updates);

                return $payment->fresh() ?? $payment;
            }
        }

        $existing = EventSettlementParticipantPayment::query()
            ->where('settlement_id', $settlement->id)
            ->get()
            ->first(fn (EventSettlementParticipantPayment $payment) => ParticipantNameMatcher::namesMatch(
                (string) $payment->participant_name,
                $participant->fullName(),
            ));

        if ($existing) {
            $updates = [
                'participant_name' => $participant->fullName(),
                'booking_reference' => $participant->booking_reference ?: $existing->booking_reference,
            ];
            if ($data->dueAmountPln !== null) {
                $updates['due_amount_pln'] = round($data->dueAmountPln, 2);
            }
            $existing->update($updates);
            $participant->forceFill(['participant_payment_id' => $existing->id])->saveQuietly();

            return $existing->fresh() ?? $existing;
        }

        $payment = EventSettlementParticipantPayment::query()->create([
            'settlement_id' => $settlement->id,
            'participant_name' => $participant->fullName(),
            'booking_reference' => $participant->booking_reference,
            'due_amount_pln' => round((float) ($data->dueAmountPln ?? 0), 2),
            'paid_amount_pln' => 0,
            'payment_status' => 'pending',
            'attended' => true,
            'notes' => 'Utworzono z listy uczestników #'.$participant->id,
        ]);

        $participant->forceFill(['participant_payment_id' => $payment->id])->saveQuietly();
        $settlement->recalculateTotals();

        return $payment;
    }
}
