<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventSettlementCost;
use App\Services\PilotSettlementService;
use App\Services\SettlementCostContractorResolver;
use App\Support\ContractorContactDetails;
use App\Support\CurrencyAmountDisplay;
use Illuminate\Support\Collection;

/**
 * Dane operacyjne do pakietu PDF pilota: wydatki (tylko paid_by=pilot)
 * oraz zbiorcza lista adresów/kontaktów (trasa, hotele, kontrahenci programu).
 */
final class PilotPackageOperationalDataBuilder
{
    public function __construct(
        private readonly PilotSettlementService $pilotSettlement,
        private readonly SettlementCostContractorResolver $contractorResolver,
    ) {}

    /**
     * @return list<array{
     *     day: ?int,
     *     name: string,
     *     payee: string,
     *     address: ?string,
     *     planned_amount_label: string,
     *     point_id: ?int
     * }>
     */
    public function expenseRows(Event $event): array
    {
        $settlement = $event->relationLoaded('activeSettlement')
            ? $event->activeSettlement
            : $event->activeSettlement()->first();

        if (! $settlement) {
            return [];
        }

        $lines = $this->pilotSettlement->getExpenseLines($settlement);

        if ($lines->isEmpty()) {
            return [];
        }

        $pointIds = $lines
            ->filter(fn (EventSettlementCost $cost): bool => in_array((string) $cost->source_type, ['program_point', 'manual'], true)
                && filled($cost->source_id))
            ->pluck('source_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $pointsById = $pointIds->isEmpty()
            ? collect()
            : EventProgramPoint::query()
                ->whereIn('id', $pointIds)
                ->with(['contractor', 'contractorLocation', 'templatePoint'])
                ->get()
                ->keyBy('id');

        $rows = [];

        foreach ($lines as $cost) {
            $point = null;
            if (in_array((string) $cost->source_type, ['program_point', 'manual'], true) && $cost->source_id) {
                $point = $pointsById->get((int) $cost->source_id);
            }

            $contractorPack = $this->contractorResolver->resolve($cost, $event, $point);
            $payee = trim((string) ($contractorPack['contractor'] ?? ''));
            $address = $this->resolvePayeeAddress($point, $contractorPack);

            if ($payee === '' && $point?->contractor) {
                $payee = (string) ($point->contractor->name ?? '');
            }

            $convertToPln = (bool) ($cost->planned_convert_to_pln ?? true);
            $currency = $cost->plannedCurrency ?? $cost->actualCurrency;
            $planned = round((float) ($cost->planned_amount ?? $cost->ledger_pilot_due ?? 0), 2);
            $plannedLabel = $planned > 0.009
                ? CurrencyAmountDisplay::format($planned, $currency, $convertToPln)
                : '—';

            $name = trim((string) ($cost->name ?: ''));
            if ($name === '' && $point) {
                $name = (string) ($point->templatePoint?->name ?? $point->name ?? '');
            }
            if ($name === '') {
                $name = 'Wydatek #'.$cost->id;
            }

            $rows[] = [
                'day' => $point ? ((int) ($point->day ?? 0) ?: null) : null,
                'name' => $name,
                'payee' => $payee !== '' ? $payee : '—',
                'address' => $address,
                'planned_amount_label' => $plannedLabel,
                'point_id' => $point ? (int) $point->id : null,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $dayCmp = ($a['day'] ?? 999) <=> ($b['day'] ?? 999);
            if ($dayCmp !== 0) {
                return $dayCmp;
            }

            return strcmp($a['name'], $b['name']);
        });

        return $rows;
    }

    /**
     * Kwoty „do zapłaty” per punkt programu (tylko zobowiązania pilota) — do kolumny w programie.
     *
     * @return array<int, array{amount_label: string, payee: ?string}>
     */
    public function pilotDueByPointId(Event $event): array
    {
        $map = [];

        foreach ($this->expenseRows($event) as $row) {
            $pointId = $row['point_id'] ?? null;
            if (! $pointId || ($row['planned_amount_label'] ?? '—') === '—') {
                continue;
            }

            $map[(int) $pointId] = [
                'amount_label' => (string) $row['planned_amount_label'],
                'payee' => ($row['payee'] ?? null) !== '—' ? (string) $row['payee'] : null,
            ];
        }

        return $map;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $hotelPlan
     * @param  Collection<int, Collection<int, EventProgramPoint>>  $programByDay
     * @param  array<string, string>  $travelLegends
     * @param  array<string, string>  $programDayRoutes
     * @return list<array{
     *     role: string,
     *     name: string,
     *     address: ?string,
     *     phone: ?string,
     *     email: ?string,
     *     note: ?string
     * }>
     */
    public function contactPlaces(
        Event $event,
        Collection $hotelPlan,
        Collection $programByDay,
        array $travelLegends,
        array $programDayRoutes,
    ): array {
        $places = [];
        $seen = [];

        $pickup = trim(strip_tags((string) (
            $event->adress_transport_start
            ?: $event->pickup_place_details
            ?: $event->startPlace?->name
            ?: ''
        )));
        if ($pickup !== '') {
            $places[] = $this->pushUnique($seen, [
                'role' => 'Podstawienie / start',
                'name' => $pickup,
                'address' => null,
                'phone' => null,
                'email' => null,
                'note' => $travelLegends['departure'] ?? null,
            ]);
        }

        $destination = trim((string) ($travelLegends['destination'] ?? ''));
        if ($destination !== '' && $destination !== '—') {
            $places[] = $this->pushUnique($seen, [
                'role' => 'Cel przejazdu',
                'name' => $destination,
                'address' => null,
                'phone' => null,
                'email' => null,
                'note' => null,
            ]);
        }

        $returnPlace = trim(strip_tags((string) (
            $event->adress_transport_end
            ?: ($travelLegends['return_place'] ?? '')
            ?: ''
        )));
        if ($returnPlace !== '' && $returnPlace !== '—') {
            $places[] = $this->pushUnique($seen, [
                'role' => 'Miejsce powrotu',
                'name' => $returnPlace,
                'address' => null,
                'phone' => null,
                'email' => null,
                'note' => $travelLegends['return'] ?? null,
            ]);
        }

        foreach ($hotelPlan as $day) {
            $hotelName = trim((string) ($day['hotel_name'] ?? ''));
            $branch = trim((string) ($day['hotel_branch'] ?? ''));
            $label = $hotelName !== '' ? $hotelName : $branch;
            if ($label === '') {
                continue;
            }

            $places[] = $this->pushUnique($seen, [
                'role' => 'Hotel (dzień '.(int) ($day['day'] ?? 1).')',
                'name' => $branch !== '' && $hotelName !== '' ? $hotelName.' — '.$branch : $label,
                'address' => filled($day['hotel_address'] ?? null) ? (string) $day['hotel_address'] : null,
                'phone' => filled($day['hotel_phone'] ?? null) ? (string) $day['hotel_phone'] : null,
                'email' => filled($day['hotel_email'] ?? null) ? (string) $day['hotel_email'] : null,
                'note' => null,
            ]);
        }

        foreach ($programByDay as $day => $points) {
            foreach ($points as $point) {
                if (! $point->contractor) {
                    continue;
                }

                $meta = ContractorContactDetails::operationalMeta($point->contractor, $point->contractorLocation);
                $name = trim((string) ($point->contractor->name ?? ''));
                if ($name === '') {
                    continue;
                }

                $pointLabel = trim((string) ($point->name ?: $point->templatePoint?->name ?: ''));
                $role = 'Program · dzień '.(int) $day;
                if ($pointLabel !== '') {
                    $role .= ' · '.$pointLabel;
                }

                $places[] = $this->pushUnique($seen, [
                    'role' => $role,
                    'name' => $name.(! empty($meta['branch_name']) ? ' — '.$meta['branch_name'] : ''),
                    'address' => $meta['address'] ?? null,
                    'phone' => $meta['phone'] ?? null,
                    'email' => $meta['email'] ?? null,
                    'note' => null,
                ], keyExtra: (string) ($meta['address'] ?? ''));
            }
        }

        foreach ($programDayRoutes as $routeDay => $routeLabel) {
            $route = trim((string) $routeLabel);
            if ($route === '') {
                continue;
            }

            $places[] = $this->pushUnique($seen, [
                'role' => 'Trasa · dzień '.$routeDay,
                'name' => $route,
                'address' => null,
                'phone' => null,
                'email' => null,
                'note' => null,
            ], keyExtra: 'route-'.$routeDay);
        }

        return array_values(array_filter($places));
    }

    /**
     * @param  array<string, true>  $seen
     * @param  array{
     *     role: string,
     *     name: string,
     *     address: ?string,
     *     phone: ?string,
     *     email: ?string,
     *     note: ?string
     * }  $place
     * @return array{
     *     role: string,
     *     name: string,
     *     address: ?string,
     *     phone: ?string,
     *     email: ?string,
     *     note: ?string
     * }|null
     */
    private function pushUnique(array &$seen, array $place, string $keyExtra = ''): ?array
    {
        $key = mb_strtolower(trim($place['name'].'|'.($place['address'] ?? '').'|'.$keyExtra));
        if ($key === '|' || isset($seen[$key])) {
            return null;
        }

        $seen[$key] = true;

        return $place;
    }

    /**
     * @param  array<string, mixed>  $contractorPack
     */
    private function resolvePayeeAddress(?EventProgramPoint $point, array $contractorPack): ?string
    {
        if ($point?->contractor) {
            $meta = ContractorContactDetails::operationalMeta($point->contractor, $point->contractorLocation);
            $parts = array_filter([
                $meta['branch_name'] ?? null,
                $meta['address'] ?? null,
                isset($meta['phone']) && $meta['phone'] ? 'tel. '.$meta['phone'] : null,
                $meta['email'] ?? null,
            ]);

            return $parts !== [] ? implode(' · ', $parts) : null;
        }

        return $this->formatContractorPlaceDetails($point, $contractorPack);
    }

    /**
     * @param  array<string, mixed>  $contractorPack
     */
    private function formatContractorPlaceDetails(?EventProgramPoint $point, array $contractorPack): ?string
    {
        if ($point?->contractor) {
            $meta = ContractorContactDetails::operationalMeta($point->contractor, $point->contractorLocation);

            return $this->joinContactBits([
                $meta['branch_name'] ?? null,
                $meta['address'] ?? null,
                $meta['phone'] ?? null,
                $meta['email'] ?? null,
            ]);
        }

        $details = collect($contractorPack['contractor_details'] ?? [])
            ->map(fn ($line) => trim((string) $line))
            ->filter()
            ->values();

        if ($details->isEmpty()) {
            return $this->joinContactBits([
                $contractorPack['contractor_phone'] ?? null,
                $contractorPack['contractor_email'] ?? null,
            ]);
        }

        // Pierwszy element to zwykle nazwa — pomijamy, bo jest w „payee”.
        $withoutName = $details->slice(1)->values();

        return $withoutName->isNotEmpty()
            ? $withoutName->implode(' · ')
            : $this->joinContactBits([
                $contractorPack['contractor_phone'] ?? null,
                $contractorPack['contractor_email'] ?? null,
            ]);
    }

    /**
     * @param  list<?string>  $parts
     */
    private function joinContactBits(array $parts): ?string
    {
        $joined = collect($parts)
            ->map(fn ($part) => is_string($part) ? trim($part) : '')
            ->filter()
            ->implode(' · ');

        return $joined !== '' ? $joined : null;
    }
}
