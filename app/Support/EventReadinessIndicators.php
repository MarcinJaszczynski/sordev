<?php

namespace App\Support;

use App\Models\Event;
use Illuminate\Support\Facades\Schema;

final class EventReadinessIndicators
{
    /**
     * @return array<int, array{key: string, label: string, short: string, tone: string, title: string}>
     */
    public static function forEvent(Event $event): array
    {
        // Kanoniczne 4 karty: odprawa, zaliczka pilota, ubezpieczenie, kierowca.
        return [
            self::checkInItem($event),
            self::pilotFundsItem($event),
            self::insuranceItem($event),
            self::driverItem($event),
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
        $status = Schema::hasColumn('events', 'check_in_status')
            ? (string) ($event->check_in_status ?: 'pending')
            : 'pending';

        return match ($status) {
            'completed' => [
                'key' => 'check_in',
                'label' => 'Odprawa',
                'short' => 'OK',
                'tone' => 'ok',
                'title' => 'Odprawa zakończona',
            ],
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
            $amount = filled($event->pilot_advance_planned_amount)
                ? number_format((float) $event->pilot_advance_planned_amount, 0, ',', ' ').' zł'
                : '';

            return [
                'key' => 'pilot_funds',
                'label' => 'Zaliczka pilota',
                'short' => 'OK',
                'tone' => 'ok',
                'title' => 'Zaliczka wypłacona'.($when !== '' ? ' ('.$when.')' : '').($amount !== '' ? ' · '.$amount : ''),
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

        return [
            'key' => 'pilot_funds',
            'label' => 'Zaliczka pilota',
            'short' => 'brak',
            'tone' => 'danger',
            'title' => 'Brak planu zaliczki pilota',
        ];
    }

    /**
     * @return array{key: string, label: string, short: string, tone: string, title: string}
     */
    protected static function insuranceItem(Event $event): array
    {
        // Gotowość: tylko zrobione / nie — bez „nie dotyczy” i bez stanu pośredniego.
        if (($event->insurance_status ?? 'pending') === 'completed') {
            return [
                'key' => 'insurance',
                'label' => 'Ubezp.',
                'short' => 'OK',
                'tone' => 'ok',
                'title' => 'Ubezpieczenie oznaczone jako gotowe',
            ];
        }

        return [
            'key' => 'insurance',
            'label' => 'Ubezp.',
            'short' => 'brak',
            'tone' => 'danger',
            'title' => 'Uzupełnij w Operacje → Ubezpieczenia i ustaw status „Gotowe”',
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
            blank($event->departure_time) ? 'godzina podstawienia' : null,
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

}

