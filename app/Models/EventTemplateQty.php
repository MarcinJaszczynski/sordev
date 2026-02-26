<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Model EventTemplateQty
 * Reprezentuje wariant ilości uczestników dla szablonu wydarzenia.
 *
 * @property int $id
 * @property int $qty
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class EventTemplateQty extends Model
{
    use HasFactory;
    /**
     * Pola masowo przypisywalne
     * @var array<int, string>
     */
    protected $fillable = [
        'event_template_id',
        'qty',
        'gratis',
        'staff',
        'driver',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (is_null($model->gratis)) {
                $model->gratis = (int) ceil($model->qty / 15);
            }
            if (is_null($model->staff)) {
                $model->staff = 1;
            }
            if (is_null($model->driver)) {
                $model->driver = 1;
            }
        });
    }
}
