<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentSection extends Model
{
    protected $fillable = ['title','slug','order_number'];

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class)->orderBy('order_number');
    }
}
