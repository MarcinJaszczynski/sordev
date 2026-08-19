<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventSettlementCost;
use App\Models\Reservation;
use App\Support\CurrencyAmountDisplay;
use App\Support\EventProgramPointPricesSummary;
use App\Support\ProgramPointCostPricing;
use App\Support\Reservations\ReservationWorkflowDisplay;
use Carbon\Carbon;

final class ProgramPointListFinanceDisplay
{
    /**
     * @return array{
     *     calc: string,
     *     calcSub: string|null,
     *     planned: string,
     *     plannedSub: string|null,
     *     paid: string,
     *     paidSub: string|null,
     *     paidStatus: string,
     *     advanceHtml: string|null,
     *     paymentHint: string|null,
     *     pilotDueHint: string|null,
     *     remainingHint: string|null,
     *     remaining: string,
     *     remainingSub: string|null,
     *     documentHint: string|null,
     *     documentStatusLabel: string|null,
     *     documentFirstUrl: string|null,
     *     hasUploadedFile: bool,
     *     isSetRollup: bool,
     *     statusLabel: string|null,
     *     statusColor: string,
     *     planDiffersFromCalc: bool,
     *     paidBy: string,
     *     payerHint: string|null,
     *     totalLine: string|null,
     *     advanceLine: array{text: string, status: string}|null,
     *     remainingLine: array{text: string, tone: string}|null,
     * }
     */
    public function summarizePoint(
        EventProgramPoint $record,
        ProgramPointSettlementCostCache $costCache,
        ?int $participantCount = null,
    ): array {
        $record->loadMissing(['event', 'currency', 'templatePoint']);
        $event = $record->event;
        $participantCount = max(1, (int) ($participantCount ?? $event?->participant_count ?? 1));
        $baseCost = $costCache->baseCost((int) $record->id);
        $paymentRows = $costCache->paymentRows((int) $record->id);
        $docMeta = $costCache->documentMeta((int) $record->id);
        $plannedCurrency = $baseCost?->plannedCurrency ?? $record->currency;
        $planConvertToPln = (bool) ($baseCost?->planned_convert_to_pln ?? $record->convert_to_pln ?? false);
        $planRate = (float) ($baseCost?->planned_rate ?? ($plannedCurrency?->exchange_rate ?? 1));
        if ($planRate <= 0) {
            $planRate = 1.0;
        }

        $symbol = CurrencyAmountDisplay::symbol($plannedCurrency);
        $isForeign = $symbol !== 'PLN';

        // Kolumna „Szablon” — informacyjnie z ceny szablonu (nie z unit_price punktu / planu).
        [$calcAmountRaw, $calcCurrency, $calcConvertToPln, $calcRate] = $this->resolveTemplateAmount(
            $record,
            $event,
            $participantCount,
        );

        $plannedAmountRaw = $this->resolvePlannedAmountRaw($baseCost, $record);

        [$paidPln, $paidForeign] = $this->resolvePaidTotals($baseCost, $paymentRows, $record, $isForeign, $planRate);

        $paidAmountRaw = $isForeign
            ? ($paidForeign > 0.009 ? $paidForeign : ($planRate > 0 ? round($paidPln / $planRate, 2) : 0.0))
            : ($paidPln > 0.009 ? $paidPln : $paidForeign);

        [$calcFormatted, $calcSub] = $this->formatMoneyPair($calcAmountRaw, $calcCurrency, $calcConvertToPln, $calcRate);
        [$plannedFormatted, $plannedSub] = $this->formatMoneyPair($plannedAmountRaw, $plannedCurrency, $planConvertToPln, $planRate);
        [$paidFormatted, $paidSub] = $this->formatMoneyPair($paidAmountRaw, $plannedCurrency, $planConvertToPln, $planRate);

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
            $planConvertToPln,
            $planRate,
            $isForeign,
        );

        $remainingRaw = max(0, round($plannedAmountRaw - $paidAmountRaw, 2));
        [$remainingFormatted, $remainingSub] = $remainingRaw > 0.009
            ? $this->formatMoneyPair($remainingRaw, $plannedCurrency, $planConvertToPln, $planRate)
            : ['—', null];
        $remainingHint = $remainingRaw > 0.009
            ? 'Do dopłaty '.$this->joinMoneyPair($remainingFormatted, $remainingSub)
            : null;

