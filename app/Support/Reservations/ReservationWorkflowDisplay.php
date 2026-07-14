<?php

namespace App\Support\Reservations;

use App\Models\Reservation;
use Carbon\Carbon;

final class ReservationWorkflowDisplay
{
    public static function depositStatus(Reservation $reservation): string
    {
        if (filled($reservation->deposit_paid_at)) {
            return 'paid';
        }

        if (! filled($reservation->deposit_due_at)) {
            return 'not_set';
        }

        $due = $reservation->deposit_due_at instanceof Carbon
            ? $reservation->deposit_due_at
            : Carbon::parse($reservation->deposit_due_at);

        if ($due->isPast() && ! in_array($reservation->status, ['cancelled', 'not_required', 'completed'], true)) {
            return 'overdue';
        }

        return 'pending';
    }

    public static function depositStatusLabel(Reservation $reservation): string
    {
        return match (static::depositStatus($reservation)) {
            'paid' => 'Zaliczka zapłacona',
            'overdue' => 'Zaliczka po terminie',
            'pending' => 'Zaliczka oczekuje',
            default => 'Brak zaliczki',
        };
    }

    /**
     * @return array{color: string, tooltip: string}
     */
    public static function depositBadge(Reservation $reservation): array
    {
        return match (static::depositStatus($reservation)) {
            'paid' => [
                'color' => 'green',
                'tooltip' => 'Zaliczka zapłacona'
                    .(filled($reservation->deposit_paid_at) ? ' ('.static::formatDate($reservation->deposit_paid_at).')' : ''),
            ],
            'overdue' => [
                'color' => 'red',
                'tooltip' => 'Zaliczka po terminie'
                    .(filled($reservation->deposit_due_at) ? ' (do '.static::formatDate($reservation->deposit_due_at).')' : ''),
            ],
            'pending' => [
                'color' => 'amber',
                'tooltip' => 'Zaliczka do zapłaty'
                    .(filled($reservation->deposit_due_at) ? ' do '.static::formatDate($reservation->deposit_due_at) : ''),
            ],
            default => [
                'color' => 'gray',
                'tooltip' => 'Brak terminu zaliczki',
            ],
        };
    }

    /**
     * @return list<string>
     */
    public static function workflowLines(Reservation $reservation): array
    {
        $lines = [
            'Status: '.(Reservation::$statuses[$reservation->status] ?? $reservation->status),
        ];

        if (filled($reservation->confirm_by)) {
            $lines[] = 'Potwierdzić do: '.static::formatDate($reservation->confirm_by);
        }

        if (filled($reservation->confirmed_at)) {
            $lines[] = 'Potwierdzono: '.static::formatDate($reservation->confirmed_at);
        }

        if (filled($reservation->deposit_due_at)) {
            $lines[] = 'Zaliczka do: '.static::formatDate($reservation->deposit_due_at);
        }

        if (filled($reservation->deposit_paid_at)) {
            $lines[] = 'Zaliczka zapłacona: '.static::formatDate($reservation->deposit_paid_at);
        }

        return $lines;
    }

    public static function formatDate(mixed $value): string
    {
        if (! $value) {
            return '—';
        }

        if ($value instanceof Carbon) {
            return $value->format('d.m.Y');
        }

        return Carbon::parse($value)->format('d.m.Y');
    }
}
