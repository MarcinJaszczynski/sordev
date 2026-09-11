<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventHotelRoomLine extends Model
{
    public const PRICE_BASIS_PER_ROOM = 'per_room';

    public const PRICE_BASIS_PER_PERSON = 'per_person';

    /** Warstwa oferty (S) — struktura + ceny z szablonu. */
    public const LAYER_OFFER = 'offer';

    /** Warstwa uzgodniona (P) — struktura + ceny z hotelem; obsada / numery pokoi. */
    public const LAYER_NEGOTIATED = 'negotiated';

    public const ROLES = [
        'qty' => 'Uczestnicy',
        'gratis' => \App\Support\EventParticipantGroupLabels::GRATIS,
        'staff' => 'Obsługa',
        'driver' => 'Kierowca',
    ];

    protected $fillable = [
        'event_hotel_stay_id',
        'price_layer',
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
            if (\Illuminate\Support\Facades\Schema::hasColumn('event_hotel_room_lines', 'price_layer')) {
                $line->price_layer = self::normalizeLayer($line->price_layer);
            }

            if (! \Illuminate\Support\Facades\Schema::hasColumn('event_hotel_room_lines', 'offer_unit_price')) {
                return;
            }

            // Legacy dual-price na jednej linii: brak S przy niezerowym P → zamroź S = P.
            // Przy rozdzielonych warstwach offer_unit_price na linii offer = unit_price.
            if ($line->isOfferLayer()) {
                $line->offer_unit_price = $line->unit_price;

                return;
            }

            if (
                ! $line->isDirty('offer_unit_price')
                && (float) ($line->offer_unit_price ?? 0) === 0.0
                && (float) $line->unit_price > 0
            ) {
                $line->offer_unit_price = $line->unit_price;
            }
        });
    }

    public static function normalizeLayer(?string $layer): string
    {
        return $layer === self::LAYER_OFFER ? self::LAYER_OFFER : self::LAYER_NEGOTIATED;
    }

    public function isOfferLayer(): bool
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('event_hotel_room_lines', 'price_layer')) {
            return false;
        }

        return self::normalizeLayer($this->price_layer) === self::LAYER_OFFER;
    }

    public function isNegotiatedLayer(): bool
    {
        return ! $this->isOfferLayer();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\EventHotelRoomLine>  $query
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\EventHotelRoomLine>
     */
    public function scopeForLayer($query, ?string $layer)
    {
        $normalized = self::normalizeLayer($layer);

        if (! \Illuminate\Support\Facades\Schema::hasColumn('event_hotel_room_lines', 'price_layer')) {
            return $query;
        }

        return $query->where('price_layer', $normalized);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\EventHotelRoomLine>  $query
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\EventHotelRoomLine>
     */
    public function scopeOffer($query)
    {
        return $query->forLayer(self::LAYER_OFFER);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\EventHotelRoomLine>  $query
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\EventHotelRoomLine>
     */
    public function scopeNegotiated($query)
    {
        return $query->forLayer(self::LAYER_NEGOTIATED);
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
     * Cena jednostkowa linii.
     * Przy rozdzielonych warstwach (price_layer) każda linia ma własne unit_price —
     * source służy tylko do legacy dual-price na jednej linii.
     */
    public function effectiveUnitPrice(?string $source = null): float
    {
        $unit = round((float) $this->unit_price, 2);

        if (\Illuminate\Support\Facades\Schema::hasColumn('event_hotel_room_lines', 'price_layer')) {
            return $unit;
        }

        $source = \App\Support\HotelCalculationSource::normalize($source);

        if ($source === \App\Support\HotelCalculationSource::OFFER) {
            if ($this->offer_unit_price === null) {
                return $unit;
            }

            $offer = round((float) $this->offer_unit_price, 2);
            if ($offer === 0.0 && $unit > 0.0) {
                return $unit;
            }

            return $offer;
        }

        return $unit;
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
