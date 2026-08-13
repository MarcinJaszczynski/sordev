<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\EventSettlementCostGroup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class EventSettlementCostGroupService
{
    /**
     * Zapewnia domyślne grupy i przypisuje koszty bez grupy.
     * Ścieżka odczytu: bez zbędnych UPDATE-ów, gdy grupy i przypisania są kompletne.
     *
     * @return Collection<int, EventSettlementCostGroup>
     */
    public function ensureGroups(EventSettlement $settlement): Collection
    {
        if (! Schema::hasTable('event_settlement_cost_groups')) {
            return collect();
        }

        $groups = $settlement->costGroups()->orderBy('sort_order')->orderBy('id')->get();
        $byKey = $groups->keyBy('key');
        $created = false;

        foreach (EventSettlementCostGroup::$defaultGroups as $key => $name) {
            if ($byKey->has($key)) {
                continue;
            }

            $group = $settlement->costGroups()->create([
                'name' => $name,
                'key' => $key,
                'sort_order' => $groups->count(),
                'is_system' => true,
            ]);
            $byKey->put($key, $group);
            $groups->push($group);
            $created = true;
        }

        if ($created) {
            $groups = $settlement->costGroups()->orderBy('sort_order')->orderBy('id')->get();
            $byKey = $groups->keyBy('key');
        }

        $hasUngrouped = Schema::hasColumn('event_settlement_costs', 'finance_group_id')
            && $settlement->costs()
                ->whereNull('finance_group_id')
                ->where(function ($q): void {
                    $q->whereNull('source_type')
                        ->orWhere('source_type', 'not like', '%_payment');
                })
                ->exists();

        if ($hasUngrouped) {
            $this->autoAssignUngroupedCosts($settlement, $byKey);
            // Odśwież relację kosztów po auto-assign.
            $settlement->unsetRelation('costs');
            $settlement->load(['costs.contractor', 'costs.plannedCurrency', 'costs.actualCurrency', 'costs.financeGroup']);
        }

        return $groups;
    }

    /**
     * @param  Collection<string, EventSettlementCostGroup>  $byKey
     */
    public function autoAssignUngroupedCosts(EventSettlement $settlement, Collection $byKey): void
    {
        if (! Schema::hasColumn('event_settlement_costs', 'finance_group_id')) {
            return;
        }

        $settlement->costs()
            ->whereNull('finance_group_id')
            ->where(function ($q): void {
                $q->whereNull('source_type')
                    ->orWhere('source_type', 'not like', '%_payment');
            })
            ->get()
            ->each(function (EventSettlementCost $cost) use ($byKey): void {
                if (EventSettlementCost::isPaymentSourceType($cost->source_type)) {
                    return;
                }

                $key = EventSettlementCostGroup::defaultKeyForSourceType($cost->source_type);
                $group = $byKey->get($key) ?? $byKey->get(EventSettlementCostGroup::KEY_OTHER);
                if ($group) {
                    $cost->update(['finance_group_id' => $group->id]);
                }
            });
    }

    public function createGroup(EventSettlement $settlement, string $name): EventSettlementCostGroup
    {
        $maxSort = (int) $settlement->costGroups()->max('sort_order');

        return $settlement->costGroups()->create([
            'name' => trim($name),
            'key' => null,
            'sort_order' => $maxSort + 1,
            'is_system' => false,
        ]);
    }

    public function renameGroup(EventSettlementCostGroup $group, string $name): EventSettlementCostGroup
    {
        $group->update(['name' => trim($name)]);

        return $group->fresh();
    }

    public function deleteGroup(EventSettlementCostGroup $group): void
    {
        if ($group->is_system) {
            throw new \InvalidArgumentException('Nie można usunąć grupy systemowej.');
        }

        $settlement = $group->settlement;
        if (! $settlement) {
            throw new \InvalidArgumentException('Grupa nie ma przypisanego rozliczenia.');
        }

        $fallback = $settlement->costGroups()
            ->where('id', '!=', $group->id)
            ->where('key', EventSettlementCostGroup::KEY_OTHER)
            ->first()
            ?? $settlement->costGroups()->where('id', '!=', $group->id)->orderBy('sort_order')->first();

        if (! $fallback) {
            throw new \InvalidArgumentException('Brak grupy docelowej — nie można usunąć ostatniej grupy.');
        }

        EventSettlementCost::query()
            ->where('finance_group_id', $group->id)
            ->update(['finance_group_id' => $fallback->id]);

        $group->delete();
    }

    public function moveCostToGroup(EventSettlementCost $cost, ?int $groupId): void
    {
        if (EventSettlementCost::isPaymentSourceType($cost->source_type)) {
            return;
        }

        if ($groupId !== null) {
            $exists = EventSettlementCostGroup::query()
                ->where('id', $groupId)
                ->where('settlement_id', $cost->settlement_id)
                ->exists();
            if (! $exists) {
                return;
            }
        }

        $cost->update(['finance_group_id' => $groupId]);
    }

    /**
     * @param  list<int>  $orderedGroupIds
     */
    public function reorderGroups(EventSettlement $settlement, array $orderedGroupIds): void
    {
        foreach (array_values($orderedGroupIds) as $index => $groupId) {
            EventSettlementCostGroup::query()
                ->where('settlement_id', $settlement->id)
                ->where('id', $groupId)
                ->update(['sort_order' => $index]);
        }
    }
}
