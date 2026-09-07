<?php

namespace App\Services;

use App\Models\Bus;
use App\Models\Currency;
use App\Models\Event;
use Illuminate\Support\Facades\Schema;

final class EventTransportCostCalculator
{
    public const TRANSPORT_POINT_NAME = 'Koszt transportu (autokar)';

    public const MANUAL_TRANSPORT_POINT_NAME = 'Koszt transportu (ryczałt)';

    public function __construct(
        private readonly Event $event,
    ) {}

    /**
     * Km do rozliczenia autokaru.
     *
     * Na imprezie `transfer_km` = dojazd + powrót (d1+d2), zapisane tak przy tworzeniu
     * z szablonu / wyborze miejsca startu — NIE jest to jeden kierunek.
     * Formuła jak w EventTemplateCalculationEngine: 1.1 × (transfer + program) + 50.
     */
    public function resolveTransportKm(): float
    {
        $transferKm = (float) ($this->event->transfer_km ?? 0);
        $programKm = (float) ($this->event->program_km ?? 0);

        return round((1.1 * ($transferKm + $programKm)) + 50, 2);
    }

    public function usesManualTransportCost(): bool
    {
        return Schema::hasColumn('events', 'use_manual_transport_cost')
            && (bool) $this->event->use_manual_transport_cost;
    }

    /**
     * Koszt z cennika autokaru (km / pakiet) — niezależnie od ryczałtu ręcznego.
     * Używane m.in. w kolumnie „Kalkulacja” w Finansach.
     *
     * @param  array{qty:int,gratis?:int,staff?:int,driver?:int}|object|null  $variant
     */
    public function busCalculatedTransportCost(array|object|null $variant = null): float
    {
        if (! $this->resolveBus()) {
            return 0.0;
        }

        $variant ??= [
            'qty' => max(1, (int) ($this->event->participant_count ?? 1)),
            'gratis' => 0,
            'staff' => 1,
            'driver' => 1,
        ];

        return $this->costForVariant($variant);
    }

    /**
     * Efektywny koszt do oferty / planu: ryczałt ręczny albo kalkulacja z autokaru.
     *
     * @param  array{qty:int,gratis?:int,staff?:int,driver?:int}|object|null  $variant
     */
    public function effectiveTransportCost(array|object|null $variant = null): float
    {
        if ($this->usesManualTransportCost()) {
            return round((float) ($this->event->manual_transport_cost ?? 0), 2);
        }

        return $this->busCalculatedTransportCost($variant);
    }

    /**
     * @param  array{qty:int,gratis?:int,staff?:int,driver?:int}|object  $variant
     */
    public function costForVariant(array|object $variant, ?Bus $bus = null): float
    {
        $bus = $bus ?? $this->resolveBus();
        if (! $bus) {
            return 0.0;
        }

        $variant = $this->normalizeVariant($variant);
        $totalKm = $this->resolveTransportKm();
        $duration = max(1, (int) ($this->event->duration_days ?? 1));
        $includedKm = $duration * (float) ($bus->package_km_per_day ?? 0);
        $baseCost = $duration * (float) ($bus->package_price_per_day ?? 0);

        if ($totalKm <= $includedKm) {
            $busCost = $baseCost;
        } else {
            $extraKm = $totalKm - $includedKm;
            $busCost = $baseCost + ($extraKm * (float) ($bus->extra_km_price ?? 0));
        }

        $qtyTotal = (int) ($variant['qty'] ?? 0)
            + (int) ($variant['gratis'] ?? 0)
            + (int) ($variant['staff'] ?? 0)
            + (int) ($variant['driver'] ?? 0);

        $busMultiplier = 1;
        if ((int) ($bus->capacity ?? 0) > 0 && $qtyTotal > (int) $bus->capacity) {
            $busMultiplier = (int) ceil($qtyTotal / (int) $bus->capacity);
        }

        $busCost *= $busMultiplier;

        if ($bus->currency && $bus->currency !== 'PLN') {
            $currency = Currency::where('symbol', $bus->currency)->first();
            $exchangeRate = (float) ($currency?->exchange_rate ?? 1);
            $busCost *= $exchangeRate;
        }

        return round($busCost, 2);
    }

