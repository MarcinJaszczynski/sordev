<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurrencyRateSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'currency_id',
        'rate',
        'purchase_rate',
        'sale_rate',
        'source',
        'rate_date',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'rate'          => 'decimal:4',
        'purchase_rate' => 'decimal:4',
        'sale_rate'     => 'decimal:4',
        'rate_date'     => 'date',
    ];

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function settlementCosts(): HasMany
    {
        return $this->hasMany(EventSettlementCost::class, 'rate_snapshot_id');
    }

    public function pilotCashPreparations(): HasMany
    {
        return $this->hasMany(PilotCashPreparation::class, 'rate_snapshot_id');
    }

    /** Najnowszy kurs dla danej waluty */
    public static function latestFor(int $currencyId): ?self
    {
        return static::where('currency_id', $currencyId)
            ->orderByDesc('rate_date')
            ->orderByDesc('id')
            ->first();
    }
}
