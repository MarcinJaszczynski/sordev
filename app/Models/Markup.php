<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Markup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'percent',
        'discount_percent',
        'discount_start',
        'discount_end',
        'is_default',
        'min_daily_amount_pln',
    ];

    protected $casts = [
        'discount_start' => 'date',
        'discount_end' => 'date',
        'is_default' => 'boolean',
        'percent' => 'float',
        'min_daily_amount_pln' => 'float',
    ];

    public static function boot()
    {
        parent::boot();
        static::saving(function ($model) {
            if ($model->is_default) {
                static::where('id', '!=', $model->id)->update(['is_default' => false]);
            }
        });
    }

    /**
     * Narzut PLN: max(procent od bazy, min. kwota/dzień × liczba dni).
     * Minimum dzienne dotyczy wyłącznie PLN (waluty obce liczą sam %).
     *
     * @return array{
     *     amount: float,
     *     percent_applied: float,
     *     min_daily_applied: bool,
     *     min_daily_floor: float
     * }
     */
    public static function calculateAmount(
        float $basePln,
        float $percent,
        float $minDailyAmountPln = 0.0,
        int $days = 1,
    ): array {
        $days = max(1, $days);
        $percentAmount = round($basePln * ($percent / 100), 2);
        $minFloor = round(max(0, $minDailyAmountPln) * $days, 2);
        $minApplied = $minFloor > $percentAmount;

        return [
            'amount' => $minApplied ? $minFloor : $percentAmount,
            'percent_applied' => $percent,
            'min_daily_applied' => $minApplied,
            'min_daily_floor' => $minFloor,
        ];
    }

    /**
     * @return array{
     *     amount: float,
     *     percent_applied: float,
     *     min_daily_applied: bool,
     *     min_daily_floor: float
     * }
     */
    public function amountForBase(float $basePln, int $days = 1): array
    {
        return self::calculateAmount(
            $basePln,
            (float) ($this->percent ?? 0),
            (float) ($this->min_daily_amount_pln ?? 0),
            $days,
        );
    }
}
