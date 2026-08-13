<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Contract;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventSettlementParticipantPayment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Cennik diety na umowie → dopłata po wyborze diety w portalu.
 *
 * base_amount_due (meta) + (osoby z dietą × daily × dni) = amount_due.
 */
final class ContractDietSurchargeService
{
    /**
     * @param  array<string, mixed>  $data  dane z wizarda
     * @return array{requires_diet: bool, diet_daily_pln: ?float, diet_options: list<string>, pricing: array{base_amount_due: float, diet_surcharge_pln: float}}
     */
    public function catalogFromWizardData(array $data, float $baseAmountDue): array
    {
        $enabled = (bool) ($data['requires_diet'] ?? false);
        $daily = $enabled ? round((float) ($data['diet_daily_pln'] ?? 20), 2) : null;
        $options = [];
        if ($enabled) {
            $raw = $data['diet_options'] ?? [];
            if (is_string($raw)) {
                $raw = preg_split('/[,;]+/', $raw) ?: [];
            }
            if (is_array($raw)) {
                $options = collect($raw)
                    ->map(fn ($v): string => trim((string) $v))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
            }
        }

        return [
            'requires_diet' => $enabled,
            'diet_daily_pln' => $daily,
            'diet_options' => $options,
            'pricing' => [
                'base_amount_due' => round($baseAmountDue, 2),
                'diet_surcharge_pln' => 0.0,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public function mergeCatalogIntoMeta(array $meta, array $catalog): array
    {
        $meta['diet_options'] = $catalog['diet_options'];
        $meta['pricing'] = array_merge(
            is_array($meta['pricing'] ?? null) ? $meta['pricing'] : [],
            $catalog['pricing'],
        );

        return $meta;
    }

    public function applyForParticipant(EventParticipant $participant): void
    {
        app(ContractExtrasSurchargeService::class)->applyForParticipantWithoutDietLoop($participant);
    }

    public function applyForContract(Contract $contract): Contract
    {
        return app(ContractExtrasSurchargeService::class)->applyForContract($contract);
    }

    /**
     * Legacy: tylko dieta (używane gdy brak meta.extras).
     */
    public function applyForContractLegacy(Contract $contract): Contract
    {
        if (! Schema::hasColumn('contracts', 'requires_diet') || ! $contract->requires_diet) {
            return $contract;
        }

        $daily = round((float) ($contract->diet_daily_pln ?? 0), 2);
        if ($daily <= 0.009) {
            return $contract;
        }

        $meta = is_array($contract->meta) ? $contract->meta : [];
        $prevSurcharge = round((float) data_get($meta, 'pricing.diet_surcharge_pln', 0), 2);
        $base = data_get($meta, 'pricing.base_amount_due');
        if ($base === null) {
            $base = round((float) $contract->amount_due - $prevSurcharge, 2);
        } else {
            $base = round((float) $base, 2);
        }

        $days = $this->durationDays($contract);
        $withDiet = $this->participantsWithDiet($contract)->count();
        $surcharge = round($withDiet * $daily * $days, 2);
        $newDue = round(max(0, $base + $surcharge), 2);

        $meta['pricing'] = array_merge(
            is_array($meta['pricing'] ?? null) ? $meta['pricing'] : [],
            [
                'base_amount_due' => $base,
                'diet_surcharge_pln' => $surcharge,
                'diet_days' => $days,
                'diet_participants' => $withDiet,
            ],
        );

        $paid = round((float) ($contract->amount_paid ?? 0), 2);
        $paymentStatus = 'pending';
        if ($newDue <= 0.009) {
            $paymentStatus = (string) ($contract->payment_status ?? 'pending');
        } elseif ($paid + 0.009 >= $newDue) {
            $paymentStatus = 'paid';
        } elseif ($paid > 0.009) {
            $paymentStatus = 'partial';
        }

        $contract->forceFill([
            'amount_due' => $newDue,
            'meta' => $meta,
            'payment_status' => $paymentStatus,
        ])->save();

        if (Schema::hasTable('contract_payment_schedules')) {
            app(ContractInstallmentCheckoutService::class)->alignSchedulesWithContractTotal($contract->fresh());
        }

        $this->syncLinkedPayment($contract->fresh());

        return $contract->fresh() ?? $contract;
    }

    /**
     * @return Collection<int, Contract>
     */
    public function contractsForParticipantPublic(EventParticipant $participant): Collection
    {
        return $this->contractsForParticipant($participant);
    }

    /**
     * @return Collection<int, EventParticipant>
     */
    public function participantsForContractPublic(Contract $contract): Collection
    {
        return $this->participantsForContract($contract);
    }

    /**
     * @return array{enabled: bool, daily_pln: float, days: int, per_person_pln: float, options: list<string>, base_pln: float, surcharge_pln: float}
     */
    public function presentation(Contract $contract): array
    {
        $enabled = (bool) $contract->requires_diet;
        $daily = round((float) ($contract->diet_daily_pln ?? 0), 2);
        $days = $this->durationDays($contract);
        $meta = is_array($contract->meta) ? $contract->meta : [];
        $options = data_get($meta, 'diet_options', []);
        if (! is_array($options)) {
            $options = [];
        }
        $options = array_values(array_filter(array_map(
            static fn ($v): string => trim((string) $v),
            $options,
        )));

        return [
            'enabled' => $enabled && $daily > 0.009,
            'daily_pln' => $daily,
            'days' => $days,
            'per_person_pln' => round($daily * $days, 2),
            'options' => $options,
            'base_pln' => round((float) data_get($meta, 'pricing.base_amount_due', $contract->amount_due ?? 0), 2),
            'surcharge_pln' => round((float) data_get($meta, 'pricing.diet_surcharge_pln', 0), 2),
        ];
    }

    public function durationDays(Contract $contract): int
    {
        $event = $contract->relationLoaded('event')
            ? $contract->event
            : $contract->event()->first();

        if ($event instanceof Event) {
            $days = (int) ($event->duration_days ?? 0);
            if ($days > 0) {
                return $days;
            }
            if ($event->start_date && $event->end_date) {
                return max(1, $event->start_date->diffInDays($event->end_date) + 1);
            }
        }

        if ($contract->event_start_date && $contract->event_end_date) {
            return max(1, $contract->event_start_date->diffInDays($contract->event_end_date) + 1);
        }

        return 1;
    }

    /**
     * @return Collection<int, EventParticipant>
     */
    public function participantsWithDiet(Contract $contract): Collection
    {
        return $this->participantsForContract($contract)
            ->filter(fn (EventParticipant $p): bool => filled(trim((string) ($p->diet ?? ''))));
    }

    /**
     * @return Collection<int, EventParticipant>
     */
    public function participantsForContract(Contract $contract): Collection
    {
        if (! Schema::hasTable('event_participants')) {
            return collect();
        }

        $query = EventParticipant::query()->where('event_id', $contract->event_id);

        // Indywidualna / per-osoba: uczestnicy powiązani z tą umową.
        $linked = (clone $query)->where('contract_id', $contract->id)->get();
        if ($linked->isNotEmpty()) {
            return $linked;
        }

        if ($contract->participant_payment_id) {
            $viaPayment = (clone $query)
                ->where('participant_payment_id', $contract->participant_payment_id)
                ->get();
            if ($viaPayment->isNotEmpty()) {
                return $viaPayment;
            }
        }

        // Umowa grupowa (jedna płatność za grupę): wszyscy aktywni uczestnicy imprezy.
        if ($contract->isGroup()
            || (string) data_get($contract->meta, 'generation_mode') === 'group_ordering'
        ) {
            return $query
                ->where(function ($q): void {
                    $q->whereNull('status')
                        ->orWhere('status', EventParticipant::STATUS_ACTIVE);
                })
                ->get();
        }

        return collect();
    }

    /**
     * @return Collection<int, Contract>
     */
    private function contractsForParticipant(EventParticipant $participant): Collection
    {
        $ids = collect();
        if ($participant->contract_id) {
            $ids->push((int) $participant->contract_id);
        }

        if ($participant->participant_payment_id && Schema::hasTable('contracts')) {
            $ids = $ids->merge(
                Contract::query()
                    ->where('participant_payment_id', $participant->participant_payment_id)
                    ->pluck('id')
            );
        }

        $ids = $ids->filter()->unique()->values();
        if ($ids->isEmpty() && $participant->event_id) {
            return Contract::query()
                ->where('event_id', $participant->event_id)
                ->get()
                ->filter(fn (Contract $contract): bool => $contract->isGroup()
                    || (string) data_get($contract->meta, 'generation_mode') === 'group_ordering'
                    || (bool) $contract->requires_diet
                    || $this->contractHasExtras($contract))
                ->values();
        }

        if ($ids->isEmpty()) {
            return collect();
        }

        return Contract::query()->whereIn('id', $ids->all())->get();
    }

    private function contractHasExtras(Contract $contract): bool
    {
        $extras = data_get($contract->meta, 'extras', []);

        return is_array($extras) && $extras !== [];
    }

    private function syncLinkedPayment(Contract $contract): void
    {
        if (! $contract->participant_payment_id || ! Schema::hasTable('event_settlement_participant_payments')) {
            return;
        }

        $payment = EventSettlementParticipantPayment::query()->find($contract->participant_payment_id);
        if (! $payment) {
            return;
        }

        $due = round((float) $contract->amount_due, 2);
        $paid = round((float) $payment->paid_amount_pln, 2);
        $status = 'pending';
        if ($due > 0.009 && $paid + 0.009 >= $due) {
            $status = 'paid';
        } elseif ($paid > 0.009) {
            $status = 'partial';
        }

        $payment->forceFill([
            'due_amount_pln' => $due,
            'payment_status' => $status,
        ])->saveQuietly();
    }
}
