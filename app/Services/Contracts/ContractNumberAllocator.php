<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\Event;

class ContractNumberAllocator
{
    public function allocate(Contract $contract): string
    {
        $contract->loadMissing('event');
        $event = $contract->event;

        if (! $event instanceof Event || blank($event->code)) {
            return sprintf('UM-%d', $contract->id ?? 0);
        }

        $base = strtoupper(trim((string) $event->code));

        return match ($contract->contract_type) {
            Contract::TYPE_INDIVIDUAL => $this->allocateIndividualNumber($contract, $base),
            Contract::TYPE_GROUP => $this->allocateGroupNumber($contract, $base),
            Contract::TYPE_CUSTOM => $this->allocateCustomNumber($contract, $base),
            default => $base,
        };
    }

    public function ensureOperationalNumber(Contract $contract): ?string
    {
        if (filled($contract->operational_number)) {
            return $contract->operational_number;
        }

        if (! $contract->id) {
            return null;
        }

        $number = $this->allocate($contract);

        $contract->forceFill(['operational_number' => $number])->saveQuietly();

        return $number;
    }

    private function allocateGroupNumber(Contract $contract, string $base): string
    {
        $query = Contract::query()
            ->where('event_id', $contract->event_id)
            ->where('contract_type', Contract::TYPE_GROUP)
            ->whereNotIn('status', ['cancelled', 'template']);

        if ($contract->id) {
            $query->where('id', '!=', $contract->id);
        }

        $existing = $query->pluck('operational_number')->filter()->all();

        if (! in_array($base, $existing, true)) {
            return $base;
        }

        $candidate = $base.'-G';

        if (! in_array($candidate, $existing, true)) {
            return $candidate;
        }

        $suffix = 2;
        while (in_array($base.'-G'.$suffix, $existing, true)) {
            $suffix++;
        }

        return $base.'-G'.$suffix;
    }

    private function allocateIndividualNumber(Contract $contract, string $base): string
    {
        $maxSequence = Contract::query()
            ->where('event_id', $contract->event_id)
            ->where('contract_type', Contract::TYPE_INDIVIDUAL)
            ->where('status', '!=', 'template')
            ->when($contract->id, fn ($query) => $query->where('id', '!=', $contract->id))
            ->pluck('operational_number')
            ->map(fn (?string $number) => $this->extractIndividualSequence($number, $base))
            ->max();

        $next = max(0, (int) $maxSequence) + 1;

        return $base.'U'.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    private function allocateCustomNumber(Contract $contract, string $base): string
    {
        $paymentMode = (string) data_get($contract->meta, 'payment_mode', Contract::CUSTOM_PAYMENT_TOTAL_LUMP);

        if (in_array($paymentMode, [
            Contract::CUSTOM_PAYMENT_PER_PARTICIPANT,
        ], true)) {
            return $this->allocateGroupNumber($contract, $base);
        }

        if ($paymentMode === Contract::CUSTOM_PAYMENT_MANUAL && filled($contract->operational_number)) {
            return (string) $contract->operational_number;
        }

        return $this->allocateGroupNumber($contract, $base);
    }

    private function extractIndividualSequence(?string $number, string $base): int
    {
        if (blank($number)) {
            return 0;
        }

        $pattern = '/^'.preg_quote($base, '/').'U(\d+)$/i';

        if (preg_match($pattern, strtoupper(trim($number)), $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }
}
