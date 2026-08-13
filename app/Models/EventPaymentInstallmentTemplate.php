<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventPaymentInstallmentTemplate extends Model
{
    public const SHARE_PERCENT = 'percent';

    public const SHARE_FIXED_PLN = 'fixed_pln';

    public const SHARE_FOREIGN = 'foreign';

    public const PAID_BY_OFFICE = 'office';

    public const PAID_BY_PILOT = 'pilot';

    public static array $shareTypes = [
        self::SHARE_PERCENT => 'Procent ceny',
        self::SHARE_FIXED_PLN => 'Kwota PLN',
        self::SHARE_FOREIGN => 'Waluta (np. pilot)',
    ];

    public static array $paidByOptions = [
        self::PAID_BY_OFFICE => 'Biuro',
        self::PAID_BY_PILOT => 'Pilot',
    ];

    protected $fillable = [
        'event_id',
        'sort_order',
        'label',
        'share_type',
        'percent',
        'amount_pln',
        'amount_foreign',
        'currency_code',
        'paid_by',
        'due_offset_days',
        'due_offset_from_days',
        'due_offset_to_days',
        'notes',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'percent' => 'decimal:4',
        'amount_pln' => 'decimal:2',
        'amount_foreign' => 'decimal:2',
        'due_offset_days' => 'integer',
        'due_offset_from_days' => 'integer',
        'due_offset_to_days' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function isForeign(): bool
    {
        return $this->share_type === self::SHARE_FOREIGN;
    }
}
