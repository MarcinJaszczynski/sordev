<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentScheduleTemplateInstallment extends Model
{
    public const SHARE_PERCENT = 'percent';

    public const SHARE_FIXED_PLN = 'fixed_pln';

    public const SHARE_FOREIGN = 'foreign';

    public const PAID_BY_OFFICE = 'office';

    public const PAID_BY_PILOT = 'pilot';

    /** @var array<string, string> */
    public static array $shareTypes = [
        self::SHARE_PERCENT => 'Procent ceny',
        self::SHARE_FIXED_PLN => 'Kwota PLN',
        self::SHARE_FOREIGN => 'Waluta (np. pilot)',
    ];

    /** @var array<string, string> */
    public static array $paidByOptions = [
        self::PAID_BY_OFFICE => 'Biuro',
        self::PAID_BY_PILOT => 'Pilot',
    ];

    protected $fillable = [
        'payment_schedule_template_id',
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

    public function template(): BelongsTo
    {
        return $this->belongsTo(PaymentScheduleTemplate::class, 'payment_schedule_template_id');
    }

    public function isForeign(): bool
    {
        return $this->share_type === self::SHARE_FOREIGN;
    }

    public function resolvedDueOffsetFrom(): int
    {
        return (int) ($this->due_offset_from_days ?? $this->due_offset_days ?? 0);
    }

    public function resolvedDueOffsetTo(): int
    {
        return (int) ($this->due_offset_to_days ?? $this->due_offset_days ?? 0);
    }
}
