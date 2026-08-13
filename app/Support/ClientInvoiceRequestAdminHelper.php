<?php

declare(strict_types=1);

namespace App\Support;

use App\Actions\Finance\CreateClientInvoiceRequestAction;
use App\Data\CreateClientInvoiceRequestData;
use App\Models\ClientInvoiceRequest;
use App\Models\Contract;
use App\Models\ContractOrderingParty;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventSettlementParticipantPayment;
use App\Models\User;

final class ClientInvoiceRequestAdminHelper
{
    /**
     * @return array<string, mixed>
     */
    public static function prefillFromParticipant(EventParticipant $participant): array
    {
        $participant->loadMissing([
            'contract.orderingParties',
            'participantPayment',
        ]);

        $prefill = self::contractPrefill($participant->contract);

        if ($prefill === []) {
            $prefill['buyer_type'] = ClientInvoiceRequest::BUYER_PERSON;
            $prefill['company_name'] = $participant->fullName();
            $prefill['invoice_email'] = $participant->email;
            $prefill['applicant_phone'] = $participant->phone;
            $prefill['payment_reference'] = $participant->booking_reference;
        } else {
            $prefill['company_name'] = $prefill['company_name'] ?: $participant->fullName();
            $prefill['invoice_email'] = $prefill['invoice_email'] ?: $participant->email;
            $prefill['applicant_phone'] = $prefill['applicant_phone'] ?: $participant->phone;
            $prefill['payment_reference'] = $prefill['payment_reference'] ?: $participant->booking_reference;
        }

        $prefill['event_id'] = $participant->event_id;
        $prefill['amount'] = self::resolveInvoiceAmount($participant->participantPayment);

        return $prefill;
    }

    /**
     * @return array<string, mixed>
     */
    public static function prefillFromPayment(EventSettlementParticipantPayment $payment, int $eventId): array
    {
        $payment->loadMissing([
            'eventParticipant.contract.orderingParties',
            'contracts.orderingParties',
        ]);

        $participant = $payment->eventParticipant;
        $contract = $participant?->contract ?? $payment->contracts->first();

        if ($participant) {
            $prefill = self::prefillFromParticipant($participant);

            $prefill['event_id'] = $eventId;
            $prefill['amount'] = self::resolveInvoiceAmount($payment);

            return $prefill;
        }

        $prefill = self::contractPrefill($contract);
        $prefill['event_id'] = $eventId;
        $prefill['buyer_type'] = $prefill['buyer_type'] ?? ClientInvoiceRequest::BUYER_PERSON;
        $prefill['company_name'] = $prefill['company_name'] ?: (string) $payment->participant_name;
        $prefill['payment_reference'] = $prefill['payment_reference'] ?: $payment->booking_reference;
        $prefill['amount'] = self::resolveInvoiceAmount($payment);

        return $prefill;
    }

    /**
     * @return array<string, mixed>
     */
    public static function prefillFromEvent(Event $event): array
    {
        return array_filter([
            'event_id' => $event->id,
            'buyer_type' => ClientInvoiceRequest::BUYER_COMPANY,
            'company_name' => $event->client_name,
            'invoice_email' => $event->client_email,
            'applicant_phone' => $event->client_phone,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @return array<string, mixed>
     */
    public static function prefillFromUser(User $user): array
    {
        return [
            'buyer_type' => ClientInvoiceRequest::BUYER_PERSON,
            'company_name' => $user->name,
            'invoice_email' => $user->email,
            'applicant_phone' => $user->phone,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function createFromAdminForm(
        array $data,
        ?User $linkedUser = null,
        ?Contract $contract = null,
    ): ClientInvoiceRequest {
        $event = Event::query()->findOrFail((int) $data['event_id']);

        return app(CreateClientInvoiceRequestAction::class)(new CreateClientInvoiceRequestData(
            buyerType: (string) $data['buyer_type'],
            companyName: (string) $data['company_name'],
            invoiceEmail: (string) $data['invoice_email'],
            source: ClientInvoiceRequest::SOURCE_ADMIN,
            nip: $data['nip'] ?? null,
            street: $data['street'] ?? null,
            houseNumber: $data['house_number'] ?? null,
            postalCode: $data['postal_code'] ?? null,
            city: $data['city'] ?? null,
            applicantPhone: $data['applicant_phone'] ?? null,
            amount: isset($data['amount']) && $data['amount'] !== null && $data['amount'] !== ''
                ? (float) $data['amount']
                : null,
            paymentReference: $data['payment_reference'] ?? null,
            notes: $data['notes'] ?? null,
            eventCodeEntered: $event->code,
            event: $event,
            contract: $contract,
            user: $linkedUser,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private static function contractPrefill(?Contract $contract): array
    {
        if (! $contract) {
            return [];
        }

        $contract->loadMissing('orderingParties');
        /** @var ContractOrderingParty|null $primaryParty */
        $primaryParty = $contract->orderingParties->sortBy('sort_order')->first();

        $nip = filled($primaryParty?->nip) ? (string) $primaryParty->nip : null;

        return array_filter([
            'buyer_type' => filled($nip)
                ? ClientInvoiceRequest::BUYER_COMPANY
                : ClientInvoiceRequest::BUYER_PERSON,
            'company_name' => $contract->customer_name ?: $contract->signer_name,
            'nip' => $nip,
            'street' => $primaryParty?->street,
            'house_number' => $primaryParty?->house_number,
            'postal_code' => $primaryParty?->postal_code,
            'city' => $primaryParty?->city,
            'invoice_email' => $contract->signer_email ?: $contract->customer_email,
            'applicant_phone' => $contract->signer_phone ?: $contract->customer_phone,
            'payment_reference' => $contract->agreement_number
                ?: $contract->contract_number
                ?: $contract->operational_number,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private static function resolveInvoiceAmount(
        EventSettlementParticipantPayment|null $payment,
    ): ?float {
        if (! $payment) {
            return null;
        }

        $paid = (float) $payment->paid_amount_pln;
        if ($paid > 0.009) {
            return round($paid, 2);
        }

        $due = (float) $payment->due_amount_pln;
        if ($due > 0.009) {
            return round($due, 2);
        }

        return null;
    }
}
