<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistTemplateItem extends Model
{
    protected $fillable = [
        'checklist_template_id',
        'title',
        'description',
        'input_type',
        'input_label',
        'input_required',
        'input_unit',
        'sort_order',
    ];

    protected $casts = [
        'input_required' => 'boolean',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplate::class, 'checklist_template_id');
    }
}
