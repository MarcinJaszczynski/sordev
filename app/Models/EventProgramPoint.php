<?php

namespace App\Models;

use App\Models\Concerns\HasStickyNotes;
use App\Models\Concerns\HasTasks;
use App\Services\ProgramPointContractorSync;
use App\Services\ProgramPointPricingCalculator;
use App\Support\CurrencyAmountDisplay;
use App\Support\ProgramPointCostPricing;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class EventProgramPoint extends Model
{
    use HasFactory, HasStickyNotes, HasTasks, SoftDeletes;

    public static bool $suppressSideEffects = false;

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function runWithoutSideEffects(callable $callback): mixed
    {
        $previous = static::$suppressSideEffects;
        static::$suppressSideEffects = true;

        try {
            return $callback();
        } finally {
            static::$suppressSideEffects = $previous;
        }
    }

    protected $fillable = [
        'event_id',
        'event_template_program_point_id',
        'name',
        'description',
        'office_notes',
        'pilot_notes',
        'day',
        'order',
        'start_time',
        'end_time',
        'start_date',
        'end_date',
        'hide_times',
        'times_manually_locked',
        'duration_hours',
        'duration_minutes',
        'featured_image',
        'gallery_images',
        'unit_price',
        'quantity',
        'unit',
        'total_price',
        'calculated_price',
        'planned_price',
        'paid_price',
        'notes',
        'include_in_program',
        'include_in_calculation',
        'include_gratis_in_cost',
        'include_pilot_in_cost',
        'include_driver_in_cost',
        'active',
        'show_title_style',
        'show_description',
        'group_size',
        'currency_id',
        'convert_to_pln',
        'parent_id',
        'contractor_id',
        'contractor_location_id',
        'reservation_id',
        'is_hotel',
        'is_transport',
        'is_hotel_service',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'calculated_price' => 'decimal:2',
        'planned_price' => 'decimal:2',
        'paid_price' => 'decimal:2',
        'include_in_program' => 'boolean',
        'include_in_calculation' => 'boolean',
        'include_gratis_in_cost' => 'boolean',
        'include_pilot_in_cost' => 'boolean',
        'include_driver_in_cost' => 'boolean',
        'active' => 'boolean',
        'show_title_style' => 'boolean',
        'show_description' => 'boolean',
        'is_hotel' => 'boolean',
        'is_transport' => 'boolean',
        'is_hotel_service' => 'boolean',
        'gallery_images' => 'array',
        'convert_to_pln' => 'boolean',
        'hide_times' => 'boolean',
        'times_manually_locked' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'include_gratis_in_cost' => false,
        'include_pilot_in_cost' => false,
        'include_driver_in_cost' => false,
    ];

    /**
     * Parent program point (for hierarchical structure)
     */
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Child program points
     */
    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('order');
    }

    protected static function booted()
    {
        static::saving(function ($point) {
            $groupSize = (int) ($point->group_size ?? 0);
            $event = $point->event;
            $paying = max(1, (int) ($event?->participant_count ?? 1));
            $costHeadcount = $event
                ? ProgramPointCostPricing::costHeadcountForPoint($point, $event, $paying)
                : $paying;

            if ($groupSize > 0) {
                $point->quantity = ProgramPointPricingCalculator::billableUnits($costHeadcount, $groupSize);
            }

            // Automatycznie oblicz total_price
            $point->total_price = $point->resolveEffectiveTotalPrice($costHeadcount);

            // Kalkulacja (kosztorys) — zawsze live z unit_price × osoby koszowe.
            // Plan (ustalenia) NIE jest tu nadpisywany przy zmianie ceny.
            $point->calculated_price = $point->resolveCalculationTotal($paying);
        });

        // Seed planu tylko przy tworzeniu — default = kosztorys; potem plan żyje osobno.
        static::creating(function ($point) {
            $point->include_pilot_in_cost = (bool) ($point->include_pilot_in_cost ?? false);
            $point->include_driver_in_cost = (bool) ($point->include_driver_in_cost ?? false);
            $point->include_gratis_in_cost = (bool) ($point->include_gratis_in_cost ?? false);

            if (is_null($point->planned_price) || (float) $point->planned_price == 0.0) {
                $participants = max(1, (int) ($point->event?->participant_count ?? 1));
                $point->planned_price = $point->calculated_price
                    ?? $point->resolveCalculationTotal($participants);
            }
        });

        static::updated(function ($point) {
            if (static::$suppressSideEffects) {
                return;
            }

            $event = $point->event;
            if (! $event) {
                return;
            }

            $changes = $point->getChanges();
            $pointName = self::resolvePointName($point);

            foreach ($changes as $field => $newValue) {
                $oldValue = $point->getOriginal($field);
                $event->logHistory(
                    'program_changed',
                    "program_point.{$field}",
                    $oldValue,
                    $newValue,
                    "Zmieniono {$field} w punkcie programu: {$pointName}"
                );
            }

            if (array_key_exists('contractor_id', $changes)) {
                if (Schema::hasColumn('event_program_points', 'contractor_location_id')) {
                    $point->contractor_location_id = null;
                    $point->saveQuietly();
                }

                app(ProgramPointContractorSync::class)->sync($point);
            }

            // Przelicz całkowity koszt imprezy
            $event->calculateTotalCost();
            $event->refreshActiveSettlementCosts();
            $event->syncTransportFromProgramPoints();
        });

        static::created(function ($point) {
            if (static::$suppressSideEffects) {
                return;
            }

            $event = $point->event;
            if (! $event) {
                return;
            }

            $event->logHistory(
                'program_added',
                null,
                null,
                $point->toArray(),
                'Dodano punkt programu: '.self::resolvePointName($point)
            );

            // Przelicz całkowity koszt imprezy
            $event->calculateTotalCost();
            $event->refreshActiveSettlementCosts();
            $event->syncTransportFromProgramPoints();
        });

        static::deleted(function ($point) {
            $pointIds = self::query()
                ->withTrashed()
                ->where('event_id', $point->event_id)
                ->where(function ($query) use ($point): void {
                    $query->where('id', $point->id)
                        ->orWhere('parent_id', $point->id);
                })
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all();

            EventSettlementCost::query()
                ->whereIn('source_type', ['program_point', 'program_point_payment'])
                ->whereIn('source_id', $pointIds)
                ->delete();

            $event = $point->event;
            if (! $event) {
                return;
            }

            $event->logHistory(
                'program_removed',
                null,
                $point->toArray(),
                null,
                'Usunięto punkt programu: '.self::resolvePointName($point)
            );

            // Przelicz całkowity koszt imprezy
            $event->calculateTotalCost();
            $event->refreshActiveSettlementCosts();
            $event->syncTransportFromProgramPoints();
        });
    }

    /**
     * Impreza
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function settlementCosts(): HasMany
    {
        return $this->hasMany(EventSettlementCost::class, 'source_id')
            ->where('source_type', 'program_point');
    }

    /**
     * Szablon punktu programu
     */
    public function templatePoint(): BelongsTo
    {
        return $this->belongsTo(EventTemplateProgramPoint::class, 'event_template_program_point_id');
    }

    /**
     * Waluta punktu programu
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * Kontrahent (wykonawca) punktu programu
     */
    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function contractorLocation(): BelongsTo
    {
        return $this->belongsTo(ContractorLocation::class, 'contractor_location_id');
    }

    public function vendorInvoices(): BelongsToMany
    {
        return $this->belongsToMany(
            VendorInvoice::class,
            'vendor_invoice_program_point'
        )->withTimestamps();
    }

    /**
     * Legacy: faktury z kolumny event_program_point_id (główny punkt).
     */
    public function primaryVendorInvoices(): HasMany
    {
        return $this->hasMany(VendorInvoice::class, 'event_program_point_id');
    }

    /**
     * Rezerwacje dla tego punktu programu
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'program_point_id');
    }

    public function sharedReservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id');
    }

    public function hotelStays(): HasMany
    {
        return $this->hasMany(EventHotelStay::class, 'event_program_point_id');
    }

    /**
     * Rezerwacja widoczna przy punkcie: wspólna grupa (hotel / transport / szablon), własna albo noc hotelu.
     */
    public function latestVisibleReservation(): ?Reservation
    {
        $shared = $this->relationLoaded('sharedReservation')
            ? $this->sharedReservation
            : (filled($this->reservation_id) ? $this->sharedReservation()->first() : null);

        if ($shared instanceof Reservation && $shared->isActiveBooking()) {
            return $shared;
        }

        $own = ($this->relationLoaded('reservations') ? $this->reservations : $this->reservations()->get())
            ->filter(function (Reservation $reservation): bool {
                if (method_exists($reservation, 'trashed') && $reservation->trashed()) {
                    return false;
                }

                return $reservation->isActiveBooking();
            })
            ->sortByDesc('id')
            ->first();

        if ($own) {
            return $own;
        }

        $stays = $this->relationLoaded('hotelStays')
            ? $this->hotelStays
            : $this->hotelStays()->with('reservation')->get();

        $fromStay = $stays
            ->map(fn (EventHotelStay $stay) => $stay->reservation)
            ->filter(fn ($reservation) => $reservation instanceof Reservation && $reservation->isActiveBooking())
            ->sortByDesc('id')
            ->first();

        if ($fromStay instanceof Reservation) {
            return $fromStay;
        }

        return app(\App\Services\ProgramPointReservationSync::class)->findForPoint($this);
    }

    /**
     * Oblicz koszt całkowity na podstawie ceny jednostkowej i ilości
     */
    public function calculateTotalPrice(): void
    {
        $this->total_price = ($this->unit_price ?? 0) * ($this->quantity ?? 1);
        $this->save();
    }

    public function resolveCalculatedQuantity(?int $participantCount = null): int
    {
        $count = max(1, (int) ($participantCount ?? $this->event?->participant_count ?? 1));

        return ProgramPointPricingCalculator::billableUnits(
            $count,
            $this->group_size,
            max(1, (int) ($this->quantity ?? 1)),
        );
    }

    public function resolveEffectiveTotalPrice(?int $participantCount = null): float
    {
        $count = max(1, (int) ($participantCount ?? $this->event?->participant_count ?? 1));

        return ProgramPointPricingCalculator::totalPrice(
            (float) ($this->unit_price ?? 0),
            $count,
            $this->group_size,
            max(1, (int) ($this->quantity ?? 1)),
        );
    }

    public function resolveCalculationTotal(?int $participantCount = null): float
    {
        $this->loadMissing(['event', 'currency', 'templatePoint']);

        if ($this->event) {
            return (float) ProgramPointCostPricing::breakdown($this, $this->event, $participantCount)['total'];
        }

        $count = max(1, (int) ($participantCount ?? 1));
        $unitPrice = (float) ($this->unit_price ?? $this->templatePoint?->unit_price ?? 0);

        return ProgramPointPricingCalculator::totalPrice(
            $unitPrice,
            $count,
            $this->group_size,
            max(1, (int) ($this->quantity ?? 1)),
        );
    }

    public function formatAmount(float|int|string|null $amount, int $decimals = 2): string
    {
        return CurrencyAmountDisplay::format(
            (float) ($amount ?? 0),
            $this->currency,
            (bool) ($this->convert_to_pln ?? false),
            $decimals,
        );
    }

    public function resolvedDescription(): ?string
    {
        return filled($this->description)
            ? $this->description
            : ($this->templatePoint?->description ?: null);
    }

    public function resolvedPilotNotes(): ?string
    {
        return filled($this->pilot_notes)
            ? $this->pilot_notes
            : ($this->templatePoint?->pilot_notes ?: null);
    }

    public function resolvedOfficeNotes(): ?string
    {
        return filled($this->office_notes)
            ? $this->office_notes
            : ($this->templatePoint?->office_notes ?: null);
    }

    public function hasResolvedPilotNotes(): bool
    {
        return filled(trim(strip_tags((string) ($this->resolvedPilotNotes() ?? ''))));
    }

    public function hasResolvedOfficeNotes(): bool
    {
        return filled(trim(strip_tags((string) ($this->resolvedOfficeNotes() ?? ''))));
    }

    public function hasPilotPortalDetails(): bool
    {
        return filled($this->resolvedDescription()) || filled($this->resolvedPilotNotes());
    }

    /**
     * Duplikuj punkt programu
     */
    public function duplicate(): self
    {
        return self::create([
            'event_id' => $this->event_id,
            'event_template_program_point_id' => $this->event_template_program_point_id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'day' => $this->day,
            'order' => $this->getNextOrderInDay(),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'hide_times' => $this->hide_times,
            'unit_price' => $this->unit_price,
            'quantity' => $this->quantity,
            'total_price' => $this->total_price,
            'currency_id' => $this->currency_id,
            'contractor_id' => $this->contractor_id,
            'notes' => $this->notes,
            'include_in_program' => $this->include_in_program,
            'include_in_calculation' => $this->include_in_calculation,
            'include_gratis_in_cost' => $this->include_gratis_in_cost,
            'include_pilot_in_cost' => $this->include_pilot_in_cost,
            'include_driver_in_cost' => $this->include_driver_in_cost,
            'active' => $this->active,
            'show_title_style' => $this->show_title_style,
            'show_description' => $this->show_description,
            'is_hotel' => $this->is_hotel,
            'is_transport' => $this->is_transport,
            'is_hotel_service' => $this->is_hotel_service,
        ]);
    }

    /**
     * Pobierz następny numer kolejności w danym dniu
     */
    private function getNextOrderInDay(): int
    {
        $maxOrder = self::where('event_id', $this->event_id)
            ->where('day', $this->day)
            ->max('order');

        return ($maxOrder ?? 0) + 1;
    }

    /**
     * Przenieś do innego dnia
     */
    public function moveToDay(int $newDay): void
    {
        $oldDay = $this->day;
        $this->day = $newDay;
        $this->order = $this->getNextOrderInDay();
        $this->save();

        if (! $this->event) {
            return;
        }

        $this->event->logHistory(
            'program_moved',
            'program_point.day',
            $oldDay,
            $newDay,
            "Przeniesiono punkt programu '".self::resolvePointName($this)."' z dnia {$oldDay} do dnia {$newDay}"
        );
    }

    private static function resolvePointName(self $point): string
    {
        return $point->name
            ?? $point->templatePoint?->name
            ?? 'Bez nazwy';
    }
}
