<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Models\EventInsurancePolicy;
use App\Services\EventInsurancePolicySettlementSync;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Aktualizuje polisę operacyjną i opcjonalnie dokłada produkty na dzień.
 */
final class UpdateEventInsurancePolicyAction
{
    /**
     * @param  array<string, mixed>  $policyFormData  pola insurance_* z formularza
     * @param  list<int|string>  $insuranceIds
     */
    public function __invoke(
        EventInsurancePolicy $policy,
        array $policyFormData,
        ?int $day = null,
        array $insuranceIds = [],
    ): EventInsurancePolicy {
        return DB::transaction(function () use ($policy, $policyFormData, $day, $insuranceIds): EventInsurancePolicy {
            $policy->update(EventInsurancePolicy::attributesFromFormData($policyFormData));

            $event = $policy->event ?? $policy->event()->firstOrFail();

            if ($day !== null && $day > 0 && $insuranceIds !== []) {
                app(AddEventDayInsurancesAction::class)(
                    $event,
                    $day,
                    $insuranceIds,
                    $policy->id,
                );
            }

            $event->refreshInsuranceAggregateMirror();

            $freshPolicy = $policy->fresh() ?? $policy;
            app(\App\Services\EventInsuranceOperationalSync::class)->syncPolicyFromSettlement($freshPolicy);

            try {
                app(EventInsurancePolicySettlementSync::class)->syncPolicy($freshPolicy);
            } catch (\Throwable $e) {
                Log::warning('UpdateEventInsurancePolicyAction: settlement sync failed', [
                    'event_id' => $event->id,
                    'policy_id' => $policy->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return $policy->fresh() ?? $policy;
        });
    }
}
