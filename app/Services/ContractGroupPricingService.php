<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Event;
use App\Models\EventAgreement;
use Illuminate\Support\Arr;

class ContractGroupPricingService
{
    public function resolvedUnitPrice(Contract|EventAgreement $agreement, ?Event $event = null): float
    {
        if ((float) ($agreement->unit_price ?? 0) > 0) {
            return (float) $agreement->unit_price;
        }

        $event ??= $agreement->event;

        if (! $event) {
            $participantCount = max(1, (int) ($agreement->participant_count ?? 1));
            $amountDue = (float) ($agreement->amount_due ?? 0);

            return $participantCount > 0 ? round($amountDue / $participantCount, 2) : $amountDue;
        }

        $participantCount = max(1, (int) ($agreement->participant_count ?? $event->participant_count ?? 1));

        return (float) $event->resolvedPricePerPerson($participantCount);
    }

    public function calculatedTotal(Contract|EventAgreement $agreement, ?Event $event = null): float
    {
        $participantCount = max(1, (int) ($agreement->participant_count ?? 1));

        return round($this->resolvedUnitPrice($agreement, $event) * $participantCount, 2);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function applyGroupPricingToFormData(array $data, ?Event $event = null): array
    {
        $agreementType = $data['agreement_type'] ?? $data['contract_type'] ?? Contract::TYPE_GROUP;

        if ($agreementType !== Contract::TYPE_GROUP) {
            return $data;
        }

        $participantCount = max(1, (int) ($data['participant_count'] ?? $event?->participant_count ?? 1));
        $unitPrice = (float) ($data['unit_price'] ?? 0);

        if ($unitPrice <= 0 && $event) {
            $unitPrice = (float) $event->resolvedPricePerPerson($participantCount);
        }

        if ($unitPrice <= 0 && $participantCount > 0 && isset($data['amount_due'])) {
            $unitPrice = round((float) $data['amount_due'] / $participantCount, 2);
        }

        $data['participant_count'] = $participantCount;
        $data['unit_price'] = $unitPrice > 0 ? $unitPrice : null;
        $data['payment_scheme'] = $data['payment_scheme'] ?? Contract::PAYMENT_SCHEME_LUMP_SUM;
        $data['amount_due'] = round($unitPrice * $participantCount, 2);

        return $data;
    }

    public function paymentSchemeLabel(Contract|EventAgreement $agreement): string
    {
        $schemes = $agreement instanceof Contract
            ? Contract::$paymentSchemes
            : EventAgreement::$paymentSchemes;

        return $schemes[$agreement->payment_scheme ?? Contract::PAYMENT_SCHEME_LUMP_SUM]
            ?? (string) ($agreement->payment_scheme ?? '—');
    }

    /**
     * @return array<string, mixed>
     */
    public function presentationFor(Contract|EventAgreement $agreement): array
    {
        $agreement->loadMissing('paymentSchedules');

        return [
            'is_group' => $agreement->isGroup(),
            'participant_count' => max(1, (int) ($agreement->participant_count ?? 1)),
            'unit_price' => $this->resolvedUnitPrice($agreement),
            'total_amount' => (float) ($agreement->amount_due ?? $this->calculatedTotal($agreement)),
            'payment_scheme' => $agreement->payment_scheme ?? Contract::PAYMENT_SCHEME_LUMP_SUM,
            'payment_scheme_label' => $this->paymentSchemeLabel($agreement),
            'payment_schedules' => $agreement->paymentSchedules
                ->map(fn ($schedule): array => [
                    'id' => (int) $schedule->getKey(),
                    'label' => $schedule->label,
                    'amount' => (float) $schedule->amount,
                    'paid_amount' => (float) ($schedule->paid_amount ?? 0),
                    'paid_at' => optional($schedule->paid_at)->format('d.m.Y'),
                    'due_date' => optional($schedule->due_date)->format('d.m.Y'),
                    'notes' => $schedule->notes,
                    'is_paid' => (float) ($schedule->paid_amount ?? 0) >= (float) $schedule->amount - 0.01,
                ])
                ->values()
                ->all(),
            'payment_schedule_text' => $this->formatPaymentSchedulesText($agreement),
        ];
    }

    public function formatPaymentSchedulesText(Contract|EventAgreement $agreement): string
    {
        $agreement->loadMissing('paymentSchedules');

        if ($agreement->paymentSchedules->isEmpty()) {
            return '—';
        }

        return $agreement->paymentSchedules
            ->map(function ($schedule): string {
                $label = filled($schedule->label) ? $schedule->label.': ' : '';
                $date = optional($schedule->due_date)->format('d.m.Y');
                $dateSuffix = $date ? ' (termin: '.$date.')' : '';

                return sprintf(
                    '%s%s PLN%s',
                    $label,
                    number_format((float) $schedule->amount, 2, ',', ' '),
                    $dateSuffix,
                );
            })
            ->implode("\n");
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultsFromEvent(Event $event): array
    {
        $participantCount = max(1, (int) ($event->participant_count ?? 1));
        $unitPrice = (float) $event->resolvedPricePerPerson($participantCount);

        return [
            'participant_count' => $participantCount,
            'unit_price' => $unitPrice,
            'amount_due' => round($unitPrice * $participantCount, 2),
            'payment_scheme' => Contract::PAYMENT_SCHEME_LUMP_SUM,
            'payment_schedules' => [],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function schedulesToFormState(Contract|EventAgreement $record): array
    {
        $record->loadMissing('paymentSchedules');

        return $record->paymentSchedules
            ->map(fn ($schedule): array => [
                'label' => $schedule->label,
                'amount' => (float) $schedule->amount,
                'due_date' => optional($schedule->due_date)?->toDateString(),
                'notes' => $schedule->notes,
            ])
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $schedules
     */
    public function normalizedSchedules(array $schedules): array
    {
        return collect($schedules)
            ->filter(fn (array $row): bool => (float) ($row['amount'] ?? 0) > 0)
            ->values()
            ->map(function (array $row, int $index): array {
                return [
                    'sort_order' => $index,
                    'label' => filled($row['label'] ?? null) ? (string) $row['label'] : null,
                    'amount' => round((float) $row['amount'], 2),
                    'due_date' => filled($row['due_date'] ?? null) ? $row['due_date'] : null,
                    'notes' => filled($row['notes'] ?? null) ? (string) $row['notes'] : null,
                ];
            })
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $schedules
     */
    public function schedulesSum(array $schedules): float
    {
        return round(collect($schedules)->sum(fn (array $row): float => (float) ($row['amount'] ?? 0)), 2);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    public function extractSchedulesFromFormData(array $data): array
    {
        return $this->normalizedSchedules(Arr::wrap($data['payment_schedules'] ?? []));
    }
}
