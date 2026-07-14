<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PilotAdvanceLine extends Model
{
    public const PHASE_PLANNED = 'planned';

    public const PHASE_PAID = 'paid';

    protected $fillable = [
        'event_id',
        'currency_id',
        'amount',
        'phase',
        'sort_order',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
