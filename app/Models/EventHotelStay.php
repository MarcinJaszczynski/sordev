<?php

namespace App\Models;

use App\Services\EventHotelOccupancyService;
use App\Support\EventHotelPlanFormatting;
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

    public function roomLines(): HasMany
    {
        return $this->hasMany(EventHotelRoomLine::class)->orderBy('order');
    }

    public function totalPln(
        ?string $eventPricingMode = 'lines',
        ?int $peoplePerNight = null,
        ?string $priceSource = null,
    ): float {
        $source = \App\Support\HotelCalculationSource::normalize(
            $priceSource ?? \App\Support\HotelCalculationSource::NEGOTIATED
        );

        if (EventHotelPlanFormatting::isEventFlatPricing($eventPricingMode)) {
            return 0.0;
        }

        $flatAmount = $source === \App\Support\HotelCalculationSource::OFFER
            ? ($this->offer_flat_amount ?? $this->flat_amount)
            : $this->flat_amount;

        if (EventHotelPlanFormatting::isStayFlatPricing($this->pricing_mode) && $flatAmount !== null) {
            $people = $peoplePerNight ?? $this->requiredBedsForPricing();
            $amount = EventHotelPlanFormatting::resolveFlatNativeAmount(
                (float) $flatAmount,
                $this->pricing_mode,
                $people,
            );
            $currency = $this->flat_currency_id
                ? Currency::query()->find($this->flat_currency_id)
                : null;

            if (! $currency || $currency->symbol === 'PLN') {
                return $amount;
            }

            if (! (bool) ($this->flat_convert_to_pln ?? true)) {
                return 0.0;
            }

            return round($amount * (float) ($currency->exchange_rate ?? 1), 2);
        }

        return round((float) $this->roomLines->sum(
            fn (EventHotelRoomLine $line) => $line->lineTotalPln($source)
        ), 2);
    }

    protected function requiredBedsForPricing(): int
    {
        $this->loadMissing('event');

        if (! $this->event) {
            return 0;
        }

        return (int) app(EventHotelOccupancyService::class)
            ->forEvent($this->event)['required_beds_per_night'];
    }

    public function isComplete(): bool
    {
        return $this->roomLines()->exists();
    }
}
