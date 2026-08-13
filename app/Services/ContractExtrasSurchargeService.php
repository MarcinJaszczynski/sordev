<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Contract;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventSettlementParticipantPayment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Katalog dodatkowych świadczeń na umowie (dieta + inne) → dopłata po wyborze w portalu.
 */
final class ContractExtrasSurchargeService
{
    public function __construct(
        private ContractDietSurchargeService $diet,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     requires_diet: bool,
     *     diet_daily_pln: ?float,
     *     diet_options: list<string>,
     *     extras: list<array<string, mixed>>,
     *     pricing: array{base_amount_due: float, diet_surcharge_pln: float, extras_surcharge_pln: float}
     * }
     */
    public function catalogFromWizardData(array $data, float $baseAmountDue): array
    {
        $dietCatalog = $this->diet->catalogFromWizardData($data, $baseAmountDue);
        $extras = $this->normalizeExtrasInput($data['contract_extras'] ?? []);

        if ($dietCatalog['requires_diet']) {
            $extras = $this->upsertExtra($extras, [
                'key' => 'diet',
                'label' => 'Dieta specjalna',
                'pricing' => 'per_day',
                'amount_pln' => (float) ($dietCatalog['diet_daily_pln'] ?? 20),
                'options' => $dietCatalog['diet_options'],
                'applies' => 'participant',
            ]);
        }

        return [
            ...$dietCatalog,
            'extras' => $extras,
            'pricing' => [
                'base_amount_due' => round($baseAmountDue, 2),
                'diet_surcharge_pln' => 0.0,
                'extras_surcharge_pln' => 0.0,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $catalog
     * @return array<string, mixed>
     */
    public function mergeCatalogIntoMeta(array $meta, array $catalog): array
    {
        $meta = $this->diet->mergeCatalogIntoMeta($meta, $catalog);
        $meta['extras'] = $catalog['extras'] ?? [];
        $meta['pricing'] = array_merge(
            is_array($meta['pricing'] ?? null) ? $meta['pricing'] : [],
            $catalog['pricing'] ?? [],
        );

        return $meta;
    }

    public function applyForParticipant(EventParticipant $participant): void
    {
        $this->applyForParticipantWithoutDietLoop($participant);
    }

    public function applyForParticipantWithoutDietLoop(EventParticipant $participant): void
    {
        $contracts = $this->diet->contractsForParticipantPublic($participant);
        // Umowy z extras LUB dietą.
        if ($contracts->isEmpty() && $participant->event_id) {
            $contracts = Contract::query()
                ->where('event_id', $participant->event_id)
                ->get()
                ->filter(function (Contract $contract): bool {
                    $extras = $this->resolvedExtras($contract);

                    return $extras !== [] || (bool) $contract->requires_diet;
                })
                ->values();
        }

        foreach ($contracts as $contract) {
            $this->applyForContract($contract);
        }
    }

    public function applyForContract(Contract $contract): Contract
    {
        $extras = $this->resolvedExtras($contract);
        if ($extras === []) {
            // Fallback: sama dieta legacy.
            return $this->diet->applyForContractLegacy($contract);
        }

        $meta = is_array($contract->meta) ? $contract->meta : [];
        $prevExtras = round((float) data_get($meta, 'pricing.extras_surcharge_pln', 0), 2);
        $prevDiet = round((float) data_get($meta, 'pricing.diet_surcharge_pln', 0), 2);
        $base = data_get($meta, 'pricing.base_amount_due');
        if ($base === null) {
            $base = round((float) $contract->amount_due - $prevExtras - $prevDiet, 2);
        } else {
            $base = round((float) $base, 2);
        }

        $days = $this->diet->durationDays($contract);
        $dietSurcharge = 0.0;
        $extrasSurcharge = 0.0;

        foreach ($extras as $extra) {
            $amount = $this->surchargeForExtra($contract, $extra, $days);
            if (($extra['key'] ?? '') === 'diet') {
                $dietSurcharge += $amount;
            } else {
                $extrasSurcharge += $amount;
            }
        }

        $totalSurcharge = round($dietSurcharge + $extrasSurcharge, 2);
        $newDue = round(max(0, $base + $totalSurcharge), 2);

        $meta['pricing'] = array_merge(
            is_array($meta['pricing'] ?? null) ? $meta['pricing'] : [],
            [
                'base_amount_due' => $base,
                'diet_surcharge_pln' => round($dietSurcharge, 2),
                'extras_surcharge_pln' => round($extrasSurcharge, 2),
                'extras_total_surcharge_pln' => $totalSurcharge,
                'diet_days' => $days,
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
     * @return list<array{key: string, label: string, pricing: string, amount_pln: float, options: list<string>, applies: string, per_unit_pln: float}>
     */
    public function presentation(Contract $contract): array
    {
        $days = $this->diet->durationDays($contract);
        $rows = [];
        foreach ($this->resolvedExtras($contract) as $extra) {
            $amount = (float) ($extra['amount_pln'] ?? 0);
            $pricing = (string) ($extra['pricing'] ?? 'flat');
            $perUnit = match ($pricing) {
                'per_day' => round($amount * $days, 2),
                'per_person' => round($amount, 2),
                default => round($amount, 2),
            };
            $rows[] = [
                'key' => (string) ($extra['key'] ?? ''),
                'label' => (string) ($extra['label'] ?? 'Świadczenie'),
                'pricing' => $pricing,
                'amount_pln' => $amount,
                'options' => array_values(array_filter(array_map('strval', $extra['options'] ?? []))),
                'applies' => (string) ($extra['applies'] ?? 'participant'),
                'per_unit_pln' => $perUnit,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function resolvedExtras(Contract $contract): array
    {
        $meta = is_array($contract->meta) ? $contract->meta : [];
        $extras = $this->normalizeExtrasInput($meta['extras'] ?? []);

        if ($extras === [] && Schema::hasColumn('contracts', 'requires_diet') && $contract->requires_diet) {
            $extras[] = [
                'key' => 'diet',
                'label' => 'Dieta specjalna',
                'pricing' => 'per_day',
                'amount_pln' => (float) ($contract->diet_daily_pln ?? 20),
                'options' => array_values(array_filter(array_map('strval', data_get($meta, 'diet_options', [])))),
                'applies' => 'participant',
            ];
        }

        return $extras;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function surchargeForExtra(Contract $contract, array $extra, int $days): float
    {
        $amount = round((float) ($extra['amount_pln'] ?? 0), 2);
        if ($amount <= 0.009) {
            return 0.0;
        }

        $pricing = (string) ($extra['pricing'] ?? 'flat');
        $key = (string) ($extra['key'] ?? '');
        $applies = (string) ($extra['applies'] ?? 'participant');

        if ($applies === 'contract') {
            return match ($pricing) {
                'per_day' => round($amount * $days, 2),
                default => $amount,
            };
        }

        $participants = $this->diet->participantsForContractPublic($contract);
        $count = 0;
        foreach ($participants as $participant) {
            if ($this->participantHasExtra($participant, $key, $extra)) {
                $count++;
            }
        }

        $unit = match ($pricing) {
            'per_day' => round($amount * $days, 2),
            'per_person' => $amount,
            default => $amount,
        };

        return round($count * $unit, 2);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function participantHasExtra(EventParticipant $participant, string $key, array $extra): bool
    {
        if ($key === 'diet') {
            return filled(trim((string) ($participant->diet ?? '')));
        }

        $selected = $participant->selected_extras ?? [];
        if (! is_array($selected)) {
            return false;
        }

        $value = $selected[$key] ?? null;

        return filled(trim((string) $value));
    }

    /**
     * @param  mixed  $raw
     * @return list<array<string, mixed>>
     */
    private function normalizeExtrasInput(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $label = trim((string) ($row['label'] ?? ''));
            $key = trim((string) ($row['key'] ?? ''));
            if ($key === '') {
                $key = Str::slug($label) ?: 'extra_'.(count($out) + 1);
            }
            $amount = round((float) ($row['amount_pln'] ?? 0), 2);
            if ($label === '' || $amount <= 0.009) {
                continue;
            }
            $options = $row['options'] ?? [];
            if (is_string($options)) {
                $options = preg_split('/[,;]+/', $options) ?: [];
            }
            $options = collect(is_array($options) ? $options : [])
                ->map(fn ($v): string => trim((string) $v))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $out[] = [
                'key' => $key,
                'label' => $label,
                'pricing' => in_array(($row['pricing'] ?? ''), ['per_day', 'per_person', 'flat'], true)
                    ? $row['pricing']
                    : 'flat',
                'amount_pln' => $amount,
                'options' => $options,
                'applies' => ($row['applies'] ?? 'participant') === 'contract' ? 'contract' : 'participant',
            ];
        }

        return array_values($out);
    }

    /**
     * @param  list<array<string, mixed>>  $extras
     * @param  array<string, mixed>  $extra
     * @return list<array<string, mixed>>
     */
    private function upsertExtra(array $extras, array $extra): array
    {
        foreach ($extras as $i => $row) {
            if (($row['key'] ?? '') === ($extra['key'] ?? '')) {
                $extras[$i] = $extra;

                return array_values($extras);
            }
        }
        $extras[] = $extra;

        return $extras;
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
