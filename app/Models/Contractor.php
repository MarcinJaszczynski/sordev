<?php

namespace App\Models;

use App\Enums\ContractorSettlementForm;
use App\Models\Concerns\HasStickyNotes;
use App\Models\Concerns\HasTasks;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

/**
 * Model Contractor
 * Reprezentuje kontrahenta w systemie.
 *
 * @property int $id
 * @property string $name
 * @property string|null $street
 * @property string|null $house_number
 * @property string|null $city
 * @property string|null $postal_code
 * @property string $status
 * @property string|null $office_notes
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Contractor extends Model
{
    use HasFactory, HasStickyNotes, HasTasks, SoftDeletes;

    /**
     * Pola masowo przypisywalne
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'legacy_contractor_id',
        'name',
        'firstname',
        'surname',
        'email',
        'phone',
        'nip',
        'bank_account',
        'settlement_form',
        'www',
        'street',
        'house_number',
        'city',
        'postal_code',
        'region',
        'country',
        'description',
        'status',
        'office_notes',
        'birth_date',
        'pesel',
        'uses_business_locations',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'uses_business_locations' => 'boolean',
        'settlement_form' => ContractorSettlementForm::class,
    ];

    /**
     * Czy kontrahent ma typ „pilot” (po nazwie typu).
     */
    public function hasPilotType(): bool
    {
        if ($this->relationLoaded('types')) {
            return $this->types->contains(fn (ContractorType $type) => strcasecmp((string) $type->name, 'pilot') === 0);
        }

        return $this->types()
            ->whereRaw('LOWER(contractor_types.name) = ?', ['pilot'])
            ->exists();
    }

    public static function contactPivotTable(): string
    {
        if (Schema::hasTable('contractor_contact')) {
            return 'contractor_contact';
        }

        if (Schema::hasTable('contact_contractor')) {
            return 'contact_contractor';
        }

        return 'contractor_contact';
    }

    public static function hasContactPivotTable(): bool
    {
        return Schema::hasTable('contractor_contact') || Schema::hasTable('contact_contractor');
    }

    /**
     * Relacja wiele-do-wielu z kontaktami
     */
    public function contacts()
    {
        return $this->belongsToMany(Contact::class, static::contactPivotTable());
    }

    /**
     * Relacja wiele-do-wielu z typami kontrahentów
     */
    public function types()
    {
        return $this->belongsToMany(ContractorType::class, 'contractor_contractortype')->withTimestamps();
    }

    /**
     * @param  array<int, string>  $typeNames
     */
    public function scopeWithAnyTypeName(Builder $query, array $typeNames): Builder
    {
        if (! Schema::hasTable('contractor_types') || ! Schema::hasTable('contractor_contractortype')) {
            return $query;
        }

        $normalized = array_values(array_filter(array_map(
            static fn ($name) => is_string($name) ? mb_strtolower(trim($name)) : null,
            $typeNames
        )));

        if ($normalized === []) {
            return $query;
        }

        return $query->whereHas('types', function (Builder $typeQuery) use ($normalized): void {
            $placeholders = implode(',', array_fill(0, count($normalized), '?'));
            $typeQuery->whereRaw('LOWER(contractor_types.name) IN ('.$placeholders.')', $normalized);
        });
    }

    /**
     * Punkty programu imprezy wykonywane przez tego kontrahenta
     */
    public function programPoints()
    {
        return $this->hasMany(EventProgramPoint::class);
    }

    /**
     * Wydatki/koszty rozliczenia związane z tym kontrahentą
     */
    public function settlementCosts()
    {
        return $this->hasMany(EventSettlementCost::class);
    }

    /**
     * Noclegi hotelowe przypisane do tego kontrahenta
     */
    public function hotelStays(): HasMany
    {
        return $this->hasMany(EventHotelStay::class);
    }

    /**
     * Imprezy, w których kontrahent jest zamawiającym
     */
    public function orderingEvents()
    {
        return $this->belongsToMany(Event::class, 'event_contractor')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    /**
     * Imprezy z legacy FK events.contractor_id (główny klient)
     */
    public function clientEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'contractor_id');
    }

    /**
     * Imprezy archiwalne (stary SOR) — zamawiający lub wykonawca (pivot contractor_legacy_event).
     */
    public function legacyEvents(): BelongsToMany|HasMany
    {
        if (Schema::hasTable('contractor_legacy_event')) {
            return $this->belongsToMany(LegacyEvent::class, 'contractor_legacy_event')
                ->withPivot(['role', 'type_name'])
                ->withTimestamps()
                ->orderByDesc('legacy_events.start_datetime');
        }

        return $this->hasMany(LegacyEvent::class, 'contractor_id')->orderByDesc('start_datetime');
    }

    /**
     * Tylko jako zamawiający (FK legacy_events.contractor_id).
     */
    public function legacyEventsAsPurchaser(): HasMany
    {
        return $this->hasMany(LegacyEvent::class, 'contractor_id')->orderByDesc('start_datetime');
    }

    public function transportEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'transport_contractor_id');
    }

    public function driverEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'driver_contractor_id');
    }

    public function pilotEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'pilot_contractor_id');
    }

    /**
     * Rezerwacje złożone przez tego kontrahenta
     */
    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function vendorInvoices()
    {
        return $this->hasMany(VendorInvoice::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class)->orderBy('registration_number');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(ContractorLocation::class)->orderByDesc('is_primary')->orderBy('name');
    }

    public function activeLocations(): HasMany
    {
        return $this->locations()->where('status', 'active');
    }

    public function usesBusinessLocations(): bool
    {
        if (! Schema::hasColumn('contractors', 'uses_business_locations')) {
            return false;
        }

        return (bool) $this->uses_business_locations;
    }

    public function defaultLocation(): ?ContractorLocation
    {
        if (! Schema::hasTable('contractor_locations')) {
            return null;
        }

        $this->loadMissing('activeLocations');

        $primary = $this->activeLocations->firstWhere('is_primary', true);

        return $primary ?? $this->activeLocations->first();
    }

    public function displayLabel(): string
    {
        if (filled($this->name)) {
            return trim((string) $this->name);
        }

        $parts = array_filter([
            filled($this->firstname) ? trim((string) $this->firstname) : null,
            filled($this->surname) ? trim((string) $this->surname) : null,
        ]);

        if ($parts !== []) {
            return implode(' ', $parts);
        }

        if (filled($this->email)) {
            return trim((string) $this->email);
        }

        return "Kontrahent #{$this->id}";
    }

    /**
     * @return array<int, string>
     */
    public static function filamentSelectOptions(): array
    {
        return static::query()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (self $contractor): array => [
                (int) $contractor->id => $contractor->displayLabel(),
            ])
            ->all();
    }
}
