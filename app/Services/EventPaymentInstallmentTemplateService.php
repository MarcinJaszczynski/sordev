<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Contract;
use App\Models\Event;
use App\Models\EventPaymentInstallmentTemplate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Szablon rat na imprezie ↔ materializacja na ContractPaymentSchedule.
 *
 * Baza ceny/os.: unit_price umowy → ręczna cena imprezy → kalkulacja
 * (przez ContractGroupPricingService::resolvedUnitPrice / Event::resolvedPricePerPerson).
 */
final class EventPaymentInstallmentTemplateService
{
    public function __construct(
        private ContractGroupPricingService $pricing,
        private ContractPaymentScheduleService $scheduleSync,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return Collection<int, EventPaymentInstallmentTemplate>
     */
    public function syncTemplate(Event $event, array $rows): Collection
    {
        if (! Schema::hasTable('event_payment_installment_templates')) {
            throw new InvalidArgumentException('Brak tabeli szablonu harmonogramu wpłat.');
        }

        $normalized = $this->normalizeTemplateRows($rows);

        if ($normalized === [] && $rows !== []) {
            throw new InvalidArgumentException(
                'Brak poprawnych wierszy w szablonie — uzupełnij procent, kwotę PLN albo kwotę waluty.'
            );
        }

        return DB::transaction(function () use ($event, $normalized): Collection {
            $existing = $event->paymentInstallmentTemplates()->get()->keyBy('sort_order');
            $keepIds = [];

            foreach ($normalized as $row) {
                $model = $existing->get($row['sort_order']);
                $payload = [
                    'sort_order' => $row['sort_order'],
                    'label' => $row['label'],
                    'share_type' => $row['share_type'],
                    'percent' => $row['percent'],
                    'amount_pln' => $row['amount_pln'],
                    'amount_foreign' => $row['amount_foreign'],
                    'currency_code' => $row['currency_code'],
                    'paid_by' => $row['paid_by'],
                    'due_offset_days' => $row['due_offset_days'],
                    'due_offset_from_days' => $row['due_offset_from_days'] ?? null,
                    'due_offset_to_days' => $row['due_offset_to_days'] ?? null,
                    'notes' => $row['notes'],
                ];

                if ($model) {
                    $model->update($payload);
                } else {
                    $model = $event->paymentInstallmentTemplates()->create($payload);
                }

                $keepIds[] = (int) $model->id;
            }

            if ($keepIds === []) {
                $event->paymentInstallmentTemplates()->delete();
            } else {
                $event->paymentInstallmentTemplates()->whereNotIn('id', $keepIds)->delete();
            }

            return $event->paymentInstallmentTemplates()->get();
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function templateToFormState(Event $event): array
    {
        if (! Schema::hasTable('event_payment_installment_templates')) {
            return [];
        }

        $existing = $event->paymentInstallmentTemplates()
            ->get()
            ->map(fn (EventPaymentInstallmentTemplate $row): array => [
                'label' => $row->label,
                'share_type' => $row->share_type,
                'percent' => $row->percent !== null ? (float) $row->percent : null,
                'amount_pln' => $row->amount_pln !== null ? (float) $row->amount_pln : null,
                'amount_foreign' => $row->amount_foreign !== null ? (float) $row->amount_foreign : null,
                'currency_code' => $row->currency_code,
                'paid_by' => $row->paid_by ?: EventPaymentInstallmentTemplate::PAID_BY_OFFICE,
                'due_offset_days' => (int) $row->due_offset_days,
                'notes' => $row->notes,
            ])
            ->all();

        return $existing !== [] ? $existing : $this->defaultTemplateRows($event);
    }

    /**
     * Materializuje szablon do absolutnych wierszy schedule (per kontrakt / baza).
     *
     * @return list<array{
     *   sort_order: int,
     *   label: ?string,
     *   amount: float,
     *   amount_foreign: ?float,
     *   currency_code: ?string,
     *   paid_by: ?string,
     *   due_date: ?string,
     *   notes: ?string
     * }>
     */
    public function materializeForBase(Event $event, float $baseAmountPln, ?Carbon $startDate = null): array
    {
        $startDate ??= $event->start_date ? Carbon::parse($event->start_date) : null;
        $templates = Schema::hasTable('event_payment_installment_templates')
            ? $event->paymentInstallmentTemplates()->get()
            : collect();

        if ($templates->isEmpty()) {
            return [];
        }

        return app(InstallmentTemplateMaterializer::class)
            ->materialize($templates, $baseAmountPln, $startDate);
    }

    /**
     * Zastosuj szablon do umów imprezy (przełącza payment_scheme → installments).
     *
     * @return int liczba zaktualizowanych umów
     */
    public function applyToEventContracts(Event $event, ?Contract $only = null): int
    {
        if (! Schema::hasTable('contracts') || ! Schema::hasTable('contract_payment_schedules')) {
            return 0;
        }

        if ($event->paymentInstallmentTemplates()->count() === 0) {
            throw new InvalidArgumentException('Najpierw zapisz szablon harmonogramu na imprezie.');
        }

        $query = Contract::query()
            ->where('event_id', $event->id)
            ->whereNotIn('status', ['cancelled', 'template']);

        if ($only) {
            $query->whereKey($only->id);
        }

        $contracts = $query->get();
        $updated = 0;

        DB::transaction(function () use ($event, $contracts, &$updated): void {
            foreach ($contracts as $contract) {
                if ($contract->payment_scheme === Contract::PAYMENT_SCHEME_INDIVIDUAL
                    && $contract->contract_type === Contract::TYPE_GROUP) {
                    // Schemat „płatności indywidualne” na grupie — pomijamy (raty per osoba).
                    continue;
                }

                $base = $this->resolveScheduleBaseAmount($contract, $event);
                $rows = $this->materializeForBase($event, $base);

                $contract->forceFill([
                    'payment_scheme' => Contract::PAYMENT_SCHEME_INSTALLMENTS,
                ])->save();

                $this->scheduleSync->syncForContract(
                    $contract->fresh(),
                    $rows,
                    Contract::PAYMENT_SCHEME_INSTALLMENTS,
                );
                $updated++;
            }
        });

        return $updated;
    }

    /**
     * Zapisz układ rat umowy jako szablon imprezy (odwrotny kierunek).
     */
    public function captureFromContract(Event $event, Contract $contract): Collection
    {
        $contract->loadMissing('paymentSchedules');
        $startDate = $event->start_date ? Carbon::parse($event->start_date)->startOfDay() : null;
        $base = max(0.01, $this->resolveScheduleBaseAmount($contract, $event));

        $rows = [];
        foreach ($contract->paymentSchedules->sortBy('sort_order')->values() as $index => $schedule) {
            $foreign = (float) ($schedule->amount_foreign ?? 0);
            $amount = (float) $schedule->amount;
            $offset = 0;
            if ($startDate && $schedule->due_date) {
                $offset = (int) $startDate->diffInDays(Carbon::parse($schedule->due_date)->startOfDay(), false);
            }

            if ($foreign > 0.009 && $amount <= 0.009) {
                $rows[] = [
                    'label' => $schedule->label,
                    'share_type' => EventPaymentInstallmentTemplate::SHARE_FOREIGN,
                    'percent' => null,
                    'amount_pln' => null,
                    'amount_foreign' => $foreign,
                    'currency_code' => $schedule->currency_code,
                    'paid_by' => $schedule->paid_by ?: EventPaymentInstallmentTemplate::PAID_BY_PILOT,
                    'due_offset_days' => $offset,
                    'notes' => $schedule->notes,
                ];

                continue;
            }

            $percent = round(($amount / $base) * 100, 4);
            $rows[] = [
                'label' => $schedule->label,
                'share_type' => EventPaymentInstallmentTemplate::SHARE_PERCENT,
                'percent' => $percent,
                'amount_pln' => null,
                'amount_foreign' => $foreign > 0 ? $foreign : null,
                'currency_code' => $schedule->currency_code,
                'paid_by' => $schedule->paid_by ?: EventPaymentInstallmentTemplate::PAID_BY_OFFICE,
                'due_offset_days' => $offset,
                'notes' => $schedule->notes,
            ];
        }

        return $this->syncTemplate($event, $rows);
    }

    public function resolveScheduleBaseAmount(Contract $contract, Event $event): float
    {
        $unit = $this->pricing->resolvedUnitPrice($contract, $event);

        if ($contract->isGroup()) {
            $count = max(1, (int) ($contract->participant_count ?? $event->participant_count ?? 1));

            return round($unit * $count, 2);
        }

        // Indywidualna: amount_due = unit × slots (rodzeństwo).
        if ((float) ($contract->amount_due ?? 0) > 0) {
            return round((float) $contract->amount_due, 2);
        }

        $slots = max(1, (int) ($contract->participant_count ?? 1));

        return round($unit * $slots, 2);
    }

    /**
     * Materializacja dla uczestnika bez umowy (baza = due_amount_pln).
     *
     * @return list<array{label: ?string, amount: float, amount_foreign: ?float, currency_code: ?string, paid_by: ?string, due_date: ?\Carbon\Carbon, notes: ?string}>
     */
    public function virtualSchedulesForAmount(Event $event, float $dueAmountPln): array
    {
        $rows = $this->materializeForBase($event, $dueAmountPln);

        return array_map(static function (array $row): array {
            return [
                'label' => $row['label'],
                'amount' => (float) $row['amount'],
                'amount_foreign' => $row['amount_foreign'],
                'currency_code' => $row['currency_code'],
                'paid_by' => $row['paid_by'],
                'due_date' => filled($row['due_date'] ?? null) ? Carbon::parse($row['due_date']) : null,
                'notes' => $row['notes'],
            ];
        }, $rows);
    }

    /**
     * Sensowny start: 10% D−30, 90% D−14, opcjonalnie waluta→pilot w dniu startu.
     *
     * @return list<array<string, mixed>>
     */
    public function defaultTemplateRows(Event $event): array
    {
        $rows = [
            [
                'label' => 'Zaliczka',
                'share_type' => EventPaymentInstallmentTemplate::SHARE_PERCENT,
                'percent' => 10,
                'amount_pln' => null,
                'amount_foreign' => null,
                'currency_code' => null,
                'paid_by' => EventPaymentInstallmentTemplate::PAID_BY_OFFICE,
                'due_offset_days' => -30,
                'notes' => null,
            ],
            [
                'label' => 'Dopłata',
                'share_type' => EventPaymentInstallmentTemplate::SHARE_PERCENT,
                'percent' => 90,
                'amount_pln' => null,
                'amount_foreign' => null,
                'currency_code' => null,
                'paid_by' => EventPaymentInstallmentTemplate::PAID_BY_OFFICE,
                'due_offset_days' => -14,
                'notes' => null,
            ],
        ];

        $foreign = app(EventPriceSummaryService::class)->forEvent($event, includeNearest: false)['foreign_prices'] ?? [];
        $first = is_array($foreign) && $foreign !== [] ? $foreign[0] : null;
        if (is_array($first) && (float) ($first['price_per_person'] ?? 0) > 0) {
            $rows[] = [
                'label' => 'Waluta u pilota',
                'share_type' => EventPaymentInstallmentTemplate::SHARE_FOREIGN,
                'percent' => null,
                'amount_pln' => null,
                'amount_foreign' => round((float) $first['price_per_person'], 2),
                'currency_code' => strtoupper((string) ($first['currency'] ?? 'EUR')),
                'paid_by' => EventPaymentInstallmentTemplate::PAID_BY_PILOT,
                'due_offset_days' => 0,
                'notes' => 'Płatność w dniu rozpoczęcia imprezy',
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function normalizeTemplateRows(array $rows): array
    {
        return collect($rows)
            ->values()
            ->map(function (array $row, int $index): ?array {
                $shareType = (string) ($row['share_type'] ?? EventPaymentInstallmentTemplate::SHARE_PERCENT);
                if (! array_key_exists($shareType, EventPaymentInstallmentTemplate::$shareTypes)) {
                    $shareType = EventPaymentInstallmentTemplate::SHARE_PERCENT;
                }

                $paidBy = (string) ($row['paid_by'] ?? EventPaymentInstallmentTemplate::PAID_BY_OFFICE);
                if (! array_key_exists($paidBy, EventPaymentInstallmentTemplate::$paidByOptions)) {
                    $paidBy = EventPaymentInstallmentTemplate::PAID_BY_OFFICE;
                }

                $percent = isset($row['percent']) ? (float) $row['percent'] : null;
                $amountPln = isset($row['amount_pln']) ? (float) $row['amount_pln'] : null;
                $amountForeign = isset($row['amount_foreign']) ? (float) $row['amount_foreign'] : null;

                $hasValue = match ($shareType) {
                    EventPaymentInstallmentTemplate::SHARE_FOREIGN => ($amountForeign ?? 0) > 0,
                    EventPaymentInstallmentTemplate::SHARE_FIXED_PLN => ($amountPln ?? 0) > 0,
                    default => ($percent ?? 0) > 0,
                };

                if (! $hasValue) {
                    return null;
                }

                // paid_by: office | pilot — użytkownik wybiera (domyślnie pilot w UI szablonu).

                return [
                    'sort_order' => $index,
                    'label' => filled($row['label'] ?? null) ? (string) $row['label'] : null,
                    'share_type' => $shareType,
                    'percent' => $shareType === EventPaymentInstallmentTemplate::SHARE_PERCENT ? round((float) $percent, 4) : null,
                    'amount_pln' => $shareType === EventPaymentInstallmentTemplate::SHARE_FIXED_PLN ? round((float) $amountPln, 2) : null,
                    'amount_foreign' => $shareType === EventPaymentInstallmentTemplate::SHARE_FOREIGN
                        ? round((float) $amountForeign, 2)
                        : null,
                    'currency_code' => filled($row['currency_code'] ?? null)
                        ? strtoupper((string) $row['currency_code'])
                        : null,
                    'paid_by' => $paidBy,
                    'due_offset_days' => (int) ($row['due_offset_days'] ?? 0),
                    'due_offset_from_days' => isset($row['due_offset_from_days']) ? (int) $row['due_offset_from_days'] : null,
                    'due_offset_to_days' => isset($row['due_offset_to_days']) ? (int) $row['due_offset_to_days'] : null,
                    'notes' => filled($row['notes'] ?? null) ? (string) $row['notes'] : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
