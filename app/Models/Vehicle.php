<?php

namespace App\Models;

use App\Enums\VehicleStatus;
use App\Enums\VehicleType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use HasFactory;

    public const EQUIPMENT_WIFI = 'wifi';

    public const EQUIPMENT_WC = 'wc';

    public const EQUIPMENT_SOCKETS_230V = 'sockets_230v';

    public const EQUIPMENT_AC = 'ac';

    public const EQUIPMENT_SEATBELTS = 'seatbelts';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'contractor_id',
        'type',
        'brand',
        'model',
        'registration_number',
        'capacity',
        'crew_seats',
        'equipment',
        'status',
        'is_ad_hoc',
        'primary_image',
        'gallery',
        'attachments',
        'notes',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'type' => VehicleType::class,
        'status' => VehicleStatus::class,
        'equipment' => 'array',
        'gallery' => 'array',
        'attachments' => 'array',
        'is_ad_hoc' => 'boolean',
        'capacity' => 'integer',
        'crew_seats' => 'integer',
    ];

    /**
     * @return array<string, string>
     */
    public static function equipmentOptions(): array
    {
        return [
            self::EQUIPMENT_WIFI => 'WiFi',
            self::EQUIPMENT_WC => 'WC',
            self::EQUIPMENT_SOCKETS_230V => 'Gniazdka 230V',
            self::EQUIPMENT_AC => 'Klimatyzacja',
            self::EQUIPMENT_SEATBELTS => 'Pasy bezpieczeństwa',
        ];
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function eventAssignments(): HasMany
    {
        return $this->hasMany(EventVehicle::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', VehicleStatus::Active->value);
    }

    public function scopeForContractor(Builder $query, ?int $contractorId): Builder
    {
        if ($contractorId === null || $contractorId <= 0) {
            return $query->where(function (Builder $builder): void {
                $builder->whereNull('contractor_id')
                    ->orWhere('is_ad_hoc', true);
            });
        }

        return $query->where(function (Builder $builder) use ($contractorId): void {
            $builder->where('contractor_id', $contractorId)
                ->orWhere(function (Builder $adHoc): void {
                    $adHoc->where('is_ad_hoc', true)->whereNull('contractor_id');
                });
        });
    }

    public function displayLabel(): string
    {
        $parts = array_filter([
            trim(implode(' ', array_filter([(string) $this->brand, (string) $this->model]))),
            $this->registration_number ? 'rej. '.$this->registration_number : null,
            $this->capacityLabel(),
            $this->type?->label(),
        ]);

        return $parts !== [] ? implode(' · ', $parts) : 'Pojazd #'.$this->getKey();
    }

    /**
     * np. „49+2” (pasażerowie + załoga) albo sama liczba pasażerska.
     */
    public function capacityLabel(): ?string
    {
        $passengers = $this->capacity !== null ? (int) $this->capacity : null;
        $crew = $this->crew_seats !== null ? (int) $this->crew_seats : null;

        if ($passengers === null && $crew === null) {
            return null;
        }

        if ($passengers !== null && $crew !== null && $crew > 0) {
            return $passengers.'+'.$crew;
        }

        if ($passengers !== null) {
            return (string) $passengers.' miejsc';
        }

        return '+'.$crew.' załoga';
    }

    /**
     * @return list<string>
     */
    public function equipmentLabels(): array
    {
        $selected = is_array($this->equipment) ? $this->equipment : [];
        $options = self::equipmentOptions();

        return collect($selected)
            ->map(fn (mixed $key): ?string => $options[(string) $key] ?? null)
            ->filter()
            ->values()
            ->all();
    }
}
