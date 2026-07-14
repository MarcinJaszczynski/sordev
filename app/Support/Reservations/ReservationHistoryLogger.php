<?php

namespace App\Support\Reservations;

use App\Models\Reservation;
use App\Models\ReservationHistory;

class ReservationHistoryLogger
{
    /** @var list<string> */
    private const TRACKED_FIELDS = [
        'status',
        'booking_reference',
        'confirm_by',
        'confirmed_at',
        'deposit_due_at',
        'deposit_paid_at',
        'reserved_amount',
        'office_notes',
        'notes',
    ];

    public static function logCreated(Reservation $reservation): void
    {
        static::write($reservation, 'created', null, null, null, 'Utworzono rezerwację');
    }

    public static function logUpdated(Reservation $reservation): void
    {
        foreach (self::TRACKED_FIELDS as $field) {
            if (! $reservation->wasChanged($field)) {
                continue;
            }

            $old = $reservation->getOriginal($field);
            $new = $reservation->{$field};

            $action = $field === 'status' ? 'status_changed' : 'updated';
            $description = $field === 'status'
                ? sprintf(
                    'Status: %s → %s',
                    Reservation::$statuses[$old] ?? $old ?? '—',
                    Reservation::$statuses[$new] ?? $new ?? '—',
                )
                : sprintf('Zmieniono %s', static::fieldLabel($field));

            static::write($reservation, $action, $field, $old, $new, $description);
        }
    }

    public static function logDeleted(Reservation $reservation): void
    {
        static::write($reservation, 'deleted', null, null, null, 'Usunięto rezerwację');
    }

    private static function write(
        Reservation $reservation,
        string $action,
        ?string $field,
        mixed $oldValue,
        mixed $newValue,
        ?string $description,
    ): void {
        if (! \Illuminate\Support\Facades\Schema::hasTable('reservation_history')) {
            return;
        }

        ReservationHistory::query()->create([
            'reservation_id' => $reservation->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'field' => $field,
            'old_value' => static::normalizeValue($oldValue),
            'new_value' => static::normalizeValue($newValue),
            'description' => $description,
            'ip_address' => request()?->ip(),
        ]);
    }

    private static function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return $value;
    }

    private static function fieldLabel(string $field): string
    {
        return match ($field) {
            'confirm_by' => 'termin potwierdzenia',
            'confirmed_at' => 'data potwierdzenia',
            'deposit_due_at' => 'termin zaliczki',
            'deposit_paid_at' => 'data zapłaty zaliczki',
            'booking_reference' => 'numer rezerwacji',
            'reserved_amount' => 'kwota',
            'office_notes' => 'notatki biura',
            'notes' => 'uwagi',
            default => $field,
        };
    }
}
