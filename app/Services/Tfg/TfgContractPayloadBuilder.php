<?php

namespace App\Services\Tfg;

use App\Models\Contract;
use Illuminate\Support\Collection;

class TfgContractPayloadBuilder
{
    public const PAYLOAD_VERSION = 1;

    public function __construct(
        protected TfgContractValidator $validator,
    ) {}

    public function buildForContracts(Collection $contracts, string $operation): array
    {
        $contracts->each(fn (Contract $contract) => $this->validator->validate($contract));

        return [
            'payload_version' => self::PAYLOAD_VERSION,
            'operation' => $operation,
            'submitted_at' => now()->toIso8601String(),
            'contracts' => $contracts->map(fn (Contract $contract) => $this->buildContractPayload($contract, $operation))->values()->all(),
        ];
    }

    public function buildContractPayload(Contract $contract, string $operation): array
    {
        $contract->loadMissing(['variants.locations', 'variants.transports', 'payments', 'refunds']);

        return [
            'contract_number' => $contract->contract_number,
            'reservation_number' => $contract->reservation_number,
            'contract_date' => optional($contract->contract_date)->format('Y-m-d'),
            'subject_code' => $contract->subject_code,
            'payment_method_code' => $contract->payment_method_code,
            'total_price' => (float) $contract->total_price,
            'currency' => strtoupper((string) $contract->currency),
            'operation' => $operation,
            'correction_reason' => $contract->correction_reason,
            'variants' => $contract->variants->map(fn ($variant) => [
                'travelers_count' => (int) $variant->travelers_count,
                'starts_at' => optional($variant->starts_at)->format('Y-m-d'),
                'ends_at' => optional($variant->ends_at)->format('Y-m-d'),
                'locations' => $variant->locations->map(fn ($loc) => [
                    'scope_type' => $loc->scope_type,
                    'country_code' => $loc->country_code,
                    'locality' => $loc->locality,
                ])->values()->all(),
                'transports' => $variant->transports->map(fn ($transport) => [
                    'transport_code' => $transport->transport_code,
                    'icao_codes' => (array) ($transport->icao_codes ?? []),
                ])->values()->all(),
            ])->values()->all(),
            'payments' => $contract->payments->map(fn ($payment) => [
                'amount' => (float) $payment->amount,
                'currency' => $payment->currency,
                'paid_at' => optional($payment->paid_at)->format('Y-m-d'),
                'payment_method_code' => $payment->payment_method_code,
                'description' => $payment->description,
            ])->values()->all(),
            'refunds' => $contract->refunds->map(fn ($refund) => [
                'amount' => (float) $refund->amount,
                'currency' => $refund->currency,
                'refunded_at' => optional($refund->refunded_at)->format('Y-m-d'),
                'description' => $refund->description,
            ])->values()->all(),
        ];
    }
}