    /**
     * Autokar z event.bus_id — odporny na stale null oraz partial select (bus:id,name).
     */
    public function resolveBus(): ?Bus
    {
        $busId = (int) ($this->event->bus_id ?? 0);
        if ($busId <= 0) {
            return null;
        }

        if ($this->event->relationLoaded('bus')) {
            $loaded = $this->event->getRelation('bus');
            if (
                $loaded instanceof Bus
                && (int) $loaded->getKey() === $busId
                && $loaded->hasTransportPricingAttributesLoaded()
            ) {
                return $loaded;
            }

            // null, inny model albo okrojony select — przeładuj pełny rekord
            $this->event->unsetRelation('bus');
        }

        $bus = $this->event->bus();
        $resolved = $bus->getResults();
        $this->event->setRelation('bus', $resolved);

        return $resolved instanceof Bus ? $resolved : null;
    }

    /**
     * @return array{name:string,unit_price:null,group_size:null,cost:float,is_child:bool,currency_symbol:string}
     */
    public function transportPointLine(float $cost, bool $manual = false): array
    {
        return [
            'name' => $manual ? self::MANUAL_TRANSPORT_POINT_NAME : self::TRANSPORT_POINT_NAME,
            'unit_price' => null,
            'group_size' => null,
            'cost' => $cost,
            'is_child' => false,
            'currency_symbol' => 'PLN',
        ];
    }

    public static function isTransportPointName(string $name): bool
    {
        return str_contains(mb_strtolower($name), 'koszt transportu');
    }

    /**
     * @param  array<string, mixed>  $detailedCalculations
     * @param  callable(int|string, float): void  $applyPlnDelta
     */
    public function syncTransportInDetailedCalculations(
        array &$detailedCalculations,
        array $qtyVariants,
        ?array $currentVariant,
        callable $applyPlnDelta,
    ): void {
        if ((! $this->resolveBus() && ! $this->usesManualTransportCost()) || empty($detailedCalculations)) {
            return;
        }

        $manual = $this->usesManualTransportCost();

        foreach (array_keys($detailedCalculations) as $qty) {
            $variant = $qtyVariants[$qty] ?? $currentVariant ?? [
                'qty' => (int) $qty,
                'gratis' => 0,
                'staff' => 1,
                'driver' => 1,
            ];

            $newCost = $manual
                ? $this->effectiveTransportCost()
                : $this->costForVariant($variant);

            $this->replaceTransportLine($detailedCalculations, $qty, $newCost, $applyPlnDelta, $manual);
        }
    }

    /**
     * @param  array<string, mixed>  $detailedCalculations
     * @param  callable(int|string, float): void  $applyPlnDelta
     */
    private function replaceTransportLine(
        array &$detailedCalculations,
        int|string $qty,
        float $newCost,
        callable $applyPlnDelta,
        bool $manual = false,
    ): void {
        $pln = $detailedCalculations[$qty]['PLN'] ?? null;
        if (! is_array($pln)) {
            return;
        }

        $points = collect($pln['points'] ?? []);
        $oldCost = (float) $points
            ->filter(fn (array $point): bool => self::isTransportPointName((string) ($point['name'] ?? '')))
            ->sum(fn (array $point): float => (float) ($point['cost'] ?? 0));

        $filtered = $points
            ->reject(fn (array $point): bool => self::isTransportPointName((string) ($point['name'] ?? '')))
            ->values();

        if ($newCost > 0) {
            $filtered->push($this->transportPointLine($newCost, $manual));
        }

        $detailedCalculations[$qty]['PLN']['points'] = $filtered->all();

        $delta = round($newCost - $oldCost, 2);
        if (abs($delta) > 0.001) {
            $applyPlnDelta($qty, $delta);
        }
    }

    /**
     * @return array{qty:int,gratis:int,staff:int,driver:int}
     */
    private function normalizeVariant(array|object $variant): array
    {
        if (is_object($variant)) {
            return [
                'qty' => (int) ($variant->qty ?? 0),
                'gratis' => (int) ($variant->gratis ?? 0),
                'staff' => (int) ($variant->staff ?? 0),
                'driver' => (int) ($variant->driver ?? 0),
            ];
        }

        return [
            'qty' => (int) ($variant['qty'] ?? 0),
            'gratis' => (int) ($variant['gratis'] ?? 0),
            'staff' => (int) ($variant['staff'] ?? 0),
            'driver' => (int) ($variant['driver'] ?? 0),
        ];
    }
}
