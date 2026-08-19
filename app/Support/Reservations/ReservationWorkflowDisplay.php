<?php

namespace App\Support\Reservations;

use App\Models\EventSettlementCost;
use App\Models\Reservation;
use App\Models\ReservationHistory;
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

        if (\App\Support\CalendarDay::isBeforeToday($due) && ! in_array($reservation->status, ['cancelled', 'not_required', 'completed'], true)) {
            return 'overdue';
        }

        return 'pending';
    }

    public static function depositStatusLabel(Reservation $reservation): string
    {
        return match (self::depositStatus($reservation)) {
            'paid' => 'Zaliczka zapłacona',
            'overdue' => 'Zaliczka po terminie',
            'pending' => \App\Support\CalendarDay::isToday($reservation->deposit_due_at)
                ? 'Zaliczka do dziś'
                : 'Zaliczka oczekuje',
            default => 'Brak zaliczki',
        };
    }

    /**
     * @return array{paid_at: string, marked_by: string|null}|null
     */
    public static function depositPaidDetails(Reservation $reservation): ?array
    {
        if (! filled($reservation->deposit_paid_at)) {
            return null;
        }

        $historyEntry = $reservation->relationLoaded('historyEntries')
            ? $reservation->historyEntries
                ->first(function (ReservationHistory $entry): bool {
                    return $entry->field === 'deposit_paid_at' && filled($entry->new_value);
                })
            : $reservation->historyEntries()
                ->where('field', 'deposit_paid_at')
                ->whereNotNull('new_value')
                ->latest('id')
                ->with('user:id,name')
                ->first();

        $markedBy = $historyEntry?->relationLoaded('user')
            ? ($historyEntry->user?->name ?: null)
            : null;

        if ($markedBy === null && $historyEntry && ! $historyEntry->relationLoaded('user')) {
            $historyEntry->loadMissing('user:id,name');
            $markedBy = $historyEntry->user?->name ?: null;
        }

        return [
            'paid_at' => self::formatDate($reservation->deposit_paid_at),
            'marked_by' => $markedBy,
        ];
    }

    /**
     * @return array{status: string, text: string}
     */
    public static function reservationLine(Reservation $reservation): array
    {
        $status = Reservation::$statuses[$reservation->status] ?? $reservation->status;
        $when = null;

        if (in_array($reservation->status, ['confirmed', 'partially_confirmed', 'completed'], true) && filled($reservation->confirmed_at)) {
            $when = self::formatDate($reservation->confirmed_at);
        } elseif (filled($reservation->reserved_at)) {
            $when = self::formatDate($reservation->reserved_at);
        }

        $parts = array_filter([
            $status,
            $when,
            filled($reservation->confirm_by) && ! in_array($reservation->status, ['confirmed', 'partially_confirmed', 'completed'], true)
                ? 'potw. do '.self::formatDate($reservation->confirm_by)
                : null,
            filled($reservation->booking_reference) ? 'nr '.$reservation->booking_reference : null,
        ]);

        return [
            'status' => (string) $reservation->status,
            'text' => implode(' · ', $parts),
        ];
    }

    /**
     * @return array{status: string, text: string}
     */
    public static function depositLine(Reservation $reservation): array
    {
        $status = self::depositStatus($reservation);

        if ($status === 'paid') {
            $details = self::depositPaidDetails($reservation);
            $parts = array_filter([
                $details['paid_at'] ?? self::formatDate($reservation->deposit_paid_at),
                filled($details['marked_by'] ?? null) ? $details['marked_by'] : null,
            ]);

            return [
                'status' => $status,
                'text' => implode(' · ', $parts) ?: 'zapłacona',
            ];
        }

        if ($status === 'not_set') {
            return [
                'status' => $status,
                'text' => 'brak',
            ];
        }

        $due = filled($reservation->deposit_due_at)
            ? 'do '.self::formatDate($reservation->deposit_due_at)
            : self::depositStatusLabel($reservation);

        return [
            'status' => $status,
            'text' => $due,
        ];
    }

    /**
     * @param  array<string, mixed>  $finance
     * @return array{text: string, tone: string}|null
     */
    public static function remainingLine(array $finance): ?array
    {
        if (! empty($finance['hideSetParentFinance'])) {
            return null;
        }

        $remaining = trim((string) ($finance['remaining'] ?? ''));
        $paidStatus = (string) ($finance['paidStatus'] ?? 'none');

        if ($paidStatus === 'full' || $remaining === '' || $remaining === '—') {
            return null;
        }

        $payerKey = (string) ($finance['paidBy'] ?? 'office');
        $payer = EventSettlementCost::$paidByOptions[$payerKey] ?? 'Biuro';
        $parts = [$payer, $remaining];

        if (filled($finance['dueDateLabel'] ?? null)) {
            $parts[] = 'do '.$finance['dueDateLabel'];
        }

        return [
            'text' => implode(' · ', $parts),
            'tone' => $paidStatus === 'partial' ? 'warn' : 'due',
        ];
    }

    /**
     * @return array{color: string, tooltip: string}
     */
    public static function depositBadge(Reservation $reservation): array
    {
        return match (self::depositStatus($reservation)) {
            'paid' => [
                'color' => 'green',
                'tooltip' => 'Zaliczka zapłacona'
                    .(filled($reservation->deposit_paid_at) ? ' ('.self::formatDate($reservation->deposit_paid_at).')' : ''),
            ],
            'overdue' => [
                'color' => 'red',
                'tooltip' => 'Zaliczka po terminie'
                    .(filled($reservation->deposit_due_at) ? ' (do '.self::formatDate($reservation->deposit_due_at).')' : ''),
            ],
            'pending' => [
                'color' => 'amber',
                'tooltip' => \App\Support\CalendarDay::isToday($reservation->deposit_due_at)
                    ? 'Zaliczka do zapłaty dziś'
                        .(filled($reservation->deposit_due_at) ? ' ('.self::formatDate($reservation->deposit_due_at).')' : '')
                    : 'Zaliczka do zapłaty'
                        .(filled($reservation->deposit_due_at) ? ' do '.self::formatDate($reservation->deposit_due_at) : ''),
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
            $lines[] = 'Potwierdzić do: '.self::formatDate($reservation->confirm_by);
        }

        if (filled($reservation->confirmed_at)) {
            $lines[] = 'Potwierdzono: '.self::formatDate($reservation->confirmed_at);
        }

        if (filled($reservation->deposit_due_at)) {
            $lines[] = 'Zaliczka do: '.self::formatDate($reservation->deposit_due_at);
        }

        if (filled($reservation->deposit_paid_at)) {
            $lines[] = 'Zaliczka zapłacona: '.self::formatDate($reservation->deposit_paid_at);
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
