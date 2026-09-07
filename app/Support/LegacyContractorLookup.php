<?php

namespace App\Support;

use App\Models\Contractor;
use Illuminate\Support\Facades\Schema;

/**
 * Mapuje ID kontrahenta ze starego SOR → bieżący Contractor.
 */
final class LegacyContractorLookup
{
    /** @var array<int, int|null> */
    private static array $cache = [];

    public static function currentId(?int $legacyContractorId): ?int
    {
        if ($legacyContractorId === null || $legacyContractorId <= 0) {
            return null;
        }

        if (array_key_exists($legacyContractorId, self::$cache)) {
            return self::$cache[$legacyContractorId];
        }

        $currentId = null;

        if (Schema::hasColumn('contractors', 'legacy_contractor_id')) {
            $currentId = Contractor::withTrashed()
                ->where('legacy_contractor_id', $legacyContractorId)
                ->value('id');
            $currentId = $currentId !== null ? (int) $currentId : null;
        }

        // Fallback: niektóre środowiska zachowały te same ID.
        if ($currentId === null && Contractor::withTrashed()->whereKey($legacyContractorId)->exists()) {
            $currentId = $legacyContractorId;
        }

        return self::$cache[$legacyContractorId] = $currentId;
    }

    public static function name(?int $legacyContractorId, ?string $fallback = null): ?string
    {
        $currentId = self::currentId($legacyContractorId);
        if ($currentId === null) {
            return $fallback;
        }

        $name = Contractor::withTrashed()->whereKey($currentId)->value('name');

        return filled($name) ? (string) $name : $fallback;
    }

    /**
     * @param  list<int|string|null>  $legacyIds
     * @return array<int, array{id: int, name: string}>
     */
    public static function mapMany(array $legacyIds): array
    {
        $ids = collect($legacyIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $out = [];
        foreach ($ids as $legacyId) {
            $currentId = self::currentId($legacyId);
            if ($currentId === null) {
                continue;
            }
            $name = Contractor::withTrashed()->whereKey($currentId)->value('name');
            if (! filled($name)) {
                continue;
            }
            $out[$legacyId] = ['id' => $currentId, 'name' => (string) $name];
        }

        return $out;
    }

    public static function clearCache(): void
    {
        self::$cache = [];
    }
}
