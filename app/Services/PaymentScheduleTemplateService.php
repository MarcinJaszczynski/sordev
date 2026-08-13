<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Contract;
use App\Models\Event;
use App\Models\PaymentScheduleTemplate;
use App\Models\PaymentScheduleTemplateInstallment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

final class PaymentScheduleTemplateService
{
    public function __construct(
        private ContractGroupPricingService $pricing,
        private ContractPaymentScheduleService $scheduleSync,
        private InstallmentTemplateMaterializer $materializer,
        private EventPaymentInstallmentTemplateService $eventTemplateService,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return Collection<int, PaymentScheduleTemplateInstallment>
     */
    public function syncInstallments(PaymentScheduleTemplate $template, array $rows): Collection
    {
        $normalized = $this->normalizeInstallmentRows($rows);

        if ($normalized === [] && $rows !== []) {
            throw new InvalidArgumentException(
                'Brak poprawnych wierszy w szablonie — uzupełnij procent, kwotę PLN albo kwotę waluty.'
            );
        }

        return DB::transaction(function () use ($template, $normalized): Collection {
            $existing = $template->installments()->get()->keyBy('sort_order');
            $keepIds = [];

            foreach ($normalized as $row) {
                $model = $existing->get($row['sort_order']);
                $payload = array_merge($row, [
                    'payment_schedule_template_id' => $template->id,
                ]);
                unset($payload['sort_order']);

                if ($model) {
                    $model->update($payload);
                } else {
                    $model = $template->installments()->create(array_merge($payload, [
                        'sort_order' => $row['sort_order'],
                    ]));
                }

                $keepIds[] = (int) $model->id;
            }

            if ($keepIds === []) {
                $template->installments()->delete();
            } else {
                $template->installments()->whereNotIn('id', $keepIds)->delete();
            }

            return $template->installments()->get();
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function materializeForEvent(PaymentScheduleTemplate $template, Event $event, float $baseAmountPln): array
    {
        $template->loadMissing('installments');
        $startDate = $event->start_date ? Carbon::parse($event->start_date) : null;

        return $this->materializer->materialize($template->installments, $baseAmountPln, $startDate);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function materializeForContract(PaymentScheduleTemplate $template, Contract $contract, Event $event): array
    {
        $template->loadMissing('installments');
        $base = $this->eventTemplateService->resolveScheduleBaseAmount($contract, $event);
        $startDate = $event->start_date ? Carbon::parse($event->start_date) : null;

        return $this->materializer->materialize($template->installments, $base, $startDate);
    }

    public function applyToContract(PaymentScheduleTemplate $template, Contract $contract, Event $event): void
    {
        if (! Schema::hasTable('contract_payment_schedules')) {
            return;
        }

        $template->loadMissing('installments');
        if ($template->installments->isEmpty()) {
            throw new InvalidArgumentException('Szablon harmonogramu nie ma żadnych transz.');
        }

        $rows = $this->materializeForContract($template, $contract, $event);

        DB::transaction(function () use ($contract, $template, $rows): void {
            $contract->forceFill([
                'payment_scheme' => Contract::PAYMENT_SCHEME_INSTALLMENTS,
                'payment_schedule_template_id' => $template->id,
            ])->save();

            $this->scheduleSync->syncForContract(
                $contract->fresh(),
                $rows,
                Contract::PAYMENT_SCHEME_INSTALLMENTS,
            );
        });
    }

    /**
     * Kopiuje globalny szablon na imprezę (event_payment_installment_templates).
     *
     * @return Collection<int, \App\Models\EventPaymentInstallmentTemplate>
     */
    public function copyToEvent(PaymentScheduleTemplate $template, Event $event): Collection
    {
        $template->loadMissing('installments');

        $rows = $template->installments->map(fn (PaymentScheduleTemplateInstallment $row): array => [
            'label' => $row->label,
            'share_type' => $row->share_type,
            'percent' => $row->percent !== null ? (float) $row->percent : null,
            'amount_pln' => $row->amount_pln !== null ? (float) $row->amount_pln : null,
            'amount_foreign' => $row->amount_foreign !== null ? (float) $row->amount_foreign : null,
            'currency_code' => $row->currency_code,
            'paid_by' => $row->paid_by,
            'due_offset_days' => $row->due_offset_days,
            'due_offset_from_days' => $row->due_offset_from_days,
            'due_offset_to_days' => $row->due_offset_to_days,
            'notes' => $row->notes,
        ])->all();

        return $this->eventTemplateService->syncTemplate($event, $rows);
    }

    /**
     * Zastosuj globalny szablon na umowy imprezy (z opcjonalnym skopiowaniem na szablon imprezy).
     *
     * @return int liczba zaktualizowanych umów
     */
    public function applyToEventContracts(
        PaymentScheduleTemplate $template,
        Event $event,
        bool $copyToEventTemplate = true,
        ?Contract $only = null,
    ): int {
        $template->loadMissing('installments');
        if ($template->installments->isEmpty()) {
            throw new InvalidArgumentException('Szablon harmonogramu nie ma żadnych transz.');
        }

        if ($copyToEventTemplate && Schema::hasTable('event_payment_installment_templates')) {
            $this->copyToEvent($template, $event);
        }

        if (! Schema::hasTable('contracts') || ! Schema::hasTable('contract_payment_schedules')) {
            return 0;
        }

        $query = Contract::query()
            ->where('event_id', $event->id)
            ->whereNotIn('status', ['cancelled', 'template']);

        if ($only) {
            $query->whereKey($only->id);
        }

        $contracts = $query->get();
        $updated = 0;

        DB::transaction(function () use ($template, $event, $contracts, &$updated): void {
            foreach ($contracts as $contract) {
                if ($contract->payment_scheme === Contract::PAYMENT_SCHEME_INDIVIDUAL
                    && $contract->contract_type === Contract::TYPE_GROUP) {
                    continue;
                }

                $this->applyToContract($template, $contract, $event);
                $updated++;
            }
        });

        return $updated;
    }

    /**
     * Zapisz układ rat umowy jako nowy (lub zaktualizowany) wpis w bibliotece globalnej.
     */
    public function captureFromContract(
        Contract $contract,
        Event $event,
        string $name,
        ?array $appliesTo = null,
        ?PaymentScheduleTemplate $into = null,
    ): PaymentScheduleTemplate {
        $contract->loadMissing('paymentSchedules');
        $startDate = $event->start_date ? Carbon::parse($event->start_date)->startOfDay() : null;
        $base = max(0.01, $this->eventTemplateService->resolveScheduleBaseAmount($contract, $event));

        $rows = [];
        foreach ($contract->paymentSchedules->sortBy('sort_order')->values() as $schedule) {
            $foreign = (float) ($schedule->amount_foreign ?? 0);
            $amount = (float) $schedule->amount;
            $offsetTo = 0;
            $offsetFrom = 0;

            if ($startDate) {
                if ($schedule->due_to ?? $schedule->due_date) {
                    $offsetTo = (int) $startDate->diffInDays(
                        Carbon::parse($schedule->due_to ?? $schedule->due_date)->startOfDay(),
                        false,
                    );
                }
                if ($schedule->due_from) {
                    $offsetFrom = (int) $startDate->diffInDays(
                        Carbon::parse($schedule->due_from)->startOfDay(),
                        false,
                    );
                } else {
                    $offsetFrom = $offsetTo;
                }
            }

            if ($foreign > 0.009 && $amount <= 0.009) {
                $rows[] = [
                    'label' => $schedule->label,
                    'share_type' => PaymentScheduleTemplateInstallment::SHARE_FOREIGN,
                    'amount_foreign' => $foreign,
                    'currency_code' => $schedule->currency_code,
                    'paid_by' => $schedule->paid_by ?: PaymentScheduleTemplateInstallment::PAID_BY_PILOT,
                    'due_offset_days' => $offsetTo,
                    'due_offset_from_days' => $offsetFrom,
                    'due_offset_to_days' => $offsetTo,
                    'notes' => $schedule->notes,
                ];

                continue;
            }

            $rows[] = [
                'label' => $schedule->label,
                'share_type' => PaymentScheduleTemplateInstallment::SHARE_PERCENT,
                'percent' => round(($amount / $base) * 100, 4),
                'paid_by' => $schedule->paid_by ?: PaymentScheduleTemplateInstallment::PAID_BY_OFFICE,
                'due_offset_days' => $offsetTo,
                'due_offset_from_days' => $offsetFrom,
                'due_offset_to_days' => $offsetTo,
                'notes' => $schedule->notes,
            ];
        }

        if ($rows === []) {
            throw new InvalidArgumentException('Umowa nie ma rat do skopiowania do biblioteki.');
        }

        return DB::transaction(function () use ($into, $name, $appliesTo, $rows): PaymentScheduleTemplate {
            $template = $into ?? PaymentScheduleTemplate::query()->create([
                'name' => $name,
                'applies_to' => $appliesTo,
                'is_active' => true,
            ]);

            if ($into) {
                $template->forceFill([
                    'name' => $name,
                    'applies_to' => $appliesTo,
                ])->save();
            }

            $this->syncInstallments($template, $rows);

            return $template->fresh(['installments']);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function defaultInstallmentRows(): array
    {
        return [
            [
                'label' => 'Zaliczka',
                'share_type' => PaymentScheduleTemplateInstallment::SHARE_PERCENT,
                'percent' => 10,
                'due_offset_days' => -30,
                'due_offset_from_days' => -30,
                'due_offset_to_days' => -30,
                'paid_by' => PaymentScheduleTemplateInstallment::PAID_BY_OFFICE,
            ],
            [
                'label' => 'Dopłata',
                'share_type' => PaymentScheduleTemplateInstallment::SHARE_PERCENT,
                'percent' => 90,
                'due_offset_days' => -14,
                'due_offset_from_days' => -14,
                'due_offset_to_days' => -14,
                'paid_by' => PaymentScheduleTemplateInstallment::PAID_BY_OFFICE,
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function normalizeInstallmentRows(array $rows): array
    {
        return collect($rows)
            ->values()
            ->map(function (array $row, int $index): ?array {
                $shareType = (string) ($row['share_type'] ?? PaymentScheduleTemplateInstallment::SHARE_PERCENT);
                if (! array_key_exists($shareType, PaymentScheduleTemplateInstallment::$shareTypes)) {
                    $shareType = PaymentScheduleTemplateInstallment::SHARE_PERCENT;
                }

                $paidBy = (string) ($row['paid_by'] ?? PaymentScheduleTemplateInstallment::PAID_BY_OFFICE);
                if (! array_key_exists($paidBy, PaymentScheduleTemplateInstallment::$paidByOptions)) {
                    $paidBy = PaymentScheduleTemplateInstallment::PAID_BY_OFFICE;
                }

                $percent = isset($row['percent']) ? (float) $row['percent'] : null;
                $amountPln = isset($row['amount_pln']) ? (float) $row['amount_pln'] : null;
                $amountForeign = isset($row['amount_foreign']) ? (float) $row['amount_foreign'] : null;

                $hasValue = match ($shareType) {
                    PaymentScheduleTemplateInstallment::SHARE_FOREIGN => ($amountForeign ?? 0) > 0,
                    PaymentScheduleTemplateInstallment::SHARE_FIXED_PLN => ($amountPln ?? 0) > 0,
                    default => ($percent ?? 0) > 0,
                };

                if (! $hasValue) {
                    return null;
                }

                $offsetDays = (int) ($row['due_offset_days'] ?? 0);
                $offsetFrom = isset($row['due_offset_from_days']) ? (int) $row['due_offset_from_days'] : $offsetDays;
                $offsetTo = isset($row['due_offset_to_days']) ? (int) $row['due_offset_to_days'] : $offsetDays;

                return [
                    'sort_order' => $index,
                    'label' => filled($row['label'] ?? null) ? (string) $row['label'] : null,
                    'share_type' => $shareType,
                    'percent' => $shareType === PaymentScheduleTemplateInstallment::SHARE_PERCENT
                        ? round((float) $percent, 4) : null,
                    'amount_pln' => $shareType === PaymentScheduleTemplateInstallment::SHARE_FIXED_PLN
                        ? round((float) $amountPln, 2) : null,
                    'amount_foreign' => $shareType === PaymentScheduleTemplateInstallment::SHARE_FOREIGN
                        ? round((float) $amountForeign, 2) : null,
                    'currency_code' => filled($row['currency_code'] ?? null)
                        ? strtoupper((string) $row['currency_code']) : null,
                    'paid_by' => $paidBy,
                    'due_offset_days' => $offsetDays,
                    'due_offset_from_days' => $offsetFrom,
                    'due_offset_to_days' => $offsetTo,
                    'notes' => filled($row['notes'] ?? null) ? (string) $row['notes'] : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
