<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EventPaymentInstallmentTemplate;
use App\Models\PaymentScheduleTemplateInstallment;
use Illuminate\Support\Carbon;

/**
 * Materializacja względnych rat (procent / kwota / waluta) na absolutne wiersze harmonogramu.
 */
final class InstallmentTemplateMaterializer
{
    /**
     * @param  iterable<int, EventPaymentInstallmentTemplate|PaymentScheduleTemplateInstallment|array<string, mixed>>  $templates
     * @return list<array{
     *   sort_order: int,
     *   label: ?string,
     *   amount: float,
     *   amount_foreign: ?float,
     *   currency_code: ?string,
     *   paid_by: ?string,
     *   due_date: ?string,
     *   due_from: ?string,
     *   due_to: ?string,
     *   notes: ?string
     * }>
     */
    public function materialize(iterable $templates, float $baseAmountPln, ?Carbon $startDate = null): array
    {
        $rows = [];
        $index = 0;

        foreach ($templates as $template) {
            $normalized = $this->normalizeRow($template);
            if ($normalized === null) {
                continue;
            }

            [$dueFrom, $dueTo, $dueDate] = $this->resolveDueDates(
                $startDate,
                $normalized['due_offset_from'],
                $normalized['due_offset_to'],
            );

            if ($normalized['share_type'] === EventPaymentInstallmentTemplate::SHARE_FOREIGN) {
                $rows[] = [
                    'sort_order' => $index,
                    'label' => $normalized['label'] ?: 'Waluta (pilot)',
                    'amount' => 0.0,
                    'amount_foreign' => round((float) ($normalized['amount_foreign'] ?? 0), 2),
                    'currency_code' => $normalized['currency_code'],
                    'paid_by' => $normalized['paid_by'] ?: EventPaymentInstallmentTemplate::PAID_BY_PILOT,
                    'due_date' => $dueDate,
                    'due_from' => $dueFrom,
                    'due_to' => $dueTo,
                    'notes' => $normalized['notes'],
                ];
                $index++;

                continue;
            }

            $amount = match ($normalized['share_type']) {
                EventPaymentInstallmentTemplate::SHARE_FIXED_PLN => round((float) ($normalized['amount_pln'] ?? 0), 2),
                default => round($baseAmountPln * ((float) ($normalized['percent'] ?? 0) / 100), 2),
            };

            $rows[] = [
                'sort_order' => $index,
                'label' => $normalized['label'],
                'amount' => $amount,
                'amount_foreign' => null,
                'currency_code' => null,
                'paid_by' => $normalized['paid_by'] ?: EventPaymentInstallmentTemplate::PAID_BY_OFFICE,
                'due_date' => $dueDate,
                'due_from' => $dueFrom,
                'due_to' => $dueTo,
                'notes' => $normalized['notes'],
            ];
            $index++;
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeRow(mixed $template): ?array
    {
        if ($template instanceof EventPaymentInstallmentTemplate || $template instanceof PaymentScheduleTemplateInstallment) {
            $shareType = (string) $template->share_type;
            $hasValue = match ($shareType) {
                EventPaymentInstallmentTemplate::SHARE_FOREIGN => (float) ($template->amount_foreign ?? 0) > 0,
                EventPaymentInstallmentTemplate::SHARE_FIXED_PLN => (float) ($template->amount_pln ?? 0) > 0,
                default => (float) ($template->percent ?? 0) > 0,
            };

            if (! $hasValue) {
                return null;
            }

            return [
                'label' => $template->label,
                'share_type' => $shareType,
                'percent' => $template->percent !== null ? (float) $template->percent : null,
                'amount_pln' => $template->amount_pln !== null ? (float) $template->amount_pln : null,
                'amount_foreign' => $template->amount_foreign !== null ? (float) $template->amount_foreign : null,
                'currency_code' => filled($template->currency_code) ? strtoupper((string) $template->currency_code) : null,
                'paid_by' => (string) ($template->paid_by ?: EventPaymentInstallmentTemplate::PAID_BY_OFFICE),
                'due_offset_from' => $template instanceof PaymentScheduleTemplateInstallment
                    ? $template->resolvedDueOffsetFrom()
                    : (int) ($template->due_offset_from_days ?? $template->due_offset_days ?? 0),
                'due_offset_to' => $template instanceof PaymentScheduleTemplateInstallment
                    ? $template->resolvedDueOffsetTo()
                    : (int) ($template->due_offset_to_days ?? $template->due_offset_days ?? 0),
                'notes' => $template->notes,
            ];
        }

        if (! is_array($template)) {
            return null;
        }

        return app(EventPaymentInstallmentTemplateService::class)
            ->normalizeTemplateRows([$template])[0] ?? null;
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: ?string}
     */
    private function resolveDueDates(?Carbon $startDate, int $offsetFrom, int $offsetTo): array
    {
        if (! $startDate) {
            return [null, null, null];
        }

        $from = $startDate->copy()->addDays($offsetFrom)->toDateString();
        $to = $startDate->copy()->addDays($offsetTo)->toDateString();

        return [$from, $to, $to];
    }
}
