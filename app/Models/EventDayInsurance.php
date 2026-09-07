<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventDayInsurance extends Model
{
    protected $table = 'event_day_insurance';

    protected $fillable = [
        'event_id',
        'day',
        'insurance_id',
        'event_insurance_policy_id',
        'is_done',
    ];

    protected $casts = [
        'is_done' => 'boolean',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function insurance()
    {
        return $this->belongsTo(Insurance::class);
    }

    public function policy()
    {
        return $this->belongsTo(EventInsurancePolicy::class, 'event_insurance_policy_id');
    }
}
