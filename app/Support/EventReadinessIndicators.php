<?php

namespace App\Support;

use App\Models\Event;
use App\Services\EventHotelOccupancyService;
use Illuminate\Support\Facades\Schema;

final class EventReadinessIndicators
{
    /**
     * @return array<int, array{key: string, label: string, short: string, tone: string, title: string}>
     */
    public static function forEvent(Event $event): array
    {
        // Kanoniczne karty: odprawa, zaliczka pilota, ubezpieczenie, kierowca, autokar, hotel, miejsca hotel.
        return [
            self::checkInItem($event),
            self::pilotFundsItem($event),
            self::insuranceItem($event),
            self::driverItem($event),
            self::busCapacityItem($event),
            self::hotelItem($event),
            self::hotelBedsItem($event),
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, short: string, tone: string, title: string, icon: string, status_label: string, url: ?string}>
     */
    public static function forEventOverview(Event $event): array
    {
        return array_map(
            fn (array $item): array => [
                ...$item,
                'icon' => self::iconForKey($item['key']),
                'status_label' => self::statusLabel($item),
                'url' => self::urlForKey($event, $item['key']),
            ],
            self::forEvent($event),
        );
    }

    /**
     * Kompaktowe podsumowanie na listę imprez: score + blockers.
     *
     * @return array{
     *   total: int,
     *   done: int,
     *   open: int,
     *   tone: string,
     *   label: string,
     *   title: string,
     *   blockers: array<int, array{key: string, label: string, short: string, tone: string, title: string}>
     * }
     */
    public static function summaryForList(Event $event): array
    {
        $relevant = array_values(array_filter(
            self::forEvent($event),
            fn (array $item): bool => ($item['tone'] ?? '') !== 'muted' && ($item['short'] ?? '') !== '—',
        ));

        $total = count($relevant);
        $done = count(array_filter($relevant, fn (array $item): bool => ($item['short'] ?? '') === 'OK'));
        $open = max(0, $total - $done);

        $blockers = array_values(array_filter(
            $relevant,
            fn (array $item): bool => in_array($item['tone'] ?? '', ['danger', 'warn'], true),
        ));

        usort($blockers, function (array $a, array $b): int {
            $rank = ['danger' => 0, 'warn' => 1, 'ok' => 2, 'muted' => 3];

            return ($rank[$a['tone']] ?? 9) <=> ($rank[$b['tone']] ?? 9);
        });

        $blockers = array_slice($blockers, 0, 2);

        $tone = match (true) {
            $total === 0 => 'muted',
            $open === 0 => 'ok',
            collect($blockers)->contains(fn (array $i): bool => ($i['tone'] ?? '') === 'danger') => 'danger',
            default => 'warn',
        };

        $title = $blockers === []
            ? ($total > 0 ? 'Gotowość kompletna' : 'Brak aktywnych wskaźników')
            : implode(' · ', array_map(fn (array $i): string => $i['label'].': '.$i['title'], $blockers));

        return [
            'total' => $total,
            'done' => $done,
            'open' => $open,
            'tone' => $tone,
            'label' => $total > 0 ? "{$done}/{$total}" : '—',
            'title' => $title,
            'blockers' => $blockers,
        ];
    }

    public static function renderHtml(Event $event): string
    {
        return self::renderSummaryHtml($event);
    }

    public static function renderSummaryHtml(Event $event): string
    {
        $summary = self::summaryForList($event);
        $blockers = array_map(
            fn (array $item): string => sprintf(
                '<span class="event-indicator event-indicator--%s" title="%s">%s</span>',
                e($item['tone']),
                e($item['title']),
                e($item['label']),
            ),
            $summary['blockers'],
        );

        return sprintf(
            '<div class="event-readiness-summary event-readiness-summary--%s" title="%s">'.
                '<span class="event-readiness-summary__score">%s</span>'.
                '%s'.
            '</div>',
            e($summary['tone']),
            e($summary['title']),
            e($summary['label']),
            $blockers !== [] ? '<span class="event-readiness-summary__blockers">'.implode('', $blockers).'</span>' : '',
        );
    }

    protected static function urlForKey(Event $event, string $key): ?string
    {
        if (! $event->getKey()) {
            return null;
        }

        try {
            return match ($key) {
                'check_in' => \App\Filament\Resources\EventResource::getUrl('edit', ['record' => $event]),
                'pilot_funds' => \App\Filament\Resources\EventResource::getUrl('pilot', ['record' => $event]),
                // Polisa / status „Gotowe”: Operacje → Ubezpieczenia.
                'insurance' => \App\Filament\Resources\EventResource::getUrl('day-insurances', ['record' => $event]),
                'driver' => \App\Filament\Resources\EventResource::getUrl('transport', ['record' => $event]),
                'bus_capacity' => \App\Filament\Resources\EventResource::getUrl('transport', ['record' => $event]),
                'hotel' => \App\Filament\Resources\EventResource::getUrl('hotel-planning', ['record' => $event]),
                'hotel_beds' => \App\Filament\Resources\EventResource::getUrl('hotel-planning', ['record' => $event]),
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }

    protected static function iconForKey(string $key): string
    {
        return match ($key) {
            'check_in' => 'heroicon-o-clipboard-document-check',
            'pilot_funds' => 'heroicon-o-banknotes',
            'insurance' => 'heroicon-o-shield-check',
            'driver' => 'heroicon-o-truck',
            'bus_capacity' => 'heroicon-o-users',
            'hotel' => 'heroicon-o-building-office-2',
            'hotel_beds' => 'heroicon-o-home-modern',
            default => 'heroicon-o-flag',
        };
    }

    /**
     * @param  array{short: string, tone: string}  $item
     */
    protected static function statusLabel(array $item): string
    {
        return match ($item['short']) {
            'OK' => 'Gotowe',
            'w toku' => 'W toku',
            'plan' => 'Zaplanowano',
            'do potw.' => 'Do potwierdzenia',
            'brak' => 'Do uzupełnienia',
            '—' => 'Nie dotyczy',
            default => (string) $item['short'],
        };
    }

    /**
     * @return array{key: string, label: string, short: string, tone: string, title: string}
     */
    protected static function checkInItem(Event $event): array
    {
        if ($event->isCheckInCompleted()) {
            return [
                'key' => 'check_in',
                'label' => 'Odprawa',
                'short' => 'OK',
                'tone' => 'ok',
                'title' => 'Odprawa zakończona',
            ];
        }

        $status = Schema::hasColumn('events', 'check_in_status')
            ? (string) ($event->check_in_status ?: 'pending')
            : 'pending';

        return match ($status) {
            'in_progress' => [
                'key' => 'check_in',
                'label' => 'Odprawa',
                'short' => 'w toku',
                'tone' => 'warn',
                'title' => 'Odprawa w trakcie',
            ],
            default => [
                'key' => 'check_in',
                'label' => 'Odprawa',
                'short' => 'brak',
                'tone' => 'danger',
                'title' => 'Odprawa do zrobienia',
            ],
        };
    }

    /**
     * @return array{key: string, label: string, short: string, tone: string, title: string}
     */
    protected static function pilotFundsItem(Event $event): array
    {
        if (! Schema::hasColumn('events', 'pilot_funds_paid') || ! $event->assigned_to) {
            return [
                'key' => 'pilot_funds',
                'label' => 'Zaliczka pilota',
                'short' => '—',
                'tone' => 'muted',
                'title' => 'Brak przypisanego pilota',
            ];
        }

        if ($event->pilot_funds_paid) {
            $when = $event->pilot_funds_paid_at?->format('d.m.Y') ?? '';
            $amount = app(\App\Services\PilotAdvanceService::class)->formatOfficePayoutLabel($event);
            $amountSuffix = ($amount !== '' && $amount !== '—') ? ' · '.$amount : '';

            return [
                'key' => 'pilot_funds',
                'label' => 'Zaliczka pilota',
                'short' => 'OK',
                'tone' => 'ok',
                'title' => 'Zaliczka wypłacona'.($when !== '' ? ' ('.$when.')' : '').$amountSuffix,
            ];
        }

        if (Schema::hasColumn('events', 'pilot_advance_planned_amount') && filled($event->pilot_advance_planned_amount)) {
            $amount = number_format((float) $event->pilot_advance_planned_amount, 0, ',', ' ').' zł';

            return [
                'key' => 'pilot_funds',
                'label' => 'Zaliczka pilota',
                'short' => 'plan',
                'tone' => 'warn',
                'title' => 'Zaplanowano zaliczkę: '.$amount.' — czeka na wypłatę',
            ];
        }

        $busFunding = self::pilotBusCollectionFundingSummary($event);
        if ($busFunding !== null) {
            return $busFunding;
        }

        return [
            'key' => 'pilot_funds',
            'label' => 'Zaliczka pilota',
            'short' => 'brak',
            'tone' => 'danger',
            'title' => 'Brak planu zaliczki pilota',
        ];
    }

    /**
     * Gdy biuro nie wypłaca gotówki — wystarczy plan/zbiórka w autokarze.
     *
     * @return array{key: string, label: string, short: string, tone: string, title: string}|null
     */
    protected static function pilotBusCollectionFundingSummary(Event $event): ?array
    {
        if (! Schema::hasTable('event_bus_collections')) {
            return null;
        }

        $rows = \App\Models\EventBusCollection::query()
            ->where('event_id', $event->id)
            ->get(['status', 'amount']);

        if ($rows->isEmpty()) {
            return null;
        }

        $held = round((float) $rows->whereIn('status', \App\Models\EventBusCollection::HELD_STATUSES)->sum('amount'), 2);
        $planned = round((float) $rows->where('status', \App\Models\EventBusCollection::STATUS_PLANNED)->sum('amount'), 2);
        $handed = round((float) $rows->whereIn('status', [
            \App\Models\EventBusCollection::STATUS_HANDED_TO_OFFICE,
            \App\Models\EventBusCollection::STATUS_CONFIRMED,
        ])->sum('amount'), 2);

        if ($held > 0.009) {
            return [
                'key' => 'pilot_funds',
                'label' => 'Zaliczka pilota',
                'short' => 'zbiórka',
                'tone' => 'ok',
                'title' => 'Gotówka z autokaru u pilota: '.number_format($held, 0, ',', ' '),
            ];
        }

        if ($planned > 0.009) {
            return [
                'key' => 'pilot_funds',
                'label' => 'Zaliczka pilota',
                'short' => 'plan zb.',
                'tone' => 'warn',
                'title' => 'Zaplanowano zbiórkę w autokarze: '.number_format($planned, 0, ',', ' ').' — bez wypłaty z biura',
            ];
        }

        if ($handed > 0.009) {
            return [
                'key' => 'pilot_funds',
                'label' => 'Zaliczka pilota',
                'short' => 'OK',
                'tone' => 'ok',
                'title' => 'Zbiórka w autokarze rozliczona z biurem',
            ];
        }

        return null;
    }

    /**
     * @return array{key: string, label: string, short: string, tone: string, title: string}
     */
    protected static function insuranceItem(Event $event): array
    {
        // Gotowość: opłacone w kosztach = gotowe.
        if (app(\App\Services\EventInsuranceOperationalSync::class)->isEventInsurancePaid($event)) {
            return [
                'key' => 'insurance',
                'label' => 'Ubezp.',
                'short' => 'OK',
                'tone' => 'ok',
                'title' => 'Ubezpieczenie opłacone w kosztach',
            ];
        }

        return [
            'key' => 'insurance',
            'label' => 'Ubezp.',
            'short' => 'brak',
            'tone' => 'danger',
            'title' => 'Uzupełnij polisę i opłać pozycje ubezpieczenia w kosztach',
        ];
    }

    /**
     * @return array{key: string, label: string, short: string, tone: string, title: string}
     */
    protected static function driverItem(Event $event): array
    {
        if (! $event->requiresDriverPickupInfo()) {
            return [
                'key' => 'driver',
                'label' => 'Kierowca',
                'short' => '—',
                'tone' => 'muted',
                'title' => 'Brak transportu autokarowego',
            ];
        }

        if ($event->isDriverPickupInfoSent()) {
            $when = $event->driver_pickup_info_sent_at?->format('d.m.Y H:i') ?? '';

            return [
                'key' => 'driver',
                'label' => 'Kierowca',
                'short' => 'OK',
                'tone' => 'ok',
                'title' => 'Wysłano info o podstawieniu'.($when !== '' ? ' ('.$when.')' : ''),
            ];
        }

        $missing = array_filter([
            blank($event->driver_name) ? 'imię kierowcy' : null,
            (Schema::hasColumn('events', 'substitution_time')
                ? blank($event->substitution_time)
                : blank($event->departure_time)) ? 'godzina podstawienia' : null,
            blank($event->pickup_place_details) && blank($event->startPlace?->name) ? 'miejsce podstawienia' : null,
        ]);

        return [
            'key' => 'driver',
            'label' => 'Kierowca',
            'short' => 'brak',
            'tone' => 'danger',
            'title' => $missing !== []
                ? 'Do uzupełnienia: '.implode(', ', $missing)
                : 'Nie wysłano informacji o podstawieniu kierowcy',
        ];
    }

    /**
     * @return array{key: string, label: string, short: string, tone: string, title: string}
     */
    protected static function hotelItem(Event $event): array
    {
        if (! Schema::hasTable('event_hotel_stays')) {
            return [
                'key' => 'hotel',
                'label' => 'Hotel',
                'short' => '—',
                'tone' => 'muted',
                'title' => 'Brak planu noclegów',
            ];
        }

        $groups = app(\App\Services\HotelStayReservationSync::class)->hotelGroups($event);

        if ($groups === []) {
            $hasStays = $event->relationLoaded('hotelStays')
                ? $event->hotelStays->isNotEmpty()
                : $event->hotelStays()->exists();

            return [
                'key' => 'hotel',
                'label' => 'Hotel',
                'short' => $hasStays ? 'brak' : '—',
                'tone' => $hasStays ? 'danger' : 'muted',
                'title' => $hasStays
                    ? 'Wybierz hotel w Operacje → Hotel'
                    : 'Brak nocy hotelowych',
            ];
        }

        $total = count($groups);
        $confirmed = count(array_filter($groups, fn (array $g): bool => (bool) ($g['is_confirmed'] ?? false)));

        if ($confirmed === $total) {
            $names = implode(', ', array_map(fn (array $g): string => $g['contractor_name'], $groups));

            return [
                'key' => 'hotel',
                'label' => 'Hotel',
                'short' => 'OK',
                'tone' => 'ok',
                'title' => $total === 1
                    ? 'Rezerwacja hotelu potwierdzona ('.$names.')'
                    : "Wszystkie hotele potwierdzone ({$confirmed}/{$total})",
            ];
        }

        $pending = array_values(array_filter(
            $groups,
            fn (array $g): bool => ! ($g['is_confirmed'] ?? false),
        ));
        $pendingLabels = array_map(function (array $g): string {
            $days = $g['days'] !== [] ? ' D'.implode('/', $g['days']) : '';

            return $g['contractor_name'].$days;
        }, $pending);

        return [
            'key' => 'hotel',
            'label' => 'Hotel',
            'short' => $confirmed > 0 ? 'w toku' : 'do potw.',
            'tone' => $confirmed > 0 ? 'warn' : 'danger',
            'title' => 'Do potwierdzenia: '.implode(' · ', $pendingLabels),
        ];
    }

    /**
     * @return array{key: string, label: string, short: string, tone: string, title: string}
     */
    protected static function busCapacityItem(Event $event): array
    {
        $status = self::resolveBusCapacityStatus($event);

        if ($status === null) {
            return [
                'key' => 'bus_capacity',
                'label' => 'Autokar',
                'short' => '—',
                'tone' => 'muted',
                'title' => 'Nie wybrano pojazdu floty',
            ];
        }

        $total = (int) $status['total'];
        $capacity = (int) $status['capacity'];
        $ratio = "{$total}/{$capacity}";

        if ($status['exceeds']) {
            return [
                'key' => 'bus_capacity',
                'label' => 'Autokar',
                'short' => $ratio,
                'tone' => 'danger',
                'title' => (string) ($status['message'] ?? 'Grupa przekracza pojemność autokaru'),
            ];
        }

        return [
            'key' => 'bus_capacity',
            'label' => 'Autokar',
            'short' => 'OK',
            'tone' => 'ok',
            'title' => "Miejsca w autokarze wystarczają ({$ratio})",
        ];
    }

    /**
     * @return array{key: string, label: string, short: string, tone: string, title: string}
     */
    protected static function hotelBedsItem(Event $event): array
    {
        if (! Schema::hasTable('event_hotel_stays')) {
            return [
                'key' => 'hotel_beds',
                'label' => 'Miejsca hotel',
                'short' => '—',
                'tone' => 'muted',
                'title' => 'Brak planu noclegów',
            ];
        }

        $analysis = EventHotelBedCapacity::analyzeOccupancy(
            app(EventHotelOccupancyService::class)->forEvent($event)
        );

        $required = (int) ($analysis['required'] ?? 0);
        $minBeds = $analysis['min_beds'] ?? null;

        if ($required <= 0) {
            return [
                'key' => 'hotel_beds',
                'label' => 'Miejsca hotel',
                'short' => '—',
                'tone' => 'muted',
                'title' => 'Brak zaplanowanej liczby uczestników',
            ];
        }

        if ($minBeds === null) {
            return [
                'key' => 'hotel_beds',
                'label' => 'Miejsca hotel',
                'short' => '—',
                'tone' => 'muted',
                'title' => 'Brak zaplanowanych pokoi — uzupełnij plan noclegów',
            ];
        }

        $ratio = "{$minBeds}/{$required}";

        if ($analysis['has_deficiency']) {
            $title = collect($analysis['deficient_stays'] ?? [])
                ->pluck('message')
                ->filter()
                ->implode(' · ');

            return [
                'key' => 'hotel_beds',
                'label' => 'Miejsca hotel',
                'short' => $ratio,
                'tone' => 'danger',
                'title' => $title !== '' ? $title : "Za mało miejsc w hotelu ({$ratio})",
            ];
        }

        return [
            'key' => 'hotel_beds',
            'label' => 'Miejsca hotel',
            'short' => 'OK',
            'tone' => 'ok',
            'title' => "Miejsca w hotelu wystarczają ({$ratio})",
        ];
    }

    /**
     * Gotowość operacyjna: tylko pojazd floty (Vehicle.capacity).
     * Autokar z cennika (Bus) służy do wyceny — nie alarmujemy o miejscach.
     *
     * @return array{exceeds: bool, total: int, capacity: int, message: ?string}|null
     */
    protected static function resolveBusCapacityStatus(Event $event): ?array
    {
        if (! Schema::hasTable('event_vehicles')) {
            return null;
        }

        $paying = max(0, (int) $event->participant_count);
        $gratis = max(0, (int) $event->resolveGratisCountForParticipantCount($paying > 0 ? $paying : null));
        $total = $paying + $gratis;

        $event->loadMissing(['eventVehicles.vehicle']);
        $vehicle = $event->mainEventVehicle()?->vehicle;
        if (! $vehicle) {
            return null;
        }

        $capacity = (int) ($vehicle->capacity ?? 0);
        if ($capacity <= 0) {
            return null;
        }

        $name = filled($vehicle->registration_number)
            ? (string) $vehicle->registration_number
            : $vehicle->displayLabel();

        if (EventBusSeatCapacity::exceeds($paying, $gratis, $capacity)) {
            return [
                'exceeds' => true,
                'total' => $total,
                'capacity' => $capacity,
                'message' => EventBusSeatCapacity::message($paying, $gratis, $capacity, $name),
            ];
        }

        return [
            'exceeds' => false,
            'total' => $total,
            'capacity' => $capacity,
            'message' => null,
        ];
    }
}
