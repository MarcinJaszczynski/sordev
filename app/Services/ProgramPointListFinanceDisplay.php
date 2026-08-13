<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\EventProgramPoint;
use App\Models\EventSettlementCost;
use App\Support\CurrencyAmountDisplay;
use App\Support\EventProgramPointPricesSummary;

final class ProgramPointListFinanceDisplay
{
    /**
     * @return array{
     *     calc: string,
     *     planned: string,
     *     paid: string,
     *     paidStatus: string,
     *     advanceHtml: string|null,
     *     paymentHint: string|null,
     *     pilotDueHint: string|null,
     *     remainingHint: string|null,
     *     remaining: string,
     *     documentHint: string|null,
     *     documentStatusLabel: string|null,
     *     hasUploadedFile: bool,
     *     isSetRollup: bool,
     *     statusLabel: string|null,
     *     statusColor: string,
     *     planDiffersFromCalc: bool,
     * }
     */
    public function summarizePoint(
        EventProgramPoint $record,
        ProgramPointSettlementCostCache $costCache,
        ?int $participantCount = null,
    ): array {
        $participantCount = max(1, (int) ($participantCount ?? $record->event?->participant_count ?? 1));
        $baseCost = $costCache->baseCost((int) $record->id);
        $paymentRows = $costCache->paymentRows((int) $record->id);
        $docMeta = $costCache->documentMeta((int) $record->id);
        $plannedCurrency = $baseCost?->plannedCurrency ?? $record->currency;
        $convertToPln = (bool) ($baseCost?->planned_convert_to_pln ?? $record->convert_to_pln ?? true);
        $planRate = (float) ($baseCost?->planned_rate ?? ($plannedCurrency?->exchange_rate ?? 1));
        if ($planRate <= 0) {
            $planRate = 1.0;
        }

        $symbol = CurrencyAmountDisplay::symbol($plannedCurrency);
        $isForeign = $symbol !== 'PLN';

        $calcAmountRaw = (float) $record->resolveCalculationTotal($participantCount);
        $plannedAmountRaw = $this->resolvePlannedAmountRaw($baseCost, $record);

        [$paidPln, $paidForeign] = $this->resolvePaidTotals($baseCost, $paymentRows, $record, $isForeign, $planRate);

        $paidAmountRaw = $isForeign
            ? ($paidForeign > 0.009 ? $paidForeign : ($planRate > 0 ? round($paidPln / $planRate, 2) : 0.0))
            : ($paidPln > 0.009 ? $paidPln : $paidForeign);

        $calcFormatted = $this->formatMoney($calcAmountRaw, $plannedCurrency, $convertToPln, $planRate);
        $plannedFormatted = $this->formatMoney($plannedAmountRaw, $plannedCurrency, $convertToPln, $planRate);
        $paidFormatted = $this->formatMoney($paidAmountRaw, $plannedCurrency, $convertToPln, $planRate);

        $advanceRows = $paymentRows->filter(fn (EventSettlementCost $row): bool => ($row->advance_type ?? '') === 'advance'
            || in_array((string) $row->payment_status, ['advance_paid', 'advance_required'], true)
            || (float) ($row->advance_amount ?? 0) > 0);

        $officePaidRaw = $this->sumPaymentsInPlanCurrency(
            $paymentRows->filter(fn (EventSettlementCost $row): bool => ($row->paid_by ?? 'office') === 'office'),
            $isForeign,
            $planRate,
        );
        $pilotPaidRaw = $this->sumPaymentsInPlanCurrency(
            $paymentRows->filter(fn (EventSettlementCost $row): bool => ($row->paid_by ?? '') === 'pilot'),
            $isForeign,
            $planRate,
        );

        $paymentHint = $this->buildPaymentHint(
            $paymentRows,
            $advanceRows,
            $plannedCurrency,
            $convertToPln,
            $planRate,
            $isForeign,
        );

        $remainingRaw = max(0, round($plannedAmountRaw - $paidAmountRaw, 2));
        $remainingFormatted = $remainingRaw > 0.009
            ? $this->formatMoney($remainingRaw, $plannedCurrency, $convertToPln, $planRate)
            : '—';
        $remainingHint = $remainingRaw > 0.009
            ? 'Do dopłaty '.$remainingFormatted
            : null;

        $planPaidBy = (string) ($baseCost?->paid_by ?? 'office');
        // Tylko gdy biuro już wpłaciło część — inaczej „Pilot: X” dubluje kolumnę Pozostało.
        $pilotDueHint = $this->buildPilotDueHint(
            $planPaidBy,
            $plannedAmountRaw,
            $officePaidRaw,
            $pilotPaidRaw,
            $plannedCurrency,
            $convertToPln,
            $planRate,
        );

        $dueDate = $advanceRows
            ->filter(fn (EventSettlementCost $row): bool => filled($row->advance_due_date))
            ->sortBy('advance_due_date')
            ->first()?->advance_due_date
            ?? $baseCost?->advance_due_date;
        $dueDateLabel = $dueDate?->format('d.m.Y');

        $statusRaw = $baseCost?->payment_status;
        $statusLabel = $statusRaw
            ? (EventSettlementCost::$paymentStatuses[$statusRaw] ?? $statusRaw)
            : null;
        $statusColor = match ($statusRaw) {
            'paid' => 'success',
            'partially_paid', 'advance_paid' => 'warning',
            'overdue', 'review' => 'danger',
            default => 'gray',
        };

        $planDiffersFromCalc = $plannedAmountRaw > 0.009
            && $calcAmountRaw > 0.009
            && abs($plannedAmountRaw - $calcAmountRaw) > 0.02;

        return [
            'calc' => $calcFormatted,
            'planned' => $plannedFormatted,
            'paid' => $paidFormatted,
            'paidStatus' => EventProgramPointPricesSummary::resolvePaidStatus($paidAmountRaw, $plannedAmountRaw),
            'advanceHtml' => $paymentHint,
            'paymentHint' => $paymentHint,
            'pilotDueHint' => $pilotDueHint,
            'remainingHint' => $remainingHint,
            'remaining' => $remainingFormatted,
            'dueDateLabel' => $dueDateLabel,
            'documentHint' => $docMeta['hint'] ?? null,
            'documentStatusLabel' => $docMeta['status_label'] ?? null,
            'hasUploadedFile' => (bool) ($docMeta['has_uploaded_file'] ?? false),
            'isSetRollup' => false,
            'statusLabel' => $statusLabel,
            'statusColor' => $statusColor,
            'planDiffersFromCalc' => $planDiffersFromCalc,
            'paidBy' => $planPaidBy,
        ];
    }

