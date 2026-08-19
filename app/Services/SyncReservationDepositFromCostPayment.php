<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EventSettlementCost;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Rezerwacja i wpłaty to jeden obieg pieniędzy, nie dwa rejestry.
 * Kolejna zaliczka / dopłata to nowy wiersz — suma zmniejsza pozostałą kwotę.
 */
final class SyncReservationDepositFromCostPayment
{
    public function markPaidFromPayment(EventSettlementCost $plan, EventSettlementCost $payment): void
    {
        if (! EventSettlementCost::isAdvancePaymentType($payment->advance_type ?? null)
            && (string) $payment->payment_status !== 'advance_paid') {
            return;
        }

        if ($plan->source_type !== 'program_point' || ! $plan->source_id) {
            return;
        }

        $reservation = $this->resolveReservationForPayment($plan, $payment);
        if (! $reservation) {
            return;
        }

        $this->attachPaymentToReservation($payment, $reservation);
        $this->mirrorPaymentOntoReservation($reservation, $payment);
    }

    public function refreshAfterPaymentsChanged(EventSettlementCost $plan): void
    {
        if ($plan->source_type !== 'program_point' || ! $plan->source_id) {
            return;
        }

        if ($this->hasAdvancePayment($plan)) {
            $latest = $this->latestAdvancePayment($plan);
            if ($latest) {
                $this->markPaidFromPayment($plan, $latest);
            }

            return;
        }

        foreach ($this->linkedReservations($plan) as $reservation) {
            if (! filled($reservation->deposit_paid_at)) {
                continue;
            }

            $reservation->forceFill(['deposit_paid_at' => null])->saveQuietly();
        }
    }

    /**
     * Data zaliczki w rezerwacji bez wpłat — pierwsza wpłata.
     * Gdy wpłaty już są, nie nadpisujemy ich: kolejne raty idą wyłącznie przez listę wpłat.
     */
    public function syncFromReservation(Reservation $reservation): void
    {
        if (! filled($reservation->deposit_paid_at)) {
            return;
        }

        $plan = $reservation->settlementCost;
        if (! $plan || $plan->source_type !== 'program_point') {
            return;
        }

        $existing = $this->existingAdvanceForReservation($plan, $reservation);
        if ($existing) {
            $this->attachPaymentToReservation($existing, $reservation);

            return;
        }

        $depositTouched = $reservation->wasRecentlyCreated
            || $reservation->wasChanged(['deposit_paid_at', 'deposit_due_at', 'reserved_amount', 'settlement_cost_id']);
        if (! $depositTouched) {
            return;
        }

        $amount = round((float) ($reservation->reserved_amount ?? 0), 2);
        if ($amount <= 0.009) {
            $plan->forceFill([
                'payment_status' => 'advance_paid',
                'paid_at' => $this->dateString($reservation->deposit_paid_at),
            ])->saveQuietly();

            return;
        }

        $plan->loadMissing('settlement');
        $settlement = $plan->settlement;
        if (! $settlement) {
            return;
        }

        $paidBy = (string) ($plan->paid_by ?? 'office');
        if (! array_key_exists($paidBy, EventSettlementCost::$paidByOptions)) {
            $paidBy = 'office';
        }

        $payload = [
            'paid_at' => $this->dateString($reservation->deposit_paid_at),
            'advance_due_date' => filled($reservation->deposit_due_at) ? $this->dateString($reservation->deposit_due_at) : null,
            'actual_amount' => $amount,
            'actual_amount_pln' => $amount,
            'advance_amount' => $amount,
            'actual_currency_id' => $plan->planned_currency_id,
            'actual_rate' => $plan->planned_rate ?? 1,
            'paid_by' => $paidBy,
            'advance_type' => 'advance',
            'payment_status' => 'advance_paid',
            'payment_method' => $paidBy === 'pilot' ? 'cash' : 'transfer',
        ];

        if ($this->supportsReservationLink()) {
            $payload['reservation_id'] = $reservation->id;
        }

        $existingCount = $settlement->costs()
            ->where('source_type', 'program_point_payment')
            ->where('source_id', $plan->source_id)
            ->count();

        $settlement->costs()->create([
            'source_type' => 'program_point_payment',
            'source_id' => $plan->source_id,
            'name' => ($plan->name ?: 'Koszt').' • zaliczka rezerwacji',
            'planned_amount' => 0,
            'planned_currency_id' => $plan->planned_currency_id,
            'planned_convert_to_pln' => $plan->planned_convert_to_pln ?? true,
            'planned_rate' => $plan->planned_rate ?? 1,
            'planned_amount_pln' => 0,
            'contractor_id' => $plan->contractor_id,
            'order' => (int) ($plan->order ?? 0) + $existingCount + 1,
            ...$payload,
        ]);

        app(SettlementPaymentHealthService::class)
            ->syncPlanPaymentStatus($plan->fresh(), $plan->settlement?->costs()->get() ?? collect());
    }

