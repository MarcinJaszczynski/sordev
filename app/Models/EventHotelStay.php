<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventHotelStay extends Model
{
    protected $fillable = [
        'event_id',
        'day',
        'contractor_id',
        'contractor_location_id',
        'event_program_point_id',
        'reservation_id',
        'offer_notes',
        'notes',
        'same_as_day',
        'pricing_mode',
        'flat_amount',
        'offer_flat_amount',
        'flat_currency_id',
        'flat_convert_to_pln',
    ];

    protected $casts = [
        'day' => 'integer',
        'same_as_day' => 'integer',
        'flat_amount' => 'decimal:2',
        'offer_flat_amount' => 'decimal:2',
        'flat_convert_to_pln' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function contractorLocation(): BelongsTo
    {
        return $this->belongsTo(ContractorLocation::class, 'contractor_location_id');
    }

    public function programPoint(): BelongsTo
    {
        return $this->belongsTo(EventProgramPoint::class, 'event_program_point_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * Linie uzgodnione (P) — operacyjna struktura, obsada, numery pokoi.
     * Domyślna relacja zachowana dla kompatybilności (occupancy / settlement).
     */
    public function roomLines(): HasMany
    {
        $relation = $this->hasMany(EventHotelRoomLine::class)->orderBy('order');

        if (\Illuminate\Support\Facades\Schema::hasColumn('event_hotel_room_lines', 'price_layer')) {
            $relation->where('price_layer', EventHotelRoomLine::LAYER_NEGOTIATED);
        }

        return $relation;
    }

    /** Linie ofertowe (S) — struktura i ceny z szablonu. */
    public function offerRoomLines(): HasMany
    {
        $relation = $this->hasMany(EventHotelRoomLine::class)->orderBy('order');

        if (\Illuminate\Support\Facades\Schema::hasColumn('event_hotel_room_lines', 'price_layer')) {
            $relation->where('price_layer', EventHotelRoomLine::LAYER_OFFER);
        } else {
            // Bez warstw — brak osobnej struktury ofertowej.
            $relation->whereRaw('0 = 1');
        }

        return $relation;
    }

    /** Wszystkie linie (S + P). */
    public function allRoomLines(): HasMany
    {
        return $this->hasMany(EventHotelRoomLine::class)->orderBy('price_layer')->orderBy('order');
    }

    public function totalPln(
        ?string $eventPricingMode = 'lines',
        ?int $peoplePerNight = null,
        ?string $priceSource = null,
    ): float {
        $source = \App\Support\HotelCalculationSource::normalize(
            $priceSource ?? \App\Support\HotelCalculationSource::NEGOTIATED
        );

        // Flat modes wycofane z kalkulacji — zawsze suma z linii wybranej warstwy.
        $lines = $source === \App\Support\HotelCalculationSource::OFFER
            && \Illuminate\Support\Facades\Schema::hasColumn('event_hotel_room_lines', 'price_layer')
            ? $this->offerRoomLines
            : $this->roomLines;

        if ($source === \App\Support\HotelCalculationSource::OFFER && $lines->isEmpty()) {
            $lines = $this->roomLines;
        }

        return round((float) $lines->sum(
            fn (EventHotelRoomLine $line) => $line->lineTotalPln($source)
        ), 2);
    }

    public function isComplete(): bool
    {
        return $this->roomLines()->exists();
    }
}
