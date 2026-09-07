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
        'pilot_photos',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'role' => EventVehicleRole::class,
        'starts_on' => 'date',
        'ends_on' => 'date',
        'sort_order' => 'integer',
        'pilot_photos' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return list<string>
     */
    public function pilotPhotoPaths(): array
    {
        return collect((array) $this->pilot_photos)
            ->filter(fn (mixed $path): bool => filled($path))
            ->map(fn (mixed $path): string => (string) $path)
            ->values()
            ->all();
    }

    public function appendPilotPhoto(string $path): void
    {
        $photos = $this->pilotPhotoPaths();
        $photos[] = $path;

        $this->forceFill(['pilot_photos' => array_values(array_unique($photos))])->save();
    }

    public function removePilotPhoto(string $path): void
    {
        $photos = array_values(array_filter(
            $this->pilotPhotoPaths(),
            fn (string $existing): bool => $existing !== $path,
        ));

        $this->forceFill(['pilot_photos' => $photos !== [] ? $photos : null])->save();
    }
}
