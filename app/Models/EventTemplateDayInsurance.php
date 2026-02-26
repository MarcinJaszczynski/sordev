<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventTemplateDayInsurance extends Model
{
    protected $table = 'event_template_day_insurance';
    protected $fillable = [
        'event_template_id',
        'day',
        'insurance_id',
    ];

    public function insurance()
    {
        return $this->belongsTo(Insurance::class);
    }

    public function eventTemplate()
    {
        return $this->belongsTo(EventTemplate::class);
    }
}
