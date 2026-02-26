<?php

namespace App\Models;

use App\Enums\Visibility;
use App\Enums\Status;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tag extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'visibility',
        'status',
    ];

    protected $casts = [
        'visibility' => Visibility::class,
        'status' => Status::class,
    ];

    public function eventTemplates()
    {
        return $this->belongsToMany(EventTemplate::class, 'event_template_tag');
    }

    
    public function eventTemplateProgramPoints()
    {
        return $this->belongsToMany(EventTemplateProgramPoint::class);
    }
}
