<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventSettlementCostGroup extends Model
{
    public const KEY_TRANSPORT = 'transport';

    public const KEY_ACCOMMODATION = 'accommodation';

    public const KEY_PROGRAM = 'program';

    public const KEY_INSURANCE = 'insurance';

    public const KEY_OTHER = 'other';

    /** @var array<string, string> */
    public static array $defaultGroups = [
        self::KEY_TRANSPORT => 'Transport',
        self::KEY_ACCOMMODATION => 'Noclegi',
        self::KEY_PROGRAM => 'Program / atrakcje',
        self::KEY_INSURANCE => 'Ubezpieczenie',
        self::KEY_OTHER => 'Inne',
    ];

    protected $fillable = [
        'settlement_id',
        'name',
        'key',
        'sort_order',
        'is_system',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_system' => 'boolean',
    ];

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(EventSettlement::class, 'settlement_id');
    }

    public function costs(): HasMany
    {
        return $this->hasMany(EventSettlementCost::class, 'finance_group_id');
    }

    public static function defaultKeyForSourceType(?string $sourceType): string
    {
        return match ($sourceType) {
            'transport' => self::KEY_TRANSPORT,
            'accommodation' => self::KEY_ACCOMMODATION,
            'insurance_day' => self::KEY_INSURANCE,
            'program_point' => self::KEY_PROGRAM,
            default => self::KEY_OTHER,
        };
    }
}
