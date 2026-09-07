<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventBusCollection extends Model
{
    use HasFactory;

    public const STATUS_PLANNED = 'planned';

    public const STATUS_COLLECTED = 'collected';

    public const STATUS_HANDED_TO_OFFICE = 'handed_to_office';

    public const STATUS_CONFIRMED = 'confirmed';

    /** Statusy, w których gotówka jest (lub była) u pilota i zasila saldo. */
    public const HELD_STATUSES = [
        self::STATUS_COLLECTED,
    ];

    /** Statusy z faktyczną zbiórką (historia wpływu do kasy pilota). */
    public const REALIZED_STATUSES = [
        self::STATUS_COLLECTED,
        self::STATUS_HANDED_TO_OFFICE,
        self::STATUS_CONFIRMED,
    ];

    public static array $statuses = [
        self::STATUS_PLANNED => 'Plan (do zebrania)',
        self::STATUS_COLLECTED => 'Zebrano w autokarze',
        self::STATUS_HANDED_TO_OFFICE => 'Przekazano do biura',
        self::STATUS_CONFIRMED => 'Potwierdzono w biurze',
    ];

    protected $fillable = [
        'event_id',
        'settlement_id',
        'title',
        'collected_at',
        'amount',
        'planned_amount',
        'currency_id',
        'participant_count',
        'amount_per_person',
        'status',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'collected_at' => 'datetime',
        'amount' => 'decimal:2',
        'planned_amount' => 'decimal:2',
        'amount_per_person' => 'decimal:2',
        'participant_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->recorded_by ??= auth()->id();
            $model->status ??= self::STATUS_COLLECTED;

            if ($model->event_id && ! $model->settlement_id) {
                $event = Event::find($model->event_id);
                if ($event) {
                    $model->settlement_id = EventSettlement::findOrCreateActiveForEvent($event)->id;
                }
            }

            if ($model->planned_amount === null && $model->status === self::STATUS_PLANNED) {
                $model->planned_amount = $model->amount;
            }

            if ($model->amount_per_person === null) {
                $model->syncAmountPerPerson();
            }
        });

        static::saving(function (self $model): void {
            if ($model->amount_per_person !== null && (int) ($model->participant_count ?? 0) > 0) {
                return;
            }

            $model->syncAmountPerPerson();
        });
    }

    public function syncAmountPerPerson(): void
    {
        $count = max(0, (int) ($this->participant_count ?? 0));
        $amount = (float) ($this->amount ?? 0);

        if ($count > 0 && $amount > 0) {
            $this->amount_per_person = round($amount / $count, 2);
        }
    }

    public function isPlanned(): bool
    {
        return $this->status === self::STATUS_PLANNED;
    }

    public function isHeldByPilot(): bool
    {
        return in_array($this->status, self::HELD_STATUSES, true);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(EventSettlement::class, 'settlement_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
