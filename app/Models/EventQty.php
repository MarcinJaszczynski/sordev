<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventQty extends Model
{
    protected $table = 'event_qties';

    protected $fillable = [
        'event_id',
        'qty',
        'gratis',
        'staff',
        'driver',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (is_null($model->staff)) {
                $model->staff = 1;
            }
            if (is_null($model->driver)) {
                $model->driver = 1;
            }
        });
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