        $planPaidBy = (string) ($baseCost?->paid_by ?? 'office');
        // Tylko gdy biuro już wpłaciło część — inaczej „Pilot: X” dubluje kolumnę Pozostało.
        $pilotDueHint = $this->buildPilotDueHint(
            $planPaidBy,
            $plannedAmountRaw,
            $officePaidRaw,
            $pilotPaidRaw,
            $plannedCurrency,
            $planConvertToPln,
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

        // Zaliczka na planie (bez osobnego wiersza wpłaty / bez rezerwacji) też ma być widoczna przy cenie.
        if ($paymentHint === null) {
            $paymentHint = $this->buildPlanAdvanceFallbackHint(
                $baseCost,
                $planPaidBy,
                $plannedCurrency,
                $planConvertToPln,
                $planRate,
            );
        }

        $payerHint = $planPaidBy === 'pilot' ? 'płaci pilot' : null;

        [$advanceLine, $remainingLine] = $this->buildOperationalPaymentLines(
            $advanceRows,
            $baseCost,
            $planPaidBy,
            $plannedAmountRaw,
            $paidAmountRaw,
            $plannedCurrency,
            $planConvertToPln,
            $planRate,
            $isForeign,
        );

        $reservation = $this->latestActiveReservation($record);
        if ($reservation) {
            $advanceLine = $this->overlayReservationAdvanceLine(
                $advanceLine,
                $reservation,
                $planPaidBy,
            );
        }

        $planDiffersFromCalc = $plannedAmountRaw > 0.009
            && $calcAmountRaw > 0.009
            && abs($plannedAmountRaw - $calcAmountRaw) > 0.02;

        return [
            'calc' => $calcFormatted,
            'calcSub' => $calcSub,
            'planned' => $plannedFormatted,
            'plannedSub' => $plannedSub,
            'paid' => $paidFormatted,
            'paidSub' => $paidSub,
            'paidStatus' => EventProgramPointPricesSummary::resolvePaidStatus($paidAmountRaw, $plannedAmountRaw),
            'advanceHtml' => $paymentHint,
            'paymentHint' => $paymentHint,
            'pilotDueHint' => $pilotDueHint,
            'remainingHint' => $remainingHint,
            'remaining' => $remainingFormatted,
            'remainingSub' => $remainingSub,
            'dueDateLabel' => $dueDateLabel,
            'documentHint' => $docMeta['hint'] ?? null,
            'documentStatusLabel' => $docMeta['status_label'] ?? null,
            'documentFirstUrl' => $docMeta['first_file_url'] ?? null,
            'hasUploadedFile' => (bool) ($docMeta['has_uploaded_file'] ?? false),
            'isSetRollup' => false,
            'statusLabel' => $statusLabel,
            'statusColor' => $statusColor,
            'planDiffersFromCalc' => $planDiffersFromCalc,
            'paidBy' => $planPaidBy,
            'payerHint' => $payerHint,
            'totalLine' => $plannedAmountRaw > 0.009 ? $plannedFormatted : null,
            'advanceLine' => $advanceLine,
            'remainingLine' => $remainingLine,
        ];
    }

