<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
}
