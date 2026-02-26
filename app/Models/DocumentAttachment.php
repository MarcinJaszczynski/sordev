<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentAttachment extends Model
{
    protected $fillable = ['document_id','path','original_name','mime_type','order_number'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
