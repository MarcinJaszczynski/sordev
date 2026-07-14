<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class TfgDictionaryItem extends Model
{
    public const TYPE_SUBJECT = 'subject';

    public const TYPE_PAYMENT_METHOD = 'payment_method';

    public const TYPE_TRANSPORT = 'transport';

    public const TYPE_COUNTRY = 'country';

    public const TYPE_OPERATION = 'operation';

    public const TYPE_CORRECTION_REASON = 'correction_reason';

    public const TYPE_SCOPE = 'scope';

    public const TYPE_CURRENCY = 'currency';

    public const TYPE_AIRPORT = 'airport';

    protected $fillable = [
        'type',
        'code',
        'label',
        'is_active',
        'meta',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    public static function optionsFor(string $type): array
    {
        return static::query()
            ->where('type', $type)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->pluck('label', 'code')
            ->all();
    }

    public static function requiresIcao(string $transportCode): bool
    {
        $item = static::query()
            ->where('type', self::TYPE_TRANSPORT)
            ->where('code', $transportCode)
            ->first();

        return (bool) data_get($item?->meta, 'requires_icao', false);
    }

    public static function activeByType(string $type): Collection
    {
        return static::query()
            ->where('type', $type)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    public static function codesFor(string $type): array
    {
        return static::query()
            ->where('type', $type)
            ->where('is_active', true)
            ->pluck('code')
            ->all();
    }

    public static function isValidCode(string $type, ?string $code): bool
    {
        if (blank($code)) {
            return false;
        }

        return static::query()
            ->where('type', $type)
            ->where('code', $code)
            ->where('is_active', true)
            ->exists();
    }

    public static function scopeForCountry(string $countryCode): ?string
    {
        $item = static::query()
            ->where('type', self::TYPE_COUNTRY)
            ->where('code', $countryCode)
            ->first();

        return $item ? (string) data_get($item->meta, 'scope') ?: null : null;
    }
}
