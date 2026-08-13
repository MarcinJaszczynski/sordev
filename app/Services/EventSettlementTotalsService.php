<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Contract;
use App\Models\EventAgreement;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\EventSettlementParticipantPayment;
use Illuminate\Support\Collection;

/**
 * Przeliczanie sum rozliczenia (koszty planowane/rzeczywiste + należności/wpłaty uczestników).
 *
 * Warstwa Service — model EventSettlement deleguje tu przez cienki wrapper.
 */
final class EventSettlementTotalsService
{
    public function __construct(
        private EventSettlement $settlement,
    ) {}

    public static function for(EventSettlement $settlement): self
    {
        return new self($settlement);
    }

    public function recalculate(): void
    {
        $costs = $this->settlement->costs()->get();
        $this->settlement->planned_cost_pln = round((float) $costs
            ->reject(fn (EventSettlementCost $cost): bool => EventSettlementCost::isPaymentSourceType($cost->source_type))
            ->sum(fn (EventSettlementCost $cost): float => (float) ($cost->planned_amount_pln ?? 0)), 2);
        $this->settlement->actual_cost_pln = round((float) $costs->sum(fn (EventSettlementCost $cost) => $this->resolvePaidCostPln($cost)), 2);

        $payments = $this->settlement->participantPayments()->get();
        $agreementTotals = $this->resolveAgreementPaymentTotals($payments);
        $this->settlement->participant_due_pln = $payments->sum('due_amount_pln') + $agreementTotals['due'];
        $this->settlement->participant_paid_pln = $payments->sum('paid_amount_pln') + $agreementTotals['paid'];

        $this->settlement->saveQuietly();
    }

    private function resolvePaidCostPln(EventSettlementCost $cost): float
    {
        if ($cost->payment_status === 'cancelled') {
            return 0.0;
        }

        if (EventSettlementCost::isPaymentSourceType($cost->source_type)) {
            return (float) ($cost->actual_amount_pln ?? 0);
        }

        if ($cost->actual_amount_pln !== null) {
            return (float) $cost->actual_amount_pln;
        }

        // Zaliczka wpłacona (bez pełnego actual) wlicza się do kosztu rzeczywistego.
        if (in_array($cost->payment_status, ['advance_paid', 'partially_paid'], true)
            && (float) ($cost->advance_amount ?? 0) > 0) {
            $rate = (float) ($cost->actual_rate ?? $cost->planned_rate ?? 1);

            return round((float) $cost->advance_amount * $rate, 2);
        }

        return 0.0;
    }

    /**
     * @param  Collection<int, EventSettlementParticipantPayment>  $participantPayments
     * @return array{due: float, paid: float}
     */
    private function resolveAgreementPaymentTotals(Collection $participantPayments): array
    {
        $event = $this->settlement->event()->with('agreements')->first();

        if (! $event) {
            return ['due' => 0.0, 'paid' => 0.0];
        }

        $paymentIds = $participantPayments
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        $references = $participantPayments
            ->flatMap(function (EventSettlementParticipantPayment $payment) {
                return array_filter([
                    $this->normalizeSettlementReference($payment->booking_reference),
                    $this->normalizeSettlementReference($payment->document_number),
                ]);
            })
            ->unique()
            ->values()
            ->all();

        // Only unsynced documents — already-linked ones live in ledger (avoid double-count).
        $unsyncedAgreements = $event->agreements->filter(function ($agreement) use ($paymentIds, $references) {
            if (! $this->shouldIncludeAgreementInSettlementTotals($agreement)) {
                return false;
            }

            if ($agreement->participant_payment_id && in_array((int) $agreement->participant_payment_id, $paymentIds, true)) {
                return false;
            }

            $linkedIds = $agreement->meta['linked_participant_payment_ids'] ?? [];
            if (is_array($linkedIds)) {
                foreach ($linkedIds as $linkedId) {
                    if (in_array((int) $linkedId, $paymentIds, true)) {
                        return false;
                    }
                }
            }

            $agreementReference = $this->normalizeSettlementReference(
                $agreement->operational_number
                    ?? $agreement->agreement_number
                    ?? $agreement->contract_number
                    ?? null
            );

            if ($agreementReference && in_array($agreementReference, $references, true)) {
                return false;
            }

            return true;
        });

        return [
            'due' => (float) $unsyncedAgreements->sum(fn ($agreement) => (float) ($agreement->amount_due ?? $agreement->total_price ?? 0)),
            'paid' => (float) $unsyncedAgreements->sum(fn ($agreement) => (float) ($agreement->amount_paid ?? 0)),
        ];
    }

    private function shouldIncludeAgreementInSettlementTotals(EventAgreement|Contract $agreement): bool
    {
        if (($agreement->meta['is_individual_template'] ?? false) === true
            && empty($agreement->meta['parent_template_id'] ?? null)) {
            return false;
        }

        if ($agreement instanceof Contract && $agreement->usesIndividualParticipantPayments()) {
            return false;
        }

        if (in_array($agreement->status, ['draft', 'template', 'cancelled'], true)) {
            return false;
        }

        if ($agreement->payment_status === 'failed') {
            return false;
        }

        return (float) ($agreement->amount_due ?? 0) > 0
            || (float) ($agreement->amount_paid ?? 0) > 0
            || $agreement->participant_payment_id !== null;
    }

    private function normalizeSettlementReference(?string $value): ?string
    {
        $value = strtoupper(trim((string) $value));

        return $value !== '' ? $value : null;
    }
}
