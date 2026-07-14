<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventBusCollection extends Model
{
    use HasFactory;

    public static array $statuses = [
        'collected' => 'Zebrano w autokarze',
        'handed_to_office' => 'Przekazano do biura',
        'confirmed' => 'Potwierdzono w biurze',
    ];

    protected $fillable = [
        'event_id',
        'settlement_id',
        'title',
        'collected_at',
        'amount',
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
        'amount_per_person' => 'decimal:2',
        'participant_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->recorded_by ??= auth()->id();

            if ($model->event_id && ! $model->settlement_id) {
                $event = Event::find($model->event_id);
                if ($event) {
                    $model->settlement_id = EventSettlement::findOrCreateActiveForEvent($event)->id;
                }
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