    /**
     * @return array{0: float, 1: ?Currency, 2: bool, 3: float}
     */
    private function resolveTemplateAmount(
        EventProgramPoint $record,
        ?Event $event,
        int $participantCount,
    ): array {
        $template = $record->templatePoint;
        $currency = $template?->currency ?? $record->currency;
        $convertToPln = (bool) ($template?->convert_to_pln ?? $record->convert_to_pln ?? false);
        $rate = (float) ($currency?->exchange_rate ?? 1);
        if ($rate <= 0) {
            $rate = 1.0;
        }

        if (! $event) {
            return [0.0, $currency, $convertToPln, $rate];
        }

        $unit = (float) ($template?->unit_price ?? 0);
        // Bez szablonu / ceny szablonu: fallback informacyjny z unit_price punktu (nie z planu).
        if ($unit <= 0.009) {
            $unit = (float) ($record->unit_price ?? 0);
            $currency = $record->currency;
            $convertToPln = (bool) ($record->convert_to_pln ?? false);
            $rate = (float) ($currency?->exchange_rate ?? 1);
            if ($rate <= 0) {
                $rate = 1.0;
            }
        }

        if ($unit <= 0.009) {
            return [0.0, $currency, $convertToPln, $rate];
        }

        $includeGratis = (bool) ($template?->include_gratis_in_cost ?? $record->include_gratis_in_cost ?? false);
        $includePilot = (bool) ($template?->include_pilot_in_cost ?? $record->include_pilot_in_cost ?? false);
        $includeDriver = (bool) ($template?->include_driver_in_cost ?? $record->include_driver_in_cost ?? false);
        $headcount = ProgramPointCostPricing::costHeadcount(
            $event,
            $participantCount,
            $includeGratis,
            $includePilot,
            $includeDriver,
        );
        $groupSize = $template?->group_size ?? $record->group_size;
        $fixedQty = max(1, (int) ($record->quantity ?? 1));
        $total = ProgramPointPricingCalculator::totalPrice($unit, $headcount, $groupSize, $fixedQty);

        return [(float) $total, $currency, $convertToPln, $rate];
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
        // Tylko zaksięgowane actual — planowana zaliczka / rezerwacja ≠ wpłata.
        $paidPln = (float) $paymentRows->sum(function (EventSettlementCost $row): float {
            $raw = (float) ($row->actual_amount ?? 0);
            $pln = $row->actual_amount_pln !== null ? (float) $row->actual_amount_pln : 0.0;
            if ($raw <= 0.009 && $pln <= 0.009) {
                return 0.0;
            }

            if ($pln > 0.009) {
                return $pln;
            }

            return $raw * (float) ($row->actual_rate ?? 1);
        });

        $paidForeign = (float) $paymentRows->sum(function (EventSettlementCost $row) use ($isForeign, $planRate): float {
            $raw = (float) ($row->actual_amount ?? 0);
            if ($raw > 0.009) {
                return $raw;
            }

            $pln = $row->actual_amount_pln !== null
                ? (float) $row->actual_amount_pln
                : 0.0;

            if ($pln > 0.009 && $isForeign && $planRate > 0) {
                return round($pln / $planRate, 2);
            }

            return 0.0;
        });

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

    private function buildPlanAdvanceFallbackHint(
        ?EventSettlementCost $baseCost,
        string $planPaidBy,
        ?Currency $plannedCurrency,
        bool $convertToPln,
        float $planRate,
    ): ?string {
        if (! $baseCost) {
            return null;
        }

        $baseAdvance = (float) ($baseCost->advance_amount ?? 0);
        if ($baseAdvance <= 0.009) {
            return null;
        }

        $payerLabel = EventSettlementCost::$paidByOptions[$planPaidBy] ?? $planPaidBy;

        return 'Zaliczka '.$payerLabel.' '.$this->formatMoney($baseAdvance, $plannedCurrency, $convertToPln, $planRate);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, EventSettlementCost>|\Illuminate\Database\Eloquent\Collection<int, EventSettlementCost>  $advanceRows
     * @return array{0: array{text: string, status: string}|null, 1: array{text: string, tone: string}|null}
     */
    private function buildOperationalPaymentLines(
        $advanceRows,
        ?EventSettlementCost $baseCost,
        string $planPaidBy,
        float $plannedAmountRaw,
        float $paidAmountRaw,
        ?Currency $plannedCurrency,
        bool $convertToPln,
        float $planRate,
        bool $isForeign,
    ): array {
        $advanceRow = $advanceRows
            ->filter(fn (EventSettlementCost $row): bool => EventSettlementCost::isAdvancePaymentType($row->advance_type)
                || (float) ($row->advance_amount ?? 0) > 0.009)
            ->sortByDesc(fn (EventSettlementCost $row): string => (string) ($row->paid_at ?? ''))
            ->first();

        $advanceAmount = 0.0;
        $advancePayer = $planPaidBy;
        $advancePaidAt = null;
        $advanceDueAt = null;

        if ($advanceRow) {
            $advanceAmount = $this->sumPaymentsInPlanCurrency(collect([$advanceRow]), $isForeign, $planRate);
            if ($advanceAmount <= 0.009) {
                $advanceAmount = round((float) ($advanceRow->advance_amount ?? 0), 2);
            }
            $advancePayer = (string) ($advanceRow->paid_by ?? $planPaidBy);
            $advancePaidAt = filled($advanceRow->paid_at) || (float) ($advanceRow->actual_amount ?? 0) > 0.009
                ? $advanceRow->paid_at
                : null;
            $advanceDueAt = $advanceRow->advance_due_date;
        } else {
            $baseAdvance = (float) ($baseCost->advance_amount ?? 0);
            if ($baseAdvance > 0.009) {
                $advanceAmount = $baseAdvance;
                $advancePaidAt = filled($baseCost->paid_at) && in_array((string) $baseCost->payment_status, ['advance_paid', 'partially_paid', 'paid'], true)
                    ? $baseCost->paid_at
                    : null;
                $advanceDueAt = $baseCost->advance_due_date;
            }
        }

        $advanceLine = null;
        if ($advanceAmount > 0.009) {
            $payerLabel = EventSettlementCost::$paidByOptions[$advancePayer] ?? 'Biuro';
            $amountLabel = $this->formatMoney($advanceAmount, $plannedCurrency, $convertToPln, $planRate);
            $status = filled($advancePaidAt) ? 'paid' : 'pending';
            $when = filled($advancePaidAt)
                ? $this->formatDay($advancePaidAt)
                : (filled($advanceDueAt) ? 'do '.$this->formatDay($advanceDueAt) : null);

            $advanceLine = [
                'text' => implode(' · ', array_filter([
                    $status === 'paid' ? 'zapłacona' : 'do zapłaty',
                    $payerLabel,
                    $amountLabel,
                    $when,
                ])),
                'status' => $status,
            ];
        }

        $restRaw = max(0.0, round($plannedAmountRaw - $paidAmountRaw, 2));

        $remainingLine = null;
        if ($restRaw > 0.009) {
            $payerLabel = EventSettlementCost::$paidByOptions[$planPaidBy] ?? 'Biuro';
            $amountLabel = $this->formatMoney($restRaw, $plannedCurrency, $convertToPln, $planRate);

            $remainingLine = [
                'text' => implode(' · ', array_filter([$payerLabel, $amountLabel])),
                'tone' => $paidAmountRaw > 0.009 ? 'warn' : 'due',
            ];
        }

        return [$advanceLine, $remainingLine];
    }

    private function latestActiveReservation(EventProgramPoint $record): ?Reservation
    {
        return $record->latestVisibleReservation();
    }

    /**
     * Daty zaliczki z rezerwacji; kwota zostaje z wpłat / planu gdy już jest na linii.
     *
     * @param  array{text: string, status: string}|null  $advanceLine
     * @return array{text: string, status: string}|null
     */
    private function overlayReservationAdvanceLine(
        ?array $advanceLine,
        Reservation $reservation,
        string $planPaidBy,
    ): ?array {
        $deposit = ReservationWorkflowDisplay::depositLine($reservation);
        if ($deposit['status'] === 'not_set') {
            return $advanceLine;
        }

        // Wpłata już ma płatnika i kwotę — rezerwacja nie może nadpisać np. Biuro → Pilot.
        if ($advanceLine) {
            return $advanceLine;
        }

        $payerLabel = EventSettlementCost::$paidByOptions[$planPaidBy] ?? 'Biuro';
        $status = $deposit['status'] === 'paid' ? 'paid' : ($deposit['status'] === 'overdue' ? 'overdue' : 'pending');
        $stateLabel = match ($status) {
            'paid' => 'zapłacona',
            'overdue' => 'po terminie',
            default => 'do zapłaty',
        };

        return [
            'text' => implode(' · ', array_filter([$stateLabel, $payerLabel, $deposit['text']])),
            'status' => $status,
        ];
    }

    private function formatDay(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        $date = $value instanceof Carbon ? $value : Carbon::parse($value);

        return $date->format('d.m.Y');
    }

    private function formatMoney(float $amount, ?Currency $currency, bool $convertToPln, float $rate): string
    {
        [$main, $sub] = $this->formatMoneyPair($amount, $currency, $convertToPln, $rate);

        return $this->joinMoneyPair($main, $sub);
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function formatMoneyPair(float $amount, ?Currency $currency, bool $convertToPln, float $rate): array
    {
        if ($amount <= 0.009) {
            return ['—', null];
        }

        $symbol = CurrencyAmountDisplay::symbol($currency);
        $main = number_format($amount, 2, ',', ' ').' '.$symbol;

        if ($symbol === 'PLN' || ! $convertToPln) {
            return [$main, null];
        }

        $effectiveRate = $rate > 0 ? $rate : CurrencyAmountDisplay::rate($currency);
        $pln = round($amount * $effectiveRate, 2);

        return [$main, '≈ '.number_format($pln, 2, ',', ' ').' PLN'];
    }

    private function joinMoneyPair(string $main, ?string $sub): string
    {
        return $sub ? $main.' ('.$sub.')' : $main;
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
