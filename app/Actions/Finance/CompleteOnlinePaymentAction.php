<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Models\Contract;
use App\Models\ContractPaymentSchedule;
use App\Models\EventAgreement;
use App\Models\EventAgreementPaymentSchedule;
use App\Models\EventSettlement;
use App\Models\EventSettlementParticipantPayment;
use App\Models\EventSettlementParticipantPaymentEntry;
use App\Models\OnlinePaymentSession;
use App\Services\AgreementPaymentSyncService;
use App\Services\ContractPaymentSyncService;
use App\Services\ParticipantPaymentLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Finalizacja udanej płatności online → rata + Entry w ledgerze uczestnika.
 *
 * SSoT wpłat: EventSettlementParticipantPaymentEntry.
 * schedule.paid_amount oraz amount_paid na umowie = projekcje.
 */
final class CompleteOnlinePaymentAction
{
    public function __construct(
        private readonly ParticipantPaymentLedgerService $ledger,
        private readonly ContractPaymentSyncService $contractPaymentSync,
        private readonly AgreementPaymentSyncService $agreementPaymentSync,
    ) {}

    public function __invoke(OnlinePaymentSession $session): OnlinePaymentSession
    {
        if ($session->status === OnlinePaymentSession::STATUS_PAID) {
            return $session;
        }

        if (! $session->isPending() && $session->status !== OnlinePaymentSession::STATUS_PENDING) {
            throw new InvalidArgumentException('Sesja płatności nie jest w stanie pending.');
        }

        return DB::transaction(function () use ($session): OnlinePaymentSession {
            $session = OnlinePaymentSession::query()->lockForUpdate()->findOrFail($session->id);
            if ($session->status === OnlinePaymentSession::STATUS_PAID) {
                return $session;
            }

            $payable = $session->payable;
            $amount = round((float) $session->amount, 2);

            if ($payable instanceof ContractPaymentSchedule) {
                $this->completeForContractSchedule($payable, $session, $amount);
            } elseif ($payable instanceof EventAgreementPaymentSchedule) {
                $this->completeForAgreementSchedule($payable, $session, $amount);
            }

            $session->forceFill([
                'status' => OnlinePaymentSession::STATUS_PAID,
                'paid_at' => now(),
            ])->save();

            return $session->fresh() ?? $session;
        });
    }

    private function completeForContractSchedule(
        ContractPaymentSchedule $schedule,
        OnlinePaymentSession $session,
        float $amount,
    ): void {
        $this->applySchedulePayment($schedule, $amount);

        $contract = $schedule->contract()->first();
        if (! $contract instanceof Contract) {
            return;
        }

        $participantPayment = $this->ensureContractParticipantPaymentWithoutPaidSeed($contract);
        $this->recordOnlineLedgerEntry($participantPayment, $session, $amount);
        $this->projectDocumentPaidAmount($contract->fresh(), $participantPayment->fresh());
        $this->contractPaymentSync->sync($contract->fresh());
    }

    private function completeForAgreementSchedule(
        EventAgreementPaymentSchedule $schedule,
        OnlinePaymentSession $session,
        float $amount,
    ): void {
        $this->applySchedulePayment($schedule, $amount);

        $agreement = $schedule->eventAgreement()->first();
        if (! $agreement instanceof EventAgreement) {
            return;
        }

        $participantPayment = $this->ensureAgreementParticipantPaymentWithoutPaidSeed($agreement);
        $this->recordOnlineLedgerEntry($participantPayment, $session, $amount);
        $this->projectDocumentPaidAmount($agreement->fresh(), $participantPayment->fresh());
        $this->agreementPaymentSync->sync($agreement->fresh());
    }

    private function applySchedulePayment(ContractPaymentSchedule|EventAgreementPaymentSchedule $schedule, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $attributes = $schedule->getAttributes();
        if (! array_key_exists('paid_amount', $attributes) && ! Schema::hasColumn($schedule->getTable(), 'paid_amount')) {
            return;
        }

        $paid = round((float) ($schedule->paid_amount ?? 0) + $amount, 2);
        $due = round((float) ($schedule->amount ?? 0), 2);
        $schedule->forceFill([
            'paid_amount' => min($paid, $due > 0 ? $due : $paid),
            'paid_at' => $schedule->paid_at ?? now(),
        ])->save();
    }

    /**
     * Sync due/linków bez seedowania paid_amount z rat (żeby Entry było SSoT).
     */
    private function ensureContractParticipantPaymentWithoutPaidSeed(Contract $contract): EventSettlementParticipantPayment
    {
        $previousPaid = (float) ($contract->amount_paid ?? 0);
        $contract->forceFill(['amount_paid' => 0])->saveQuietly();

        try {
            $this->contractPaymentSync->sync($contract->fresh());
        } finally {
            $contract->forceFill(['amount_paid' => $previousPaid])->saveQuietly();
        }

        $contract->refresh();

        if ($contract->participant_payment_id) {
            $payment = EventSettlementParticipantPayment::query()->find($contract->participant_payment_id);
            if ($payment) {
                return $payment;
            }
        }

        $linkedIds = $contract->meta['linked_participant_payment_ids'] ?? [];
        if (is_array($linkedIds) && $linkedIds !== []) {
            $payment = EventSettlementParticipantPayment::query()->find((int) $linkedIds[0]);
            if ($payment) {
                return $payment;
            }
        }

        if (! $contract->event) {
            throw new InvalidArgumentException('Umowa nie ma powiązanej imprezy — brak ledgeru wpłat.');
        }

        $settlement = EventSettlement::findOrCreateActiveForEvent($contract->event);
        $payment = EventSettlementParticipantPayment::query()->create([
            'settlement_id' => $settlement->id,
            'participant_name' => $contract->signer_name
                ?: $contract->participant_name
                ?: $contract->customer_name
                ?: 'Klient',
            'booking_reference' => $contract->operational_number ?: $contract->contract_number ?: ('UMOWA-'.$contract->id),
            'due_amount_pln' => (float) $contract->total_price,
            'paid_amount_pln' => 0,
            'payment_status' => 'pending',
            'notes' => 'Utworzono z płatności online dla umowy #'.$contract->id,
        ]);

        $contract->forceFill(['participant_payment_id' => $payment->id])->saveQuietly();

        return $payment;
    }

