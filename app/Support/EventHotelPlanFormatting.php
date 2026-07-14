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

        $roomId = $line['hotel_room_id'] ?? null;
        if ($roomId && $hotelRoomsById?->has($roomId)) {
            $room = $hotelRoomsById->get($roomId);

            return max(1, (int) ($room->capacity ?? $room->people_count ?? 1));
        }

        return 1;
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

    public static function usesLinePricing(?string $eventMode, ?string $stayMode = 'lines'): bool
    {
        if ($eventMode === 'flat_stay') {
            return false;
        }

        return ($stayMode ?? 'lines') === 'lines';
    }

    /**
     * @param  array<string, mixed>  $stay
     */
    public static function stayTotalPln(array $stay, ?string $eventPricingMode = 'lines', ?Collection $currenciesById = null): float
    {
        if ($eventPricingMode === 'flat_stay') {
            return 0.0;
        }

        if (($stay['pricing_mode'] ?? 'lines') === 'flat_night' && isset($stay['flat_amount']) && $stay['flat_amount'] !== '') {
            $amount = round((float) $stay['flat_amount'], 2);
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
            $total += self::lineTotalPln($line, $currenciesById);
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
    ): float {
        if ($eventPricingMode === 'flat_stay' && $flatStayAmount !== null) {
            $amount = round($flatStayAmount, 2);
            $symbol = $flatStayCurrencyId && $currenciesById?->has($flatStayCurrencyId)
                ? (string) $currenciesById->get($flatStayCurrencyId)
                : (Currency::query()->find($flatStayCurrencyId)?->symbol ?? 'PLN');

            if ($symbol === 'PLN' || ! $flatStayConvertToPln) {
                return $symbol === 'PLN' ? $amount : 0.0;
            }

            $rate = (float) (Currency::query()->find($flatStayCurrencyId)?->exchange_rate ?? 1);

            return round($amount * $rate, 2);
        }

        return round(collect($stays)->sum(fn (array $stay) => self::stayTotalPln($stay, $eventPricingMode, $currenciesById)), 2);
    }

    /**
     * @param  array<string, mixed>  $stay
     */
    public static function stayTotalDisplay(array $stay, ?string $eventPricingMode = 'lines', ?Collection $currenciesById = null): string
    {
        if ($eventPricingMode === 'flat_stay') {
            return '—';
        }

        if (($stay['pricing_mode'] ?? 'lines') === 'flat_night' && isset($stay['flat_amount']) && $stay['flat_amount'] !== '') {
            $currency = self::resolveCurrency($stay['flat_currency_id'] ?? null, $currenciesById);

            return CurrencyAmountDisplay::format(
                (float) $stay['flat_amount'],
                $currency,
                (bool) ($stay['flat_convert_to_pln'] ?? true),
                0,
            );
        }

        return self::formatMixedBreakdown(
            collect($stay['room_lines'] ?? [])->all(),
            $currenciesById,
            0,
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
    ): string {
        if ($eventPricingMode === 'flat_stay' && $flatStayAmount !== null) {
            $currency = self::resolveCurrency($flatStayCurrencyId, $currenciesById);

            return CurrencyAmountDisplay::format($flatStayAmount, $currency, $flatStayConvertToPln, 0);
        }

        $plnPart = 0.0;
        $foreignBuckets = [];

        foreach ($stays as $stay) {
            if (($stay['pricing_mode'] ?? 'lines') === 'flat_night' && isset($stay['flat_amount']) && $stay['flat_amount'] !== '') {
                $currency = self::resolveCurrency($stay['flat_currency_id'] ?? null, $currenciesById);
                $amount = (float) $stay['flat_amount'];
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
                $total = self::lineNativeTotal($line);
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
     * @param  array<int, array<string, mixed>>  $lines
     */
    protected static function formatMixedBreakdown(array $lines, ?Collection $currenciesById = null, int $decimals = 0): string
    {
        $plnPart = 0.0;
        $foreignBuckets = [];

        foreach ($lines as $line) {
            $total = self::lineNativeTotal($line);
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
}
