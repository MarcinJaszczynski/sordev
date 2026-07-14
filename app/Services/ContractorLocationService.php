<?php

namespace App\Services;

use App\Models\Contractor;
use App\Models\ContractorLocation;
use Illuminate\Support\Facades\Schema;

class ContractorLocationService
{
    /**
     * @return array<int, string>
     */
    public function optionsForContractor(?int $contractorId, ?int $includeId = null, string $search = ''): array
    {
        if (! $contractorId || ! Schema::hasTable('contractor_locations')) {
            return [];
        }

        $query = ContractorLocation::query()
            ->where('contractor_id', $contractorId)
            ->active()
            ->orderByDesc('is_primary')
            ->orderBy('name');

        $search = trim($search);

        if ($search !== '') {
            $query->where(function ($inner) use ($search): void {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('street', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $locations = $query->limit(50)->get();

        if ($includeId && ! $locations->contains('id', $includeId)) {
            $included = ContractorLocation::query()->find($includeId);

            if ($included && (int) $included->contractor_id === (int) $contractorId) {
                $locations->prepend($included);
            }
        }

        return $locations
            ->unique('id')
            ->mapWithKeys(fn (ContractorLocation $location): array => [
                (int) $location->id => $location->shortLabel(),
            ])
            ->all();
    }

    public function contractorRequiresLocationSelection(?int $contractorId): bool
    {
        if (! $contractorId || ! Schema::hasTable('contractor_locations')) {
            return false;
        }

        $contractor = Contractor::query()->find($contractorId);

        if (! $contractor || ! $contractor->usesBusinessLocations()) {
            return false;
        }

        return $contractor->activeLocations()->exists();
    }

    public function resolveForSelection(?int $contractorId, ?int $locationId): ?ContractorLocation
    {
        if (! $locationId || ! $contractorId || ! Schema::hasTable('contractor_locations')) {
            return null;
        }

        return ContractorLocation::query()
            ->whereKey($locationId)
            ->where('contractor_id', $contractorId)
            ->first();
    }

    public function syncLocationOnContractorChange(callable $set, ?int $contractorId): void
    {
        $set('contractor_location_id', null);

        if (! $contractorId) {
            return;
        }

        $contractor = Contractor::query()->find($contractorId);

        if (! $contractor?->usesBusinessLocations()) {
            return;
        }

        $default = $contractor->defaultLocation();

        if ($default) {
            $set('contractor_location_id', $default->getKey());
        }
    }

    public function validateLocationBelongsToContractor(?int $contractorId, ?int $locationId): bool
    {
        if (! $locationId) {
            return true;
        }

        return $this->resolveForSelection($contractorId, $locationId) !== null;
    }
}
