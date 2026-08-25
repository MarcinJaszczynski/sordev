<?php

namespace App\Models;

use App\Enums\EventVehicleRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventVehicle extends Model
{
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'event_id',
        'vehicle_id',
        'role',
        'starts_on',
        'ends_on',
        'sort_order',
        'notes',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'role' => EventVehicleRole::class,
        'starts_on' => 'date',
        'ends_on' => 'date',
        'sort_order' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
