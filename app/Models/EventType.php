<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventType extends Model
{
    protected $fillable = [
        'name',
        'desc',
    ];

    public function eventTemplates()
    {
        return $this->belongsToMany(EventTemplate::class);
    }
}
