<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventPriceDescription extends Model
{
    protected $table = 'event_price_descriptions';
    protected $fillable = [
        'name',
        'description', // HTML-formatted text
    ];

    // Relacja: jeden opis może być przypisany do wielu event_template
    public function eventTemplates()
    {
        return $this->belongsToMany(
            \App\Models\EventTemplate::class,
            'event_template_event_price_description',
            'event_price_description_id',
            'event_template_id'
        );
    }
}
