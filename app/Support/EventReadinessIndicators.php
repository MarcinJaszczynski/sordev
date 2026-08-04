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
        $items = [
            self::checkInItem($event),
            self::pilotFundsItem($event),
            self::insuranceItem($event),
            self::driverItem($event),
        ];

        return $items;
    }

    /**
     * @return array<int, array{key: string, label: string, short: string, tone: string, title: string, icon: string, status_label: string}>
     */
    public static function forEventOverview(Event $event): array
    {
        return array_map(
            fn (array $item): array => [
                ...$item,
                'icon' => self::iconForKey($item['key']),
                'status_label' => self::statusLabel($item),
            ],
            self::forEvent($event),
        );
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

    public static function renderHtml(Event $event): string
    {
        $pills = array_map(
            fn (array $item): string => sprintf(
                '<span class="event-indicator event-indicator--%s" title="%s">%s: %s</span>',
                e($item['tone']),
                e($item['title']),
                e($item['label']),
                e($item['short']),
            ),
            self::forEvent($event),
        );

        return '<div class="event-indicators event-indicators--clickable">'.implode('', $pills).'</div>';
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
                'label' => 'Zaliczka',
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
                'label' => 'Zaliczka',
                'short' => 'OK',
                'tone' => 'ok',
                'title' => 'Zaliczka wypłacona'.($when !== '' ? ' ('.$when.')' : '').($amount !== '' ? ' · '.$amount : ''),
            ];
        }

        if (Schema::hasColumn('events', 'pilot_advance_planned_amount') && filled($event->pilot_advance_planned_amount)) {
            $amount = number_format((float) $event->pilot_advance_planned_amount, 0, ',', ' ').' zł';

            return [
                'key' => 'pilot_funds',
                'label' => 'Zaliczka',
                'short' => 'plan',
                'tone' => 'warn',
                'title' => 'Zaplanowano zaliczkę: '.$amount.' — czeka na wypłatę',
            ];
        }

        return [
            'key' => 'pilot_funds',
            'label' => 'Zaliczka',
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
        if (! $event->requiresInsuranceWorkflow()) {
            return [
                'key' => 'insurance',
                'label' => 'Ubezp.',
                'short' => '—',
                'tone' => 'muted',
                'title' => 'Brak wymogu ubezpieczenia',
            ];
        }

        if ($event->isInsuranceCompleted()) {
            return [
                'key' => 'insurance',
                'label' => 'Ubezp.',
                'short' => 'OK',
                'tone' => 'ok',
                'title' => 'Ubezpieczenie kompletne',
            ];
        }

        if ($event->hasInsuranceDataSaved()) {
            return [
                'key' => 'insurance',
                'label' => 'Ubezp.',
                'short' => 'w toku',
                'tone' => 'warn',
                'title' => $event->insuranceChecklistLabel(),
            ];
        }

        return [
            'key' => 'insurance',
            'label' => 'Ubezp.',
            'short' => 'brak',
            'tone' => 'danger',
            'title' => 'Ubezpieczenie do wystawienia',
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
}
