<?php

namespace App\Services;

use App\Models\Contractor;
use Illuminate\Support\Facades\Schema;

class ContractorLookupService
{
    /**
     * @param  array<int, string>  $typeNames
     * @return array<int, string>
     */
    public function searchOptions(
        string $search = '',
        array $typeNames = [],
        bool $searchAll = false,
        ?int $includeId = null,
        int $limit = 50,
        ?array $restrictToIds = null,
    ): array {
        $query = Contractor::query()->orderBy('name');

        if ($restrictToIds !== null) {
            if ($restrictToIds === []) {
                return $includeId
                    ? $this->optionsForIds([$includeId], $includeId)
                    : [];
            }

            $query->whereIn('id', $restrictToIds);
        }

        if (! $searchAll && $typeNames !== []) {
            $query->withAnyTypeName($typeNames);
        }

        $search = trim($search);

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");

                if (Schema::hasColumn('contractors', 'nip')) {
                    $q->orWhere('nip', 'like', "%{$search}%");
                }

                $q->orWhere('street', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('postal_code', 'like', "%{$search}%");
            });
        }

        $contractors = $query->limit($limit)->get();

        if ($includeId && ! $contractors->contains('id', $includeId)) {
            $included = Contractor::query()->find($includeId);

            if ($included) {
                $contractors->prepend($included);
            }
        }

        return $contractors
            ->unique('id')
            ->mapWithKeys(fn (Contractor $contractor): array => [
                (int) $contractor->id => $this->formatOptionLabel($contractor),
            ])
            ->all();
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, string>
     */
    public function optionsForIds(array $ids, ?int $includeId = null): array
    {
        $ids = collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($includeId && ! in_array($includeId, $ids, true)) {
            array_unshift($ids, $includeId);
        }

        if ($ids === []) {
            return [];
        }

        return Contractor::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Contractor $contractor): array => [
                (int) $contractor->id => $this->formatOptionLabel($contractor),
            ])
            ->all();
    }

    public function formatOptionLabel(Contractor $contractor): string
    {
        $city = filled($contractor->city) ? trim((string) $contractor->city) : 'brak miasta';

        return $contractor->displayLabel().' ('.$city.')';
    }
}