    public function resolveReservationIdForNewPayment(EventSettlementCost $plan, ?int $requestedId = null): ?int
    {
        if (! $this->supportsReservationLink() || $plan->source_type !== 'program_point') {
            return null;
        }

        $reservations = $this->linkedReservations($plan);
        if ($requestedId && $reservations->contains(fn (Reservation $reservation): bool => (int) $reservation->id === $requestedId)) {
            return $requestedId;
        }

        if ($reservations->count() === 1) {
            return (int) $reservations->first()->id;
        }

        return null;
    }

    public function existingAdvanceForReservation(EventSettlementCost $plan, Reservation $reservation): ?EventSettlementCost
    {
        $query = $this->advancePaymentsQuery($plan);

        if ($this->supportsReservationLink()) {
            $linked = (clone $query)->where('reservation_id', $reservation->id)->orderByDesc('id')->first();
            if ($linked) {
                return $linked;
            }
        }

        return $query
            ->when(
                $this->supportsReservationLink(),
                fn ($inner) => $inner->whereNull('reservation_id'),
            )
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return Collection<int, Reservation>
     */
    public function linkedReservations(EventSettlementCost $plan): Collection
    {
        return Reservation::query()
            ->where(function ($query) use ($plan): void {
                $query->where('settlement_cost_id', $plan->id);

                if ($plan->source_id) {
                    $query->orWhere('program_point_id', (int) $plan->source_id);
                }
            })
            ->whereNotIn('status', ['cancelled', 'not_required'])
            ->get();
    }

    private function resolveReservationForPayment(EventSettlementCost $plan, EventSettlementCost $payment): ?Reservation
    {
        if ($this->supportsReservationLink() && filled($payment->reservation_id)) {
            return Reservation::query()->find((int) $payment->reservation_id);
        }

        $reservations = $this->linkedReservations($plan);

        return $reservations->count() === 1 ? $reservations->first() : null;
    }

    private function attachPaymentToReservation(EventSettlementCost $payment, Reservation $reservation): void
    {
        if (! $this->supportsReservationLink()) {
            return;
        }

        if ((int) $payment->reservation_id === (int) $reservation->id) {
            return;
        }

        $payment->forceFill(['reservation_id' => $reservation->id])->saveQuietly();
    }

    private function mirrorPaymentOntoReservation(Reservation $reservation, EventSettlementCost $payment): void
    {
        $paidAt = $this->dateString($payment->paid_at) ?? now()->toDateString();
        $amount = round((float) (
            $payment->advance_amount
            ?? $payment->actual_amount
            ?? $payment->actual_amount_pln
            ?? 0
        ), 2);

        $updates = [];

        if ((string) $reservation->deposit_paid_at?->toDateString() !== $paidAt) {
            $updates['deposit_paid_at'] = $paidAt;
        }

        if (
            $amount > 0.009
            && (
                $reservation->reserved_amount === null
                || (float) $reservation->reserved_amount <= 0.009
            )
        ) {
            $updates['reserved_amount'] = $amount;
        }

        if ($updates === []) {
            return;
        }

        $reservation->forceFill($updates)->saveQuietly();
    }

    /**
     * Termin zaliczki z planu kosztów — ta sama data na powiązanych rezerwacjach.
     */
    public function syncDueDateFromPlan(EventSettlementCost $plan): void
    {
        if ($plan->source_type !== 'program_point' || ! $plan->source_id) {
            return;
        }

        $due = $this->dateString($plan->advance_due_date);

        foreach ($this->linkedReservations($plan) as $reservation) {
            if ($this->dateString($reservation->deposit_due_at) === $due) {
                continue;
            }

            $reservation->forceFill(['deposit_due_at' => $due])->saveQuietly();
        }
    }

    private function hasAdvancePayment(EventSettlementCost $plan): bool
    {
        return $this->advancePaymentsQuery($plan)->exists();
    }

    private function latestAdvancePayment(EventSettlementCost $plan): ?EventSettlementCost
    {
        return $this->advancePaymentsQuery($plan)
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<EventSettlementCost>
     */
    private function advancePaymentsQuery(EventSettlementCost $plan)
    {
        return EventSettlementCost::query()
            ->where('settlement_id', $plan->settlement_id)
            ->where('source_type', 'program_point_payment')
            ->where('source_id', (int) $plan->source_id)
            ->where(function ($query): void {
                $query->whereIn('advance_type', ['advance', 'deposit'])
                    ->orWhere('payment_status', 'advance_paid');
            });
    }

    private function supportsReservationLink(): bool
    {
        return Schema::hasColumn('event_settlement_costs', 'reservation_id');
    }

    private function dateString(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->toDateString();
        }

        return Carbon::parse($value)->toDateString();
    }
}