    private function ensureAgreementParticipantPaymentWithoutPaidSeed(EventAgreement $agreement): EventSettlementParticipantPayment
    {
        $previousPaid = (float) ($agreement->amount_paid ?? 0);
        $agreement->forceFill(['amount_paid' => 0])->saveQuietly();

        try {
            $this->agreementPaymentSync->sync($agreement->fresh());
        } finally {
            $agreement->forceFill(['amount_paid' => $previousPaid])->saveQuietly();
        }

        $agreement->refresh();

        if ($agreement->participant_payment_id) {
            $payment = EventSettlementParticipantPayment::query()->find($agreement->participant_payment_id);
            if ($payment) {
                return $payment;
            }
        }

        if (! $agreement->event) {
            throw new InvalidArgumentException('Umowa legacy nie ma powiązanej imprezy — brak ledgeru wpłat.');
        }

        $settlement = EventSettlement::findOrCreateActiveForEvent($agreement->event);
        $payment = EventSettlementParticipantPayment::query()->create([
            'settlement_id' => $settlement->id,
            'participant_name' => $agreement->signer_name
                ?: $agreement->participant_name
                ?: $agreement->customer_name
                ?: 'Klient',
            'booking_reference' => $agreement->operational_number
                ?: $agreement->agreement_number
                ?: ('UMOWA-'.$agreement->id),
            'due_amount_pln' => (float) ($agreement->amount_due ?? 0),
            'paid_amount_pln' => 0,
            'payment_status' => 'pending',
            'notes' => 'Utworzono z płatności online dla event_agreement #'.$agreement->id,
        ]);

        $agreement->forceFill(['participant_payment_id' => $payment->id])->saveQuietly();

        return $payment;
    }

    /**
     * Projekcja amount_paid: ledger (SSoT) gdy są wpisy; inaczej suma rat.
     */
    private function projectDocumentPaidAmount(
        Contract|EventAgreement $document,
        ?EventSettlementParticipantPayment $payment,
    ): void {
        $fromSchedules = 0.0;
        if (method_exists($document, 'paymentSchedules')) {
            $document->loadMissing('paymentSchedules');
            if (Schema::hasColumn($document->paymentSchedules()->getRelated()->getTable(), 'paid_amount')) {
                $fromSchedules = round((float) $document->paymentSchedules->sum('paid_amount'), 2);
            }
        }

        $paid = $fromSchedules;
        if ($payment) {
            $fromLedger = $this->ledger->resolvedPaidAmount($payment);
            if (Schema::hasTable('event_settlement_participant_payment_entries')) {
                $payment->loadMissing('entries');
                if ($payment->entries->isNotEmpty()) {
                    $paid = $fromLedger;
                } elseif ($fromLedger > 0) {
                    $paid = $fromLedger;
                }
            } elseif ($fromLedger > 0) {
                $paid = $fromLedger;
            }
        }

        $due = $document instanceof Contract
            ? round((float) ($document->total_price ?? 0), 2)
            : round((float) ($document->amount_due ?? 0), 2);

        $document->forceFill([
            'amount_paid' => $paid,
            'payment_status' => $due > 0 && $paid >= $due - 0.01
                ? 'paid'
                : ($paid > 0 ? 'partial' : ($document->payment_status ?? 'pending')),
            'paid_at' => $paid > 0 ? ($document->paid_at ?? now()) : $document->paid_at,
        ])->saveQuietly();
    }

    private function recordOnlineLedgerEntry(
        EventSettlementParticipantPayment $payment,
        OnlinePaymentSession $session,
        float $amount,
    ): void {
        if ($amount <= 0) {
            return;
        }

        if (! Schema::hasTable('event_settlement_participant_payment_entries')) {
            $payment->forceFill([
                'paid_amount_pln' => round((float) $payment->paid_amount_pln + $amount, 2),
                'payment_date' => now(),
            ])->save();

            return;
        }

        $alreadyLogged = EventSettlementParticipantPaymentEntry::query()
            ->where('participant_payment_id', $payment->id)
            ->where('source', EventSettlementParticipantPaymentEntry::SOURCE_ONLINE)
            ->where('notes', 'like', '%session:'.$session->uuid.'%')
            ->exists();

        if ($alreadyLogged) {
            return;
        }

        $this->ledger->addEntry(
            payment: $payment,
            amount: $amount,
            paidAt: now(),
            source: EventSettlementParticipantPaymentEntry::SOURCE_ONLINE,
            paymentMethod: 'online',
            notes: 'Płatność online (session:'.$session->uuid.')',
            payerName: $session->payer_email,
        );
    }
}
