<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventCalculationStage extends Model
{
    public const STAGE_PRELIMINARY = 'preliminary';

    public const STAGE_PREDICTED = 'predicted';

    public static array $stages = [
        self::STAGE_PRELIMINARY => 'Wstępna',
        self::STAGE_PREDICTED => 'Przewidywana',
    ];

    protected $fillable = [
        'event_id',
        'stage',
        'client_price_pln',
        'cost_pln',
        'note',
        'locked_by',
        'locked_at',
    ];

    protected $casts = [
        'client_price_pln' => 'decimal:2',
        'cost_pln' => 'decimal:2',
        'locked_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }
}
