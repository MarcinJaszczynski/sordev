<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventTemplateProgramPointChild extends Model
{
    protected $table = 'event_template_program_point_child';

    protected $fillable = [
        'event_template_id',
        'program_point_id',
        'include_in_program',
        'include_in_calculation',
        'active',
        'insurance_per_day',
        'insurance_per_person',
        'insurance_enabled',
    ];
}
