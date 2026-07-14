<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContractorType extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    /** @var array<string, array<int, int>> */
    private static array $idsForNamesCache = [];

    /**
     * Id typu „pilot” (nazwa case-insensitive), do warunków w formularzu.
     */
    public static function pilotTypeId(): ?int
    {
        return static::idForName('pilot');
    }

    /**
     * @return array<int, string>
     */
    public static function transportTypeNames(): array
    {
        return ['przewoźnik', 'kierowca'];
    }

    /**
     * @return array<int, string>
     */
    public static function hotelTypeNames(): array
    {
        return ['hotel'];
    }

    public static function idForName(string $name): ?int
    {
        $ids = static::idsForNames([$name]);

        return $ids[0] ?? null;
    }

    /**
     * @param  array<int, string>  $names
     * @return array<int, int>
     */
    public static function idsForNames(array $names): array
    {
        $names = array_values(array_filter(array_map(
            static fn ($name) => is_string($name) ? mb_strtolower(trim($name)) : null,
            $names
        )));

        if ($names === []) {
            return [];
        }

        sort($names);
        $cacheKey = implode('|', $names);

        if (isset(static::$idsForNamesCache[$cacheKey])) {
            return static::$idsForNamesCache[$cacheKey];
        }

        $placeholders = implode(',', array_fill(0, count($names), '?'));

        $ids = static::query()
            ->whereRaw('LOWER(name) IN ('.$placeholders.')', $names)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        static::$idsForNamesCache[$cacheKey] = $ids;

        return $ids;
    }

    public function contractors()
    {
        return $this->belongsToMany(Contractor::class, 'contractor_contractortype');
    }
}
