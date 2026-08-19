<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventHotelStay;
use App\Models\EventProgramPoint;
use App\Models\Reservation;
use App\Support\Reservations\ReservationFormDefaults;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jedna Reservation na hotel (kontrahent) w imprezie — wspólna dla wszystkich nocy tego hotelu.
 */
class HotelStayReservationSync
{
    public function __construct(
        private readonly ReservationTaskSyncService $taskSync,
    ) {}

    public function findForStay(EventHotelStay $stay): ?Reservation
    {
        if (! Schema::hasColumn('event_hotel_stays', 'reservation_id')) {
            return null;
        }

        $contractorId = filled($stay->contractor_id) ? (int) $stay->contractor_id : null;

        if (! $contractorId) {
            return null;
        }

        if (filled($stay->reservation_id)) {
            $linked = Reservation::query()->find((int) $stay->reservation_id);

            if ($linked && $linked->isActiveBooking()) {
                return $linked;
            }
        }

        return $this->findExistingForHotel((int) $stay->event_id, $contractorId);
    }

    public function ensureForStay(EventHotelStay $stay): ?Reservation
    {
        if (! Schema::hasColumn('event_hotel_stays', 'reservation_id')) {
            return null;
        }

        $contractorId = filled($stay->contractor_id) ? (int) $stay->contractor_id : null;

        if (! $contractorId) {
            $this->detachStay($stay);

            return null;
        }

        return DB::transaction(function () use ($stay, $contractorId): Reservation {
            $reservation = $this->findExistingForHotel((int) $stay->event_id, $contractorId);

            if (! $reservation) {
                $stay->loadMissing(['event', 'programPoint.event', 'programPoint.currency']);
                $defaults = $this->defaultsForStay($stay);

                $reservation = Reservation::query()->create([
                    'event_id' => (int) $stay->event_id,
                    'contractor_id' => $contractorId,
                    'program_point_id' => $stay->event_program_point_id,
                    'participant_count' => $defaults['participant_count'],
                    'reserved_amount' => $defaults['reserved_amount'],
                    'currency_id' => $defaults['currency_id'],
                    'amount_basis' => $defaults['amount_basis'],
                    'participant_scope' => $defaults['participant_scope'],
                    'convert_to_pln' => $defaults['convert_to_pln'],
                    'deposit_due_at' => $defaults['deposit_due_at'],
                    'status' => 'pending',
                    'reserved_at' => now(),
                    'created_by' => auth()->id(),
                ]);
            } elseif (
                blank($reservation->program_point_id)
                && filled($stay->event_program_point_id)
            ) {
                $reservation->forceFill([
                    'program_point_id' => (int) $stay->event_program_point_id,
                ])->saveQuietly();
            }

            $this->linkStaysForHotel((int) $stay->event_id, $contractorId, (int) $reservation->id);

            $point = $stay->programPoint
                ?? (filled($stay->event_program_point_id)
                    ? EventProgramPoint::query()->find((int) $stay->event_program_point_id)
                    : null);

            if ($point) {
                app(ProgramPointReservationSync::class)->linkPoints($reservation, $point);
            }

            $this->taskSync->sync($reservation->fresh([
                'event',
                'programPoint.templatePoint',
                'contractor',
            ]));

            return $reservation->fresh();
        });
    }

    /**
     * @param  array{
     *     status?: string,
     *     confirm_by?: ?string,
     *     confirmed_at?: ?string,
     *     deposit_due_at?: ?string,
     *     deposit_paid_at?: ?string,
     *     booking_reference?: ?string
     * }  $attributes
     */
    public function updateWorkflow(Reservation $reservation, array $attributes): Reservation
    {
        $allowed = collect($attributes)->only([
            'status',
            'confirm_by',
            'confirmed_at',
            'deposit_due_at',
            'deposit_paid_at',
            'booking_reference',
        ])->all();

        if ($allowed === []) {
            return $reservation;
        }

        if (
            array_key_exists('status', $allowed)
            && in_array($allowed['status'], ['confirmed', 'partially_confirmed', 'completed'], true)
            && blank($allowed['confirmed_at'] ?? $reservation->confirmed_at)
        ) {
            $allowed['confirmed_at'] = now()->toDateString();
        }

        $reservation->update($allowed);

        $fresh = $reservation->fresh([
            'event',
            'programPoint.templatePoint',
            'contractor',
        ]);

        $this->taskSync->sync($fresh);

        return $fresh;
    }

