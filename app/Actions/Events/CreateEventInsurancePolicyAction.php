<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Models\Event;
use App\Models\EventDayInsurance;
use App\Models\EventInsurancePolicy;
use App\Services\EventInsurancePolicySettlementSync;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Tworzy polisę operacyjną imprezy i opcjonalnie produkty na wskazany dzień.
 */
final class CreateEventInsurancePolicyAction
{
    /**
     * @param  array<string, mixed>  $policyFormData  pola insurance_* z formularza
     * @param  list<int|string>  $insuranceIds
     */
    public function __invoke(
        Event $event,
        array $policyFormData,
        ?int $day = null,
        array $insuranceIds = [],
    ): EventInsurancePolicy {
        return DB::transaction(function () use ($event, $policyFormData, $day, $insuranceIds): EventInsurancePolicy {
            $policy = EventInsurancePolicy::query()->create(array_merge(
                ['event_id' => $event->id],
                EventInsurancePolicy::attributesFromFormData($policyFormData),
            ));

            if ($day !== null && $day > 0 && $insuranceIds !== []) {
                app(AddEventDayInsurancesAction::class)(
                    $event,
                    $day,
                    $insuranceIds,
                    $policy->id,
                );
            } elseif (
                Schema::hasColumn('event_day_insurance', 'event_insurance_policy_id')
                && ($day === null || $insuranceIds === [])
            ) {
                // Legacy / EditEvent: podepnij istniejące pozycje bez polisy.
                EventDayInsurance::query()
                    ->where('event_id', $event->id)
                    ->whereNull('event_insurance_policy_id')
                    ->update(['event_insurance_policy_id' => $policy->id]);
            }

            $event->refreshInsuranceAggregateMirror();

            $freshPolicy = $policy->fresh() ?? $policy;
            app(\App\Services\EventInsuranceOperationalSync::class)->syncPolicyFromSettlement($freshPolicy);

            try {
                app(EventInsurancePolicySettlementSync::class)->syncPolicy($freshPolicy);
            } catch (\Throwable $e) {
                Log::warning('CreateEventInsurancePolicyAction: settlement sync failed', [
                    'event_id' => $event->id,
                    'policy_id' => $policy->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return $policy->fresh() ?? $policy;
        });
    }
}
