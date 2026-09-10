<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventHotelRoomLine extends Model
{
    public const PRICE_BASIS_PER_ROOM = 'per_room';

    public const PRICE_BASIS_PER_PERSON = 'per_person';

    public const ROLES = [
        'qty' => 'Uczestnicy',
        'gratis' => \App\Support\EventParticipantGroupLabels::GRATIS,
        'staff' => 'Obsługa',
        'driver' => 'Kierowca',
    ];

    protected $fillable = [
        'event_hotel_stay_id',
        'hotel_room_id',
        'label',
        'role',
        'quantity',
        'people_count',
        'unit_price',
        'offer_unit_price',
        'price_basis',
        'currency_id',
        'convert_to_pln',
        'order',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'people_count' => 'integer',
        'unit_price' => 'decimal:2',
        'offer_unit_price' => 'decimal:2',
        'convert_to_pln' => 'boolean',
        'order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (EventHotelRoomLine $line): void {
            if (! \Illuminate\Support\Facades\Schema::hasColumn('event_hotel_room_lines', 'offer_unit_price')) {
                return;
            }

            // Create / legacy: brak jawnej oferty (default 0) przy niezerowym unit_price → zamroź S = P.
            if (
                ! $line->isDirty('offer_unit_price')
                && (float) ($line->offer_unit_price ?? 0) === 0.0
                && (float) $line->unit_price > 0
            ) {
                $line->offer_unit_price = $line->unit_price;
            }
        });
    }

    public function stay(): BelongsTo
    {
        return $this->belongsTo(EventHotelStay::class, 'event_hotel_stay_id');
    }

    public function hotelRoom(): BelongsTo
    {
        return $this->belongsTo(HotelRoom::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function occupants(): HasMany
    {
        return $this->hasMany(EventHotelRoomOccupant::class)->orderBy('order');
    }

    public function units(): HasMany
    {
        return $this->hasMany(EventHotelRoomUnit::class)->orderBy('unit_index');
    }

    public function effectivePeopleCount(): int
    {
        if ($this->people_count) {
            return (int) $this->people_count;
        }

        if ($this->hotelRoom) {
            return (int) ($this->hotelRoom->capacity ?? $this->hotelRoom->people_count ?? 1);
        }

        return 1;
    }

    public function displayLabel(): string
    {
        if ($this->label) {
            return $this->label;
        }

        return $this->hotelRoom?->name ?? 'Pokój';
    }

    public function resolvedPriceBasis(): string
    {
        $basis = (string) ($this->price_basis ?? self::PRICE_BASIS_PER_ROOM);

        return in_array($basis, [self::PRICE_BASIS_PER_ROOM, self::PRICE_BASIS_PER_PERSON], true)
            ? $basis
            : self::PRICE_BASIS_PER_ROOM;
    }

    /**
     * @return array<string, string>
     */
    public static function priceBasisOptions(): array
    {
        return [
            self::PRICE_BASIS_PER_ROOM => 'Pokój',
            self::PRICE_BASIS_PER_PERSON => 'Osobę',
        ];
    }

    public static function normalizePriceBasis(?string $basis): string
    {
        return in_array((string) $basis, [self::PRICE_BASIS_PER_ROOM, self::PRICE_BASIS_PER_PERSON], true)
            ? (string) $basis
            : self::PRICE_BASIS_PER_ROOM;
    }

    public static function calculateLineTotal(
        float $unitPrice,
        int $quantity,
        int $peopleCount,
        ?string $priceBasis = self::PRICE_BASIS_PER_ROOM,
    ): float {
        $qty = max(1, $quantity);
        $people = max(1, $peopleCount);
        $basis = self::normalizePriceBasis($priceBasis);

        if ($basis === self::PRICE_BASIS_PER_PERSON) {
            return round($unitPrice * $qty * $people, 2);
        }

        return round($unitPrice * $qty, 2);
    }

    /**
     * Cena jednostkowa wg źródła: offer = zamrożona oferta, negotiated = unit_price.
     */
    public function effectiveUnitPrice(?string $source = null): float
    {
        $source = \App\Support\HotelCalculationSource::normalize($source);
        $negotiated = round((float) $this->unit_price, 2);

        if ($source === \App\Support\HotelCalculationSource::OFFER) {
            if ($this->offer_unit_price === null) {
                return $negotiated;
            }

            $offer = round((float) $this->offer_unit_price, 2);
            // Legacy: oferta 0 przy niezerowym P → jeszcze nie zamrożona.
            if ($offer === 0.0 && $negotiated > 0.0) {
                return $negotiated;
            }

            return $offer;
        }

        return $negotiated;
    }

    public function lineTotal(?string $source = null): float
    {
        return self::calculateLineTotal(
            $this->effectiveUnitPrice($source ?? \App\Support\HotelCalculationSource::NEGOTIATED),
            (int) $this->quantity,
            $this->effectivePeopleCount(),
            $this->resolvedPriceBasis(),
        );
    }

    public function lineTotalPln(?string $source = null): float
    {
        $total = $this->lineTotal($source);
        $currency = $this->currency;

        if (! $currency || $currency->symbol === 'PLN') {
            return $total;
        }

        if (! $this->convert_to_pln) {
            return 0.0;
        }

        return round($total * (float) ($currency->exchange_rate ?? 1), 2);
    }
}
