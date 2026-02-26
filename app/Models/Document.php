<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    protected $fillable = ['document_section_id','title','slug','excerpt','content','order_number','is_published'];

    public function section(): BelongsTo
    {
        return $this->belongsTo(DocumentSection::class, 'document_section_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(DocumentAttachment::class)->orderBy('order_number');
    }
}
