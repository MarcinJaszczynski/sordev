<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Contract;
use App\Models\EventSettlementParticipantPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot umowa ↔ ledger wpłat uczestnika (wiele-do-wielu).
 */
final class ContractParticipantPaymentLinkService
{
    /**
     * @param  array<int, int>  $paymentIds
     */
    public function syncForContract(Contract $contract, array $paymentIds, ?int $primaryPaymentId = null): void
    {
        if (! Schema::hasTable('contract_participant_payments')) {
            $this->syncLegacyOnly($contract, $paymentIds, $primaryPaymentId);

            return;
        }

        $paymentIds = collect($paymentIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $primaryPaymentId ??= $paymentIds[0] ?? null;

        DB::transaction(function () use ($contract, $paymentIds, $primaryPaymentId): void {
            $existing = DB::table('contract_participant_payments')
                ->where('contract_id', $contract->id)
                ->pluck('participant_payment_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $toRemove = array_diff($existing, $paymentIds);
            if ($toRemove !== []) {
                DB::table('contract_participant_payments')
                    ->where('contract_id', $contract->id)
                    ->whereIn('participant_payment_id', $toRemove)
                    ->delete();
            }

            foreach ($paymentIds as $paymentId) {
                $exists = DB::table('contract_participant_payments')
                    ->where('contract_id', $contract->id)
                    ->where('participant_payment_id', $paymentId)
                    ->exists();

                if ($exists) {
                    DB::table('contract_participant_payments')
                        ->where('contract_id', $contract->id)
                        ->where('participant_payment_id', $paymentId)
                        ->update([
                            'is_primary' => $primaryPaymentId !== null && $paymentId === $primaryPaymentId,
                            'updated_at' => now(),
                        ]);
                } else {
                    DB::table('contract_participant_payments')->insert([
                        'contract_id' => $contract->id,
                        'participant_payment_id' => $paymentId,
                        'is_primary' => $primaryPaymentId !== null && $paymentId === $primaryPaymentId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $meta = array_merge($contract->meta ?? [], [
                'linked_participant_payment_ids' => array_values($paymentIds),
            ]);

            if (count($paymentIds) === 1) {
                $contract->forceFill([
                    'participant_payment_id' => $primaryPaymentId,
                    'meta' => $meta,
                ])->saveQuietly();
            } else {
                Contract::query()->whereKey($contract->id)->update([
                    'participant_payment_id' => null,
                    'meta' => json_encode($meta),
                ]);
            }
        });
    }

    public function linkSingle(Contract $contract, EventSettlementParticipantPayment $payment, bool $primary = true): void
    {
        $existingIds = $contract->linkedParticipantPaymentIds();

        if (! in_array((int) $payment->id, $existingIds, true)) {
            $existingIds[] = (int) $payment->id;
        }

        $primaryId = $primary ? (int) $payment->id : ($contract->participant_payment_id ?: $existingIds[0] ?? null);
        $this->syncForContract($contract, $existingIds, $primaryId);
    }

    /**
     * @return array<int, int>
     */
    public function linkedPaymentIds(Contract $contract): array
    {
        if (Schema::hasTable('contract_participant_payments')) {
            $fromPivot = DB::table('contract_participant_payments')
                ->where('contract_id', $contract->id)
                ->orderByDesc('is_primary')
                ->orderBy('id')
                ->pluck('participant_payment_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($fromPivot !== []) {
                return $fromPivot;
            }
        }

        return $contract->linkedParticipantPaymentIds();
    }

    /**
     * @param  array<int, int>  $paymentIds
     */
    private function syncLegacyOnly(Contract $contract, array $paymentIds, ?int $primaryPaymentId): void
    {
        $meta = array_merge($contract->meta ?? [], [
            'linked_participant_payment_ids' => array_values($paymentIds),
        ]);

        $contract->forceFill([
            'participant_payment_id' => count($paymentIds) === 1 ? ($primaryPaymentId ?? $paymentIds[0] ?? null) : null,
            'meta' => $meta,
        ])->saveQuietly();
    }
}