    private function resolvePlannedAmountRaw(?EventSettlementCost $baseCost, EventProgramPoint $record): float
    {
        $fromCost = $baseCost ? (float) ($baseCost->planned_amount ?? 0) : 0.0;
        if ($fromCost > 0.009) {
            return $fromCost;
        }

        $fromPoint = (float) ($record->planned_price ?? 0);
        if ($fromPoint > 0.009) {
            return $fromPoint;
        }

        return $fromCost;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, EventSettlementCost>|\Illuminate\Database\Eloquent\Collection<int, EventSettlementCost>  $paymentRows
     * @return array{0: float, 1: float}
     */
    private function resolvePaidTotals(
        ?EventSettlementCost $baseCost,
        $paymentRows,
        EventProgramPoint $record,
        bool $isForeign,
        float $planRate,
    ): array {
        $paidPln = (float) $paymentRows->sum(function (EventSettlementCost $row): float {
            if ($row->actual_amount_pln !== null && (float) $row->actual_amount_pln > 0) {
                return (float) $row->actual_amount_pln;
            }

            $raw = (float) ($row->actual_amount ?? 0);
            if ($raw > 0) {
                return $raw * (float) ($row->actual_rate ?? 1);
            }

            $advancePln = (float) ($row->advance_amount ?? 0);
            if ($advancePln > 0 && ! CurrencyAmountDisplay::isForeignCurrency($row->actual_currency_id ?? $row->planned_currency_id)) {
                return $advancePln;
            }

            return 0.0;
        });

        $paidForeign = (float) $paymentRows->sum(function (EventSettlementCost $row) use ($isForeign, $planRate): float {
            $raw = (float) ($row->actual_amount ?? 0);
            if ($raw > 0.009) {
                return $raw;
            }

            $advance = (float) ($row->advance_amount ?? 0);
            if ($advance > 0.009) {
                return $advance;
            }

            $pln = $row->actual_amount_pln !== null
                ? (float) $row->actual_amount_pln
                : 0.0;

            if ($pln > 0.009 && $isForeign && $planRate > 0) {
                return round($pln / $planRate, 2);
            }

            return 0.0;
        });

        if ($baseCost) {
            if ($paidPln <= 0.009) {
                $paidPln = (float) ($baseCost->actual_amount_pln ?? 0);
            }
            if ($paidForeign <= 0.009) {
                $paidForeign = (float) ($baseCost->actual_amount ?? 0);
            }
            $baseAdvance = (float) ($baseCost->advance_amount ?? 0);
            if ($baseAdvance > 0.009) {
                if ($isForeign) {
                    $paidForeign = max($paidForeign, $baseAdvance);
                } else {
                    $paidPln = max($paidPln, $baseAdvance);
                }
            }
        }

        if ($paidPln <= 0.009 && $paidForeign <= 0.009) {
            $legacyPaid = (float) ($record->paid_price ?? 0);
            if ($legacyPaid > 0.009) {
                if ($isForeign) {
                    $paidForeign = $legacyPaid;
                } else {
                    $paidPln = $legacyPaid;
                }
            }
        }

        return [$paidPln, $paidForeign];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, EventSettlementCost>|\Illuminate\Database\Eloquent\Collection<int, EventSettlementCost>  $paymentRows
     * @param  \Illuminate\Support\Collection<int, EventSettlementCost>|\Illuminate\Database\Eloquent\Collection<int, EventSettlementCost>  $advanceRows
     */
    private function buildPaymentHint(
        $paymentRows,
        $advanceRows,
        ?Currency $plannedCurrency,
        bool $convertToPln,
        float $planRate,
        bool $isForeign,
    ): ?string {
        if ($paymentRows->isEmpty() && $advanceRows->isEmpty()) {
            return null;
        }

        $parts = [];
        $advancesByPayer = $advanceRows->groupBy(fn (EventSettlementCost $row): string => (string) ($row->paid_by ?? 'office'));

        foreach ($advancesByPayer as $payer => $rows) {
            $sum = $this->sumPaymentsInPlanCurrency($rows, $isForeign, $planRate);
            if ($sum <= 0.009) {
                $sum = round((float) $rows->sum(fn (EventSettlementCost $row) => (float) ($row->advance_amount ?? 0)), 2);
            }
            if ($sum <= 0.009) {
                continue;
            }

            $payerLabel = EventSettlementCost::$paidByOptions[$payer] ?? $payer;
            $parts[] = ($rows->count() === 1 ? 'Zaliczka' : $rows->count().' zaliczki')
                .' '.$payerLabel.' '.$this->formatMoney($sum, $plannedCurrency, $convertToPln, $planRate);
        }

        $dueDate = $advanceRows
            ->filter(fn (EventSettlementCost $row): bool => filled($row->advance_due_date))
            ->sortBy('advance_due_date')
            ->first()?->advance_due_date;

        if ($dueDate && $parts !== []) {
            $parts[0] .= ' · do '.$dueDate->format('d.m.Y');
        }

        $nonAdvanceCount = $paymentRows->reject(fn (EventSettlementCost $row): bool => ($row->advance_type ?? '') === 'advance'
            || (float) ($row->advance_amount ?? 0) > 0)->count();

        if ($nonAdvanceCount > 0) {
            $parts[] = $nonAdvanceCount === 1 ? '1 dopłata' : $nonAdvanceCount.' dopłaty';
        }

        return $parts !== [] ? implode(' · ', $parts) : null;
    }

    private function buildPilotDueHint(
        string $planPaidBy,
        float $plannedAmountRaw,
        float $officePaidRaw,
        float $pilotPaidRaw,
        ?Currency $plannedCurrency,
        bool $convertToPln,
        float $planRate,
    ): ?string {
        if ($planPaidBy !== 'pilot' || $plannedAmountRaw <= 0.009) {
            return null;
        }

        $pilotDue = max(0.0, round($plannedAmountRaw - $officePaidRaw, 2));
        $pilotRemaining = max(0.0, round($pilotDue - $pilotPaidRaw, 2));

        // Bez wpłaty biura „do zapłaty = pozostało” — nie dublujemy w drugiej kolumnie.
        if ($officePaidRaw <= 0.009) {
            return null;
        }

        $hint = 'Do pilota '.$this->formatMoney($pilotDue, $plannedCurrency, $convertToPln, $planRate)
            .' (biuro '.$this->formatMoney($officePaidRaw, $plannedCurrency, $convertToPln, $planRate).')';
        if ($pilotPaidRaw > 0.009 && $pilotRemaining > 0.009) {
            $hint .= ' · zostało '.$this->formatMoney($pilotRemaining, $plannedCurrency, $convertToPln, $planRate);
        } elseif ($pilotDue <= 0.009 || $pilotRemaining <= 0.009) {
            $hint .= ' · pokryte';
        }

        return $hint;
    }

    private function formatMoney(float $amount, ?Currency $currency, bool $convertToPln, float $rate): string
    {
        unset($convertToPln);

        $symbol = CurrencyAmountDisplay::symbol($currency);
        if ($symbol !== 'PLN') {
            return CurrencyAmountDisplay::formatIndicative($amount, $currency, $rate);
        }

        return CurrencyAmountDisplay::format($amount, $currency, convertToPln: false);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, EventSettlementCost>|\Illuminate\Database\Eloquent\Collection<int, EventSettlementCost>  $rows
     */
    private function sumPaymentsInPlanCurrency($rows, bool $isForeign, float $planRate): float
    {
        return round((float) $rows->sum(function (EventSettlementCost $row) use ($isForeign, $planRate): float {
            $raw = (float) ($row->actual_amount ?? 0);
            $pln = $row->actual_amount_pln !== null
                ? (float) $row->actual_amount_pln
                : $raw * (float) ($row->actual_rate ?? $planRate);

            if ($raw <= 0.009) {
                $advance = (float) ($row->advance_amount ?? 0);
                if ($advance > 0.009) {
                    $raw = $advance;
                }
            }

            if ($isForeign) {
                if ($raw > 0.009) {
                    return $raw;
                }

                return $planRate > 0 ? round($pln / $planRate, 2) : 0.0;
            }

            return $pln > 0.009 ? $pln : $raw;
        }), 2);
    }
}
