<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Models\EventDayInsurance;
use App\Models\EventInsurancePolicy;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * SSoT płatności ubezpieczeń: kosztorys (insurance_day).
 * Polisa i lustro events.insurance_* odzwierciedlają stan wpłat — bez ręcznych statusów w UI.
 */
final class EventInsuranceOperationalSync
{
    public function syncFromPlanCost(EventSettlementCost $planCost): void
    {
        if ($planCost->source_type !== 'insurance_day') {
            return;
        }

        $dayInsurance = $planCost->source_id
            ? EventDayInsurance::query()->find((int) $planCost->source_id)
            : null;

        if ($dayInsurance?->event_insurance_policy_id) {
            $policy = EventInsurancePolicy::query()->find((int) $dayInsurance->event_insurance_policy_id);
            if ($policy) {
                $this->syncPolicyFromSettlement($policy);

                return;
            }
        }

        $event = $dayInsurance?->event
            ?? $planCost->settlement?->event
            ?? ($planCost->settlement_id
                ? EventSettlement::query()->find((int) $planCost->settlement_id)?->event
                : null);

        if ($event instanceof Event) {
            $event->refreshInsuranceAggregateMirror();
        }
    }

    public function syncPolicyFromSettlement(EventInsurancePolicy $policy): void
    {
        if (! Schema::hasTable('event_insurance_policies')) {
            return;
        }

        $event = $policy->event ?? $policy->event()->first();
        if (! $event) {
            return;
        }

        $dayIds = $policy->dayInsurances()->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $state = $this->resolvePaymentStateForDayIds($event, $dayIds);

        $paidAt = $this->resolveLatestPaidAt($event, $dayIds);

        $policy->forceFill([
            'status' => match ($state) {
                'paid' => 'completed',
                'partial' => 'in_progress',
                default => $policy->hasOperationalData() ? 'in_progress' : 'pending',
            },
            'payment_status' => match ($state) {
                'paid' => 'paid',
                'partial' => 'partial',
                default => 'pending',
            },
            'paid_at' => $state === 'paid' ? ($paidAt ?? $policy->paid_at) : null,
        ])->saveQuietly();

        $event->refreshInsuranceAggregateMirror();
    }

    public function isDayInsurancePaid(EventDayInsurance $dayInsurance): bool
    {
        return $this->resolvePaymentStateForDayIds(
            $dayInsurance->event ?? $dayInsurance->event()->firstOrFail(),
            [(int) $dayInsurance->id],
        ) === 'paid';
    }

    public function isEventInsurancePaid(Event $event): bool
    {
        if (! $event->requiresInsuranceWorkflow()) {
            return true;
        }

        $dayIds = $event->dayInsurances()
            ->whereNotNull('insurance_id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($dayIds === []) {
            return false;
        }

        return $this->resolvePaymentStateForDayIds($event, $dayIds) === 'paid';
    }

    /**
     * @param  list<int>  $dayIds
     * @return 'none'|'pending'|'partial'|'paid'
     */
    public function resolvePaymentStateForDayIds(Event $event, array $dayIds): string
    {
        if ($dayIds === []) {
            return 'none';
        }

        $settlement = $event->activeSettlement
            ?? EventSettlement::findOrCreateActiveForEvent($event);

        $plans = $settlement->costs()
            ->where('source_type', 'insurance_day')
            ->whereIn('source_id', $dayIds)
            ->get();

        if ($plans->isEmpty()) {
            return 'pending';
        }

        $paidCount = $plans->filter(
            fn (EventSettlementCost $plan): bool => ($plan->payment_status ?? '') === 'paid'
        )->count();

        return match (true) {
            $paidCount === $plans->count() => 'paid',
            $paidCount > 0 || $plans->contains(
                fn (EventSettlementCost $plan): bool => in_array(
                    (string) ($plan->payment_status ?? ''),
                    ['partially_paid', 'advance_paid'],
                    true,
                )
            ) => 'partial',
            default => 'pending',
        };
    }

    /**
     * @param  list<int>  $dayIds
     */
    private function resolveLatestPaidAt(Event $event, array $dayIds): ?\Illuminate\Support\Carbon
    {
        if ($dayIds === []) {
            return null;
        }

        $settlement = $event->activeSettlement
            ?? EventSettlement::findOrCreateActiveForEvent($event);

        /** @var Collection<int, EventSettlementCost> $payments */
        $payments = $settlement->costs()
            ->where('source_type', 'insurance_day_payment')
            ->whereIn('source_id', $dayIds)
            ->whereNotNull('paid_at')
            ->orderByDesc('paid_at')
            ->get();

        return $payments->first()?->paid_at;
    }
}