    public function detachStay(EventHotelStay $stay): void
    {
        if (! Schema::hasColumn('event_hotel_stays', 'reservation_id')) {
            return;
        }

        if (blank($stay->reservation_id) && ! $stay->exists) {
            return;
        }

        if (filled($stay->reservation_id) || $stay->isDirty('reservation_id')) {
            $stay->forceFill(['reservation_id' => null])->saveQuietly();
        }
    }

    /**
     * Istniejące noce z hotelem bez rezerwacji — jedna Reservation na kontrahenta.
     */
    public function backfillForEvent(Event $event): int
    {
        if (! Schema::hasColumn('event_hotel_stays', 'reservation_id')) {
            return 0;
        }

        $event->loadMissing('hotelStays');

        $groups = $event->hotelStays
            ->filter(fn (EventHotelStay $stay): bool => filled($stay->contractor_id))
            ->groupBy(fn (EventHotelStay $stay): int => (int) $stay->contractor_id);

        $ensured = 0;

        foreach ($groups as $stays) {
            $stay = $stays->sortBy('day')->first();
            if (! $stay) {
                continue;
            }

            $reservation = $this->ensureForStay($stay->fresh(['event', 'programPoint.event', 'programPoint.currency']));
            if ($reservation) {
                $ensured++;
            }
        }

        return $ensured;
    }

    /**
     * @return array{
     *     reserved_amount: ?float,
     *     participant_count: int,
     *     currency_id: ?int,
     *     amount_basis: string,
     *     participant_scope: string,
     *     convert_to_pln: bool,
     *     deposit_due_at: ?string
     * }
     */
    protected function defaultsForStay(EventHotelStay $stay): array
    {
        $point = $stay->programPoint;

        if (! $point && filled($stay->event_program_point_id)) {
            $point = EventProgramPoint::query()->with(['event', 'currency'])->find((int) $stay->event_program_point_id);
        }

        if ($point) {
            $defaults = ReservationFormDefaults::forProgramPoint($point);

            return [
                'reserved_amount' => $defaults['reserved_amount'],
                'participant_count' => $defaults['participant_count'],
                'currency_id' => $defaults['currency_id'],
                'amount_basis' => $defaults['amount_basis'],
                'participant_scope' => $defaults['participant_scope'],
                'convert_to_pln' => $defaults['convert_to_pln'],
                'deposit_due_at' => $defaults['deposit_due_at'],
            ];
        }

        return [
            'reserved_amount' => null,
            'participant_count' => max(1, (int) ($stay->event?->participant_count ?? 1)),
            'currency_id' => null,
            'amount_basis' => 'lump_sum',
            'participant_scope' => 'all',
            'convert_to_pln' => true,
            'deposit_due_at' => null,
        ];
    }

    /**
     * Po zapisie rezerwacji w Operacje → Rezerwacje: podepnij noce tego hotelu bez innej rezerwacji.
     */
    public function attachMatchingStays(Reservation $reservation): void
    {
        if (! Schema::hasColumn('event_hotel_stays', 'reservation_id')) {
            return;
        }

        if (! $reservation->isActiveBooking()) {
            return;
        }

        $eventId = (int) ($reservation->event_id ?? 0);
        $contractorId = filled($reservation->contractor_id) ? (int) $reservation->contractor_id : null;

        if ($eventId <= 0 || ! $contractorId) {
            return;
        }

        EventHotelStay::query()
            ->where('event_id', $eventId)
            ->where('contractor_id', $contractorId)
            ->where(function ($query) use ($reservation): void {
                $query->whereNull('reservation_id')
                    ->orWhere('reservation_id', $reservation->id);
            })
            ->update(['reservation_id' => $reservation->id]);

        $point = $reservation->programPoint;
        if (! $point) {
            $stayPointId = EventHotelStay::query()
                ->where('reservation_id', $reservation->id)
                ->whereNotNull('event_program_point_id')
                ->value('event_program_point_id');
            $point = $stayPointId
                ? EventProgramPoint::query()->find((int) $stayPointId)
                : null;
        }

        if ($point) {
            app(ProgramPointReservationSync::class)->linkPoints($reservation, $point);
        }
    }

