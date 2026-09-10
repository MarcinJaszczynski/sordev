<?php

namespace App\Support;

use App\Models\Currency;
use App\Models\EventHotelRoomLine;
use App\Models\HotelRoom;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class EventHotelPlanFormatting
{
    /**
     * Cena jednostkowa linii wg źródła (offer vs negotiated).
     *
     * @param  array<string, mixed>  $line
     */
    public static function resolveLineUnitPrice(array $line, ?string $source = null): float
    {
        $source = HotelCalculationSource::normalize(
            $source ?? HotelCalculationSource::NEGOTIATED
        );
        $negotiated = round((float) ($line['unit_price'] ?? 0), 2);

        if ($source === HotelCalculationSource::OFFER) {
            if (! array_key_exists('offer_unit_price', $line) || $line['offer_unit_price'] === null || $line['offer_unit_price'] === '') {
                return $negotiated;
            }

            $offer = round((float) $line['offer_unit_price'], 2);
            if ($offer === 0.0 && $negotiated > 0.0) {
                return $negotiated;
            }

            return $offer;
        }

        return $negotiated;
    }

    /**
     * Flat amount nocy wg źródła.
     *
     * @param  array<string, mixed>  $stay
     */
    public static function resolveStayFlatAmount(array $stay, ?string $source = null): ?float
    {
        $source = HotelCalculationSource::normalize(
            $source ?? HotelCalculationSource::NEGOTIATED
        );

        if ($source === HotelCalculationSource::OFFER) {
            if (array_key_exists('offer_flat_amount', $stay) && $stay['offer_flat_amount'] !== null && $stay['offer_flat_amount'] !== '') {
                return (float) $stay['offer_flat_amount'];
            }
        }

        if (! isset($stay['flat_amount']) || $stay['flat_amount'] === '') {
            return null;
        }

        return (float) $stay['flat_amount'];
    }

    /**
     * Projekcja payloadu stayów na jedną warstwę cenową (unit_price / flat_amount),
     * żeby istniejące helpery sumujące po unit_price działały bez zmian API.
     *
     * @param  array<int, array<string, mixed>>  $stays
     * @return array{stays: array<int, array<string, mixed>>, flat_stay_amount: float|null}
     */
    public static function projectStaysForSource(
        array $stays,
        ?string $source,
        ?float $negotiatedFlatStayAmount = null,
        ?float $offerFlatStayAmount = null,
    ): array {
        $source = HotelCalculationSource::normalize($source);
        $projected = [];

        foreach ($stays as $stay) {
            $copy = $stay;
            $flat = self::resolveStayFlatAmount($stay, $source);
            $copy['flat_amount'] = $flat;

            $lines = [];
            foreach ($stay['room_lines'] ?? [] as $line) {
                $lineCopy = $line;
                $lineCopy['unit_price'] = self::resolveLineUnitPrice($line, $source);
                $lines[] = $lineCopy;
            }
            $copy['room_lines'] = $lines;
            $projected[] = $copy;
        }

        $flatStay = $source === HotelCalculationSource::OFFER
            ? ($offerFlatStayAmount ?? $negotiatedFlatStayAmount)
            : $negotiatedFlatStayAmount;

        return [
            'stays' => $projected,
            'flat_stay_amount' => $flatStay,
        ];
    }

    /**
     * @param  array<string, mixed>  $line
     */
    public static function lineLabel(array $line, ?Collection $hotelRoomsById = null): string
    {
        $roomId = $line['hotel_room_id'] ?? null;
        if ($roomId) {
            if ($hotelRoomsById && $hotelRoomsById->has($roomId)) {
                return (string) $hotelRoomsById->get($roomId)->name;
            }

            return HotelRoom::query()->find($roomId)?->name ?? 'Pokój';
        }

        return trim((string) ($line['label'] ?? '')) ?: 'Pokój';
    }

    public static function linePeopleCount(array $line, ?Collection $hotelRoomsById = null): int
    {
        if (! empty($line['people_count'])) {
            return max(1, (int) $line['people_count']);
        }

        $roomId = filled($line['hotel_room_id'] ?? null) ? (int) $line['hotel_room_id'] : null;
        if (! $roomId) {
            return 1;
        }

        $room = $hotelRoomsById?->get($roomId);
        if (! $room instanceof HotelRoom) {
            // Bez kolekcji (sumy nocy / settlement) — dociągamy z katalogu.
            $room = HotelRoom::query()->find($roomId);
        }

        if (! $room) {
            return 1;
        }

        return max(1, (int) ($room->capacity ?? $room->people_count ?? 1));
    }

    /**
     * @param  array<string, mixed>  $line
     */
    public static function lineNativeTotal(array $line, ?Collection $hotelRoomsById = null): float
    {
        return EventHotelRoomLine::calculateLineTotal(
            (float) ($line['unit_price'] ?? 0),
            (int) ($line['quantity'] ?? 1),
            self::linePeopleCount($line, $hotelRoomsById),
            $line['price_basis'] ?? EventHotelRoomLine::PRICE_BASIS_PER_ROOM,
        );
    }

    /**
     * @param  array<string, mixed>  $line
     */
    public static function lineSummary(array $line, ?Collection $hotelRoomsById = null, ?Collection $currenciesById = null): string
    {
        $qty = max(1, (int) ($line['quantity'] ?? 1));
        $price = (float) ($line['unit_price'] ?? 0);
        $label = self::lineLabel($line, $hotelRoomsById);
        $people = self::linePeopleCount($line, $hotelRoomsById);
        $symbol = self::lineCurrencySymbol($line, $currenciesById);
        $perUnit = EventHotelRoomLine::normalizePriceBasis($line['price_basis'] ?? null) === EventHotelRoomLine::PRICE_BASIS_PER_PERSON
            ? 'os.'
            : 'pok.';

        return sprintf(
            '%d× %s (%d os.) po %s %s/%s',
            $qty,
            $label,
            $people,
            number_format($price, 0, ',', ' '),
            $symbol,
            $perUnit
        );
    }

    /**
     * @param  array<string, mixed>  $stay
     */
    public static function staySummary(array $stay, ?Collection $hotelRoomsById = null, ?Collection $currenciesById = null): string
    {
        $parts = [];
        foreach ($stay['room_lines'] ?? [] as $line) {
            $parts[] = self::lineSummary($line, $hotelRoomsById, $currenciesById);
        }

        return $parts !== [] ? implode(' · ', $parts) : 'Brak pokoi — uzupełnij strukturę';
    }

    public static function resolveCurrency(?int $currencyId, ?Collection $currenciesById = null): ?Currency
    {
        if (! $currencyId) {
            return null;
        }

        $fromDb = Currency::query()->find($currencyId);
        if ($fromDb) {
            return $fromDb;
        }

        if ($currenciesById?->has($currencyId)) {
            return new Currency([
                'id' => $currencyId,
                'symbol' => (string) $currenciesById->get($currencyId),
                'exchange_rate' => 1,
            ]);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $line
     */
    public static function lineCurrencySymbol(array $line, ?Collection $currenciesById = null): string
    {
        $currencyId = $line['currency_id'] ?? null;
        if ($currencyId && $currenciesById?->has($currencyId)) {
            return (string) $currenciesById->get($currencyId);
        }

        if ($currencyId) {
            return Currency::query()->find($currencyId)?->symbol ?? 'PLN';
        }

        return 'PLN';
    }

    /**
     * @param  array<string, mixed>  $line
     */
    public static function lineTotalPln(array $line, ?Collection $currenciesById = null, ?Collection $hotelRoomsById = null): float
    {
        $total = self::lineNativeTotal($line, $hotelRoomsById);
        $symbol = self::lineCurrencySymbol($line, $currenciesById);

        if ($symbol === 'PLN') {
            return $total;
        }

        if (! (bool) ($line['convert_to_pln'] ?? true)) {
            return 0.0;
        }

        $currencyId = $line['currency_id'] ?? null;
        $rate = 1.0;
        if ($currencyId) {
            $rate = (float) (Currency::query()->find($currencyId)?->exchange_rate ?? 1);
        }

        return round($total * $rate, 2);
    }

    /**
     * @param  array<string, mixed>  $line
     */
    public static function lineTotalDisplay(array $line, ?Collection $currenciesById = null, ?Collection $hotelRoomsById = null): string
    {
        $total = self::lineNativeTotal($line, $hotelRoomsById);
        $currency = self::resolveCurrency($line['currency_id'] ?? null, $currenciesById);

        return CurrencyAmountDisplay::format(
            $total,
            $currency,
            (bool) ($line['convert_to_pln'] ?? true),
            0,
        );
    }

    public static function countPersonSlots(array $stay, ?Collection $hotelRoomsById = null): int
    {
        return count(self::expandedPersonSlots($stay, $hotelRoomsById));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function expandedPersonSlots(array $stay, ?Collection $hotelRoomsById = null): array
    {
        $slots = [];
        $slotNumber = 0;

        foreach ($stay['room_lines'] ?? [] as $lineIndex => $line) {
            $typeLabel = self::lineLabel($line, $hotelRoomsById);
            $totalUnits = max(1, (int) ($line['quantity'] ?? 1));
            $bedsPerRoom = self::linePeopleCount($line, $hotelRoomsById);
            $lineUnits = collect($line['units'] ?? [])->keyBy('unit_index');
            $occupantsBySlot = collect($line['occupants'] ?? [])->keyBy(
                fn (array $o) => ((int) ($o['unit_index'] ?? 1)).':'.((int) ($o['bed_index'] ?? 1))
            );

            for ($unit = 1; $unit <= $totalUnits; $unit++) {
                $dbUnit = $lineUnits->get($unit);

                for ($bed = 1; $bed <= $bedsPerRoom; $bed++) {
                    $slotNumber++;
                    $occupant = $occupantsBySlot->get("{$unit}:{$bed}");

                    $slots[] = [
                        'slot_number' => $slotNumber,
                        'line_index' => (int) $lineIndex,
                        'unit_index' => $unit,
                        'bed_index' => $bed,
                        'total_units' => $totalUnits,
                        'beds_per_room' => $bedsPerRoom,
                        'room_type' => $typeLabel,
                        'room_label' => $totalUnits > 1 ? "{$typeLabel} · pokój {$unit}/{$totalUnits}" : $typeLabel,
                        'bed_label' => $bedsPerRoom > 1 ? "miejsce {$bed}/{$bedsPerRoom}" : 'miejsce 1/1',
                        'unit_id' => $dbUnit['id'] ?? null,
                        'room_number' => $dbUnit['room_number'] ?? null,
                        'occupant' => $occupant,
                    ];
                }
            }
        }

        return $slots;
    }

    public static function isEventFlatPricing(?string $eventMode): bool
    {
        return in_array($eventMode, ['flat_stay', 'flat_stay_per_person'], true);
    }

    public static function isStayFlatPricing(?string $stayMode): bool
    {
        return in_array($stayMode, ['flat_night', 'flat_night_per_person'], true);
    }

    public static function usesLinePricing(?string $eventMode, ?string $stayMode = 'lines'): bool
    {
        if (self::isEventFlatPricing($eventMode)) {
            return false;
        }

        return ($stayMode ?? 'lines') === 'lines';
    }

    /**
     * Kwota flat w walucie źródłowej (po ewentualnym × osoby).
     */
    public static function resolveFlatNativeAmount(float $amount, ?string $mode, int $peoplePerNight = 0): float
    {
        $amount = round($amount, 2);

        if (in_array($mode, ['flat_stay_per_person', 'flat_night_per_person'], true)) {
            return round($amount * max(0, $peoplePerNight), 2);
        }

        return $amount;
    }

    /**
     * @param  array<string, mixed>  $stay
     */
    public static function stayTotalPln(
        array $stay,
        ?string $eventPricingMode = 'lines',
        ?Collection $currenciesById = null,
        int $peoplePerNight = 0,
        ?Collection $hotelRoomsById = null,
    ): float {
        if (self::isEventFlatPricing($eventPricingMode)) {
            return 0.0;
        }

        $stayMode = $stay['pricing_mode'] ?? 'lines';
        if (self::isStayFlatPricing($stayMode) && isset($stay['flat_amount']) && $stay['flat_amount'] !== '') {
            $amount = self::resolveFlatNativeAmount((float) $stay['flat_amount'], $stayMode, $peoplePerNight);
            $currencyId = $stay['flat_currency_id'] ?? null;
            $symbol = $currencyId && $currenciesById?->has($currencyId)
                ? (string) $currenciesById->get($currencyId)
                : (Currency::query()->find($currencyId)?->symbol ?? 'PLN');

            if ($symbol === 'PLN' || ! (bool) ($stay['flat_convert_to_pln'] ?? true)) {
                return $symbol === 'PLN' ? $amount : 0.0;
            }

            $rate = (float) (Currency::query()->find($currencyId)?->exchange_rate ?? 1);

            return round($amount * $rate, 2);
        }

        $total = 0.0;
        foreach ($stay['room_lines'] ?? [] as $line) {
            $total += self::lineTotalPln($line, $currenciesById, $hotelRoomsById);
        }

        return round($total, 2);
    }

    /**
     * @param  array<int, array<string, mixed>>  $stays
     */
    public static function eventTotalPln(
        array $stays,
        ?string $eventPricingMode = 'lines',
        ?float $flatStayAmount = null,
        ?int $flatStayCurrencyId = null,
        bool $flatStayConvertToPln = true,
        ?Collection $currenciesById = null,
        int $peoplePerNight = 0,
        ?Collection $hotelRoomsById = null,
    ): float {
        if (self::isEventFlatPricing($eventPricingMode) && $flatStayAmount !== null) {
            $amount = self::resolveFlatNativeAmount($flatStayAmount, $eventPricingMode, $peoplePerNight);
            $symbol = $flatStayCurrencyId && $currenciesById?->has($flatStayCurrencyId)
                ? (string) $currenciesById->get($flatStayCurrencyId)
                : (Currency::query()->find($flatStayCurrencyId)?->symbol ?? 'PLN');

            if ($symbol === 'PLN' || ! $flatStayConvertToPln) {
                return $symbol === 'PLN' ? $amount : 0.0;
            }

            $rate = (float) (Currency::query()->find($flatStayCurrencyId)?->exchange_rate ?? 1);

            return round($amount * $rate, 2);
        }

        return round(collect($stays)->sum(
            fn (array $stay) => self::stayTotalPln($stay, $eventPricingMode, $currenciesById, $peoplePerNight, $hotelRoomsById)
        ), 2);
    }

    /**
     * @param  array<string, mixed>  $stay
     */
    public static function stayTotalDisplay(
        array $stay,
        ?string $eventPricingMode = 'lines',
        ?Collection $currenciesById = null,
        int $peoplePerNight = 0,
        ?Collection $hotelRoomsById = null,
    ): string {
        if (self::isEventFlatPricing($eventPricingMode)) {
            return '—';
        }

        $stayMode = $stay['pricing_mode'] ?? 'lines';
        if (self::isStayFlatPricing($stayMode) && isset($stay['flat_amount']) && $stay['flat_amount'] !== '') {
            $currency = self::resolveCurrency($stay['flat_currency_id'] ?? null, $currenciesById);
            $amount = self::resolveFlatNativeAmount((float) $stay['flat_amount'], $stayMode, $peoplePerNight);

            return CurrencyAmountDisplay::format(
                $amount,
                $currency,
                (bool) ($stay['flat_convert_to_pln'] ?? true),
                0,
            );
        }

        return self::formatMixedBreakdown(
            collect($stay['room_lines'] ?? [])->all(),
            $currenciesById,
            0,
            $hotelRoomsById,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $stays
     */
    public static function eventTotalDisplay(
        array $stays,
        ?string $eventPricingMode = 'lines',
        ?float $flatStayAmount = null,
        ?int $flatStayCurrencyId = null,
        bool $flatStayConvertToPln = true,
        ?Collection $currenciesById = null,
        int $peoplePerNight = 0,
        ?Collection $hotelRoomsById = null,
    ): string {
        if (self::isEventFlatPricing($eventPricingMode) && $flatStayAmount !== null) {
            $currency = self::resolveCurrency($flatStayCurrencyId, $currenciesById);
            $amount = self::resolveFlatNativeAmount($flatStayAmount, $eventPricingMode, $peoplePerNight);

            return CurrencyAmountDisplay::format($amount, $currency, $flatStayConvertToPln, 0);
        }

        $plnPart = 0.0;
        $foreignBuckets = [];

        foreach ($stays as $stay) {
            $stayMode = $stay['pricing_mode'] ?? 'lines';
            if (self::isStayFlatPricing($stayMode) && isset($stay['flat_amount']) && $stay['flat_amount'] !== '') {
                $currency = self::resolveCurrency($stay['flat_currency_id'] ?? null, $currenciesById);
                $amount = self::resolveFlatNativeAmount((float) $stay['flat_amount'], $stayMode, $peoplePerNight);
                $symbol = CurrencyAmountDisplay::symbol($currency);
                $pln = CurrencyAmountDisplay::plnEquivalent($amount, $currency, (bool) ($stay['flat_convert_to_pln'] ?? true));

                if ($pln !== null) {
                    $plnPart += $pln;
                } elseif ($symbol !== 'PLN') {
                    $foreignBuckets[$symbol] = ($foreignBuckets[$symbol] ?? 0) + $amount;
                } else {
                    $plnPart += $amount;
                }

                continue;
            }

            foreach ($stay['room_lines'] ?? [] as $line) {
                $total = self::lineNativeTotal($line, $hotelRoomsById);
                $currency = self::resolveCurrency($line['currency_id'] ?? null, $currenciesById);
                $symbol = CurrencyAmountDisplay::symbol($currency);
                $pln = CurrencyAmountDisplay::plnEquivalent($total, $currency, (bool) ($line['convert_to_pln'] ?? true));

                if ($pln !== null) {
                    $plnPart += $pln;
                } elseif ($symbol !== 'PLN') {
                    $foreignBuckets[$symbol] = ($foreignBuckets[$symbol] ?? 0) + $total;
                } else {
                    $plnPart += $total;
                }
            }
        }

        return CurrencyAmountDisplay::formatMixedTotal($plnPart, $foreignBuckets, 0);
    }

    /**
     * Sumy noclegów per waluta (PLN + obce bez konwersji).
     * Klucz = symbol waluty (np. PLN, EUR).
     *
     * @param  array<int, array<string, mixed>>  $stays
     * @return array<string, float>
     */
    public static function eventTotalsByCurrency(
        array $stays,
        ?string $eventPricingMode = 'lines',
        ?float $flatStayAmount = null,
        ?int $flatStayCurrencyId = null,
        bool $flatStayConvertToPln = true,
        ?Collection $currenciesById = null,
        int $peoplePerNight = 0,
        ?Collection $hotelRoomsById = null,
    ): array {
        $buckets = [];

        $add = static function (string $symbol, float $amount) use (&$buckets): void {
            if ($amount <= 0) {
                return;
            }
            $code = strtoupper($symbol ?: 'PLN');
            $buckets[$code] = round(($buckets[$code] ?? 0) + $amount, 2);
        };

        if (self::isEventFlatPricing($eventPricingMode) && $flatStayAmount !== null) {
            $currency = self::resolveCurrency($flatStayCurrencyId, $currenciesById);
            $amount = self::resolveFlatNativeAmount($flatStayAmount, $eventPricingMode, $peoplePerNight);
            $symbol = CurrencyAmountDisplay::symbol($currency);
            $pln = CurrencyAmountDisplay::plnEquivalent($amount, $currency, $flatStayConvertToPln);

            if ($pln !== null) {
                $add('PLN', $pln);
            } else {
                $add($symbol, $amount);
            }

            return $buckets;
        }

        foreach ($stays as $stay) {
            $stayMode = $stay['pricing_mode'] ?? 'lines';
            if (self::isStayFlatPricing($stayMode) && isset($stay['flat_amount']) && $stay['flat_amount'] !== '') {
                $currency = self::resolveCurrency($stay['flat_currency_id'] ?? null, $currenciesById);
                $amount = self::resolveFlatNativeAmount((float) $stay['flat_amount'], $stayMode, $peoplePerNight);
                $symbol = CurrencyAmountDisplay::symbol($currency);
                $pln = CurrencyAmountDisplay::plnEquivalent(
                    $amount,
                    $currency,
                    (bool) ($stay['flat_convert_to_pln'] ?? true),
                );

                if ($pln !== null) {
                    $add('PLN', $pln);
                } else {
                    $add($symbol, $amount);
                }

                continue;
            }

            foreach ($stay['room_lines'] ?? [] as $line) {
                $total = self::lineNativeTotal($line, $hotelRoomsById);
                $currency = self::resolveCurrency($line['currency_id'] ?? null, $currenciesById);
                $symbol = CurrencyAmountDisplay::symbol($currency);
                $pln = CurrencyAmountDisplay::plnEquivalent(
                    $total,
                    $currency,
                    (bool) ($line['convert_to_pln'] ?? true),
                );

                if ($pln !== null) {
                    $add('PLN', $pln);
                } else {
                    $add($symbol, $total);
                }
            }
        }

        return $buckets;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    protected static function formatMixedBreakdown(
        array $lines,
        ?Collection $currenciesById = null,
        int $decimals = 0,
        ?Collection $hotelRoomsById = null,
    ): string {
        $plnPart = 0.0;
        $foreignBuckets = [];

        foreach ($lines as $line) {
            $total = self::lineNativeTotal($line, $hotelRoomsById);
            $currency = self::resolveCurrency($line['currency_id'] ?? null, $currenciesById);
            $symbol = CurrencyAmountDisplay::symbol($currency);
            $pln = CurrencyAmountDisplay::plnEquivalent($total, $currency, (bool) ($line['convert_to_pln'] ?? true));

            if ($pln !== null) {
                $plnPart += $pln;
            } elseif ($symbol !== 'PLN') {
                $foreignBuckets[$symbol] = ($foreignBuckets[$symbol] ?? 0) + $total;
            } else {
                $plnPart += $total;
            }
        }

        return CurrencyAmountDisplay::formatMixedTotal($plnPart, $foreignBuckets, $decimals);
    }

    /**
     * @return array<int, array{line_index: int, unit: int, total_units: int, label: string, unit_id: int|null, room_number: string|null, people_count: int}>
     */
    public static function expandedRoomUnits(array $stay, ?Collection $hotelRoomsById = null): array
    {
        $units = [];

        foreach ($stay['room_lines'] ?? [] as $lineIndex => $line) {
            $baseLabel = self::lineLabel($line, $hotelRoomsById);
            $totalUnits = max(1, (int) ($line['quantity'] ?? 1));
            $peopleCount = self::linePeopleCount($line, $hotelRoomsById);
            $lineUnits = collect($line['units'] ?? [])->keyBy('unit_index');

            for ($unit = 1; $unit <= $totalUnits; $unit++) {
                $dbUnit = $lineUnits->get($unit);
                $units[] = [
                    'line_index' => (int) $lineIndex,
                    'unit' => $unit,
                    'total_units' => $totalUnits,
                    'label' => $totalUnits > 1 ? "{$baseLabel} ({$unit}/{$totalUnits})" : $baseLabel,
                    'unit_id' => $dbUnit['id'] ?? null,
                    'room_number' => $dbUnit['room_number'] ?? null,
                    'people_count' => $peopleCount,
                ];
            }
        }

        return $units;
    }

    /**
     * @param  array<int, array<string, mixed>>  $stays
     */
    public static function structureReady(array $stays): bool
    {
        foreach ($stays as $stay) {
            if (($stay['room_lines'] ?? []) === []) {
                continue;
            }

            foreach ($stay['room_lines'] as $line) {
                $hasType = ! empty($line['hotel_room_id']) || trim((string) ($line['label'] ?? '')) !== '';
                $qty = (int) ($line['quantity'] ?? 0);

                if ($hasType && $qty > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function normalizeRoomMatchKey(string $value): string
    {
        $value = preg_replace('/\s*\(\d+\/\d+\)\s*$/', '', trim($value)) ?? trim($value);
        $value = preg_replace('/\s*—\s*pokój\s*\d+$/iu', '', $value) ?? $value;
        $value = Str::ascii(mb_strtolower(trim($value)));

        return preg_replace('/\s+/', ' ', $value) ?? '';
    }

    public static function displaySurname(string $fullName): string
    {
        $fullName = trim($fullName);
        if ($fullName === '') {
            return '';
        }

        $parts = preg_split('/\s+/u', $fullName) ?: [];
        if (count($parts) <= 1) {
            return $fullName;
        }

        return (string) end($parts);
    }
}
