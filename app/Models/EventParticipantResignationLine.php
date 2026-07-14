<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventParticipantResignationLine extends Model
{
    protected $fillable = [
        'resignation_id',
        'program_point_id',
        'description',
        'refunded_amount_pln',
        'retained_amount_pln',
        'notes',
    ];

    protected $casts = [
        'refunded_amount_pln' => 'decimal:2',
        'retained_amount_pln' => 'decimal:2',
    ];

    public function resignation(): BelongsTo
    {
        return $this->belongsTo(EventParticipantResignation::class, 'resignation_id');
    }

    public function programPoint(): BelongsTo
    {
        return $this->belongsTo(EventProgramPoint::class, 'program_point_id');
    }
}