    /**
     * @return Collection<int, Reservation>
     */
    public function reservationsForEvent(Event $event): Collection
    {
        if (! Schema::hasColumn('event_hotel_stays', 'reservation_id')) {
            return collect();
        }

        $event->loadMissing('hotelStays');

        $ids = $event->hotelStays
            ->pluck('reservation_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return collect();
        }

        return Reservation::query()
            ->whereIn('id', $ids)
            ->with('contractor')
            ->get()
            ->keyBy('id');
    }

    /**
     * Grupy hoteli z planu: jedna pozycja na kontrahenta.
     *
     * @return list<array{
     *     contractor_id: int,
     *     contractor_name: string,
     *     days: list<int>,
     *     reservation: ?Reservation,
     *     is_confirmed: bool
     * }>
     */
    public function hotelGroups(Event $event): array
    {
        $event->loadMissing(['hotelStays.contractor', 'hotelStays.reservation']);

        $groups = [];

        foreach ($event->hotelStays as $stay) {
            $contractorId = filled($stay->contractor_id) ? (int) $stay->contractor_id : null;

            if (! $contractorId) {
                continue;
            }

            if (! isset($groups[$contractorId])) {
                $groups[$contractorId] = [
                    'contractor_id' => $contractorId,
                    'contractor_name' => $stay->contractor?->displayLabel() ?? ('#'.$contractorId),
                    'days' => [],
                    'reservation' => $stay->reservation,
                    'is_confirmed' => false,
                ];
            }

            $groups[$contractorId]['days'][] = (int) $stay->day;

            if ($stay->reservation) {
                $groups[$contractorId]['reservation'] = $stay->reservation;
            }
        }

        return array_values(array_map(function (array $group): array {
            $group['days'] = collect($group['days'])->unique()->sort()->values()->all();
            $reservation = $group['reservation'];
            $group['is_confirmed'] = $reservation instanceof Reservation
                && $reservation->isActiveBooking()
                && in_array($reservation->status, ['confirmed', 'partially_confirmed', 'completed'], true);

            return $group;
        }, $groups));
    }

    protected function findExistingForHotel(int $eventId, int $contractorId): ?Reservation
    {
        $linkedId = EventHotelStay::query()
            ->where('event_id', $eventId)
            ->where('contractor_id', $contractorId)
            ->whereNotNull('reservation_id')
            ->value('reservation_id');

        if ($linkedId) {
            $linked = Reservation::query()->find((int) $linkedId);

            if ($linked && $linked->isActiveBooking()) {
                return $linked;
            }
        }

        return Reservation::query()
            ->where('event_id', $eventId)
            ->where('contractor_id', $contractorId)
            ->whereNotIn('status', ['cancelled', 'not_required'])
            ->latest('id')
            ->first();
    }

    protected function linkStaysForHotel(int $eventId, int $contractorId, int $reservationId): void
    {
        EventHotelStay::query()
            ->where('event_id', $eventId)
            ->where('contractor_id', $contractorId)
            ->update(['reservation_id' => $reservationId]);

        // Noce, które wcześniej wskazywały tę rezerwację, ale mają już inny hotel — odłącz.
        EventHotelStay::query()
            ->where('reservation_id', $reservationId)
            ->where(function ($query) use ($contractorId): void {
                $query->whereNull('contractor_id')
                    ->orWhere('contractor_id', '!=', $contractorId);
            })
            ->update(['reservation_id' => null]);
    }
}
