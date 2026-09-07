<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\EventProfitSnapshotData;
use App\Enums\EventAnalyticsPhase;
use App\Enums\ProfitRecognitionMode;
use App\Models\Contract;
use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\VendorInvoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Polityka uznania zysku imprezy dla analityki portfolio.
 *
 * Fazy (daty + status anulacji):
 *  - przyszła → plan (koszt planned, przychód due)
 *  - w trakcie → blend kosztów (paid + remaining planu), przychód due
 *  - zakończona + rozliczenie otwarte → blend kosztów, przychód paid
 *  - settlement closed / anulowana → tylko paid
 *
 * Bez rozliczenia: fallback do kalkulacji oferty (baza / total).
 */
final class EventProfitRecognitionService
{
    public function __construct(
        private readonly SettlementPaymentHealthService $paymentHealth,
    ) {}

    public function forEvent(
        Event $event,
        ProfitRecognitionMode $modeOverride = ProfitRecognitionMode::Auto,
        ?Carbon $today = null,
    ): EventProfitSnapshotData {
        $today = ($today ?? now())->copy()->startOfDay();
        $phase = $this->resolvePhase($event, $today);
        $settlement = $this->resolveSettlement($event);
        $effectiveMode = $this->resolveEffectiveMode($phase, $settlement, $modeOverride);

        $offerCost = round($event->resolvedBaseTotalCost(), 2);
        $offerRevenue = round($event->resolvedFullTotalCost(), 2);

        $offerMarkup = 0.0;
        $offerTax = 0.0;
        $offerMarkupPercent = 0.0;
        try {
            $paying = max(1, (int) ($event->participant_count ?? 1));
            $gratis = $event->resolveGratisCountForParticipantCount($paying);
            $calc = EventCostCalculator::for($event)->calculate($paying, $gratis);
            $offerMarkup = round((float) ($calc['markup_pln'] ?? 0), 2);
            $offerTax = round((float) ($calc['tax_pln'] ?? 0), 2);
            $offerMarkupPercent = round((float) ($calc['markup_percent'] ?? 0), 2);
            if ($offerCost <= 0 && (float) ($calc['base_pln'] ?? 0) > 0) {
                $offerCost = round((float) $calc['base_pln'], 2);
            }
            if ($offerRevenue <= 0 && (float) ($calc['total_pln'] ?? 0) > 0) {
                $offerRevenue = round((float) $calc['total_pln'], 2);
            }
        } catch (\Throwable) {
            // fallback: same as previously — tylko baza / total z resolved*
        }

        $fromOffer = false;
        if ($settlement === null) {
            $fromOffer = true;
        } elseif ($this->settlementLacksPlanData($settlement)) {
            $fromOffer = true;
        }

        $costPlanned = $settlement !== null && ! $this->settlementLacksPlanData($settlement)
            ? round((float) ($settlement->planned_cost_pln ?? 0), 2)
            : $offerCost;
        $costPaid = $settlement !== null
            ? round((float) ($settlement->actual_cost_pln ?? 0), 2)
            : 0.0;

        $costOutstanding = 0.0;
        if ($settlement !== null && $effectiveMode === ProfitRecognitionMode::Blend && ! $fromOffer) {
            $costOutstanding = $this->outstandingPlanCostPln($settlement);
        }

        $costRecognized = match ($effectiveMode) {
            ProfitRecognitionMode::Planned => $costPlanned > 0 ? $costPlanned : $costPaid,
            ProfitRecognitionMode::Paid => $costPaid,
            ProfitRecognitionMode::Blend => round($costPaid + $costOutstanding, 2),
            ProfitRecognitionMode::Auto => $costPlanned,
        };

        // Blend bez pozycji planu (pusty settlement) — nie zaniżaj kosztów przyszłych zobowiązań.
        if ($effectiveMode === ProfitRecognitionMode::Blend && $fromOffer && $costRecognized <= 0 && $offerCost > 0) {
            $costRecognized = $offerCost;
            $costPlanned = $offerCost;
        }

        $revenueDue = $settlement !== null && ! $this->settlementLacksPlanData($settlement)
            ? round((float) ($settlement->participant_due_pln ?? 0) + $this->contractDuePln($event), 2)
            : $offerRevenue;
        $revenuePaid = $settlement !== null
            ? round((float) ($settlement->participant_paid_pln ?? 0) + $this->contractPaidPln($event), 2)
            : 0.0;

        if ($revenueDue <= 0 && $offerRevenue > 0 && in_array($effectiveMode, [
            ProfitRecognitionMode::Planned,
            ProfitRecognitionMode::Blend,
        ], true)) {
            $revenueDue = $offerRevenue;
            $fromOffer = true;
        }

        if ($costPlanned <= 0 && $offerCost > 0 && $effectiveMode === ProfitRecognitionMode::Planned) {
            $costPlanned = $offerCost;
            $costRecognized = $offerCost;
            $fromOffer = true;
        }

        $revenueRecognized = $this->resolveRecognizedRevenue(
            $phase,
            $effectiveMode,
            $revenueDue,
            $revenuePaid,
        );

        $margin = round($revenueRecognized - $costRecognized, 2);
        $marginPercent = $revenueRecognized > 0
            ? round(($margin / $revenueRecognized) * 100, 1)
            : null;

        return new EventProfitSnapshotData(
            eventId: (int) $event->id,
            settlementId: $settlement?->id,
            eventCode: $event->code,
            eventName: $event->name,
            eventDate: $event->start_date?->format('Y-m-d'),
            endDate: $event->end_date?->format('Y-m-d'),
            eventStatus: $event->status,
            eventStatusLabel: Event::getStatusOptions()[$event->status] ?? $event->status,
            clientName: filled($event->client_name) ? (string) $event->client_name : null,
            templateId: $event->event_template_id ? (int) $event->event_template_id : null,
            templateName: $event->eventTemplate?->name,
            phase: $phase,
            recognitionMode: $effectiveMode,
            settlementStatus: $settlement?->status,
            settlementStatusLabel: $settlement
                ? (EventSettlement::$statuses[$settlement->status] ?? $settlement->status)
                : null,
            costPlannedPln: $costPlanned,
            costPaidPln: $costPaid,
            costOutstandingPln: $costOutstanding,
            costRecognizedPln: $costRecognized,
            revenueDuePln: $revenueDue,
            revenuePaidPln: $revenuePaid,
            revenueRecognizedPln: $revenueRecognized,
            marginRecognizedPln: $margin,
            marginRecognizedPercent: $marginPercent,
            receivablesPln: round(max(0, $revenueDue - $revenuePaid), 2),
            payablesPln: $this->resolvePayablesPln($event),
            fromOfferFallback: $fromOffer,
            offerMarkupPln: $offerMarkup,
            offerTaxPln: $offerTax,
            offerNetProfitPln: $offerMarkup,
            offerMarkupPercent: $offerMarkupPercent,
        );
    }

    public function resolvePhase(Event $event, ?Carbon $today = null): EventAnalyticsPhase
    {
        $today = ($today ?? now())->copy()->startOfDay();

        if (in_array($event->status, [
            Event::STATUS_CANCELLED,
            Event::STATUS_PENDING_CANCELLATION,
        ], true)) {
            return EventAnalyticsPhase::Cancelled;
        }

        $start = $event->start_date?->copy()->startOfDay();
        $end = $event->end_date?->copy()->startOfDay();

        if ($end !== null) {
            if ($end->lt($today)) {
                return EventAnalyticsPhase::Completed;
            }
            if ($start !== null && $start->gt($today)) {
                return EventAnalyticsPhase::Future;
            }

            return EventAnalyticsPhase::InProgress;
        }

        if ($start === null) {
            return EventAnalyticsPhase::Future;
        }

        if ($start->gt($today)) {
            return EventAnalyticsPhase::Future;
        }

        if ($start->lt($today)) {
            return EventAnalyticsPhase::Completed;
        }

        return EventAnalyticsPhase::InProgress;
    }

    public function resolveEffectiveMode(
        EventAnalyticsPhase $phase,
        ?EventSettlement $settlement,
        ProfitRecognitionMode $override,
    ): ProfitRecognitionMode {
        if ($override !== ProfitRecognitionMode::Auto) {
            return $override;
        }

        if ($settlement?->status === 'closed') {
            return ProfitRecognitionMode::Paid;
        }

        return match ($phase) {
            EventAnalyticsPhase::Future => ProfitRecognitionMode::Planned,
            EventAnalyticsPhase::InProgress,
            EventAnalyticsPhase::Completed => ProfitRecognitionMode::Blend,
            EventAnalyticsPhase::Cancelled => ProfitRecognitionMode::Paid,
        };
    }

    private function resolveRecognizedRevenue(
        EventAnalyticsPhase $phase,
        ProfitRecognitionMode $mode,
        float $due,
        float $paid,
    ): float {
        if ($mode === ProfitRecognitionMode::Paid) {
            return $paid;
        }

        if ($mode === ProfitRecognitionMode::Planned) {
            return $due > 0 ? $due : $paid;
        }

        // Blend: przyszła / w trakcie → due (zobowiązanie klienta);
        // zakończona / anulowana → paid (zrealizowane wpłaty).
        return match ($phase) {
            EventAnalyticsPhase::Future,
            EventAnalyticsPhase::InProgress => $due > 0 ? $due : $paid,
            EventAnalyticsPhase::Completed,
            EventAnalyticsPhase::Cancelled => $paid,
        };
    }

    private function settlementLacksPlanData(EventSettlement $settlement): bool
    {
        $planned = (float) ($settlement->planned_cost_pln ?? 0);
        $due = (float) ($settlement->participant_due_pln ?? 0);
        $paidCost = (float) ($settlement->actual_cost_pln ?? 0);
        $paidRevenue = (float) ($settlement->participant_paid_pln ?? 0);

        return $planned <= 0.01
            && $due <= 0.01
            && $paidCost <= 0.01
            && $paidRevenue <= 0.01;
    }

    private function resolveSettlement(Event $event): ?EventSettlement
    {
        if ($event->relationLoaded('latestSettlement') && $event->latestSettlement) {
            return $event->latestSettlement;
        }

        if ($event->relationLoaded('activeSettlement') && $event->activeSettlement) {
            return $event->activeSettlement;
        }

        return $event->latestSettlement()->first()
            ?? $event->activeSettlement()->first();
    }

    private function outstandingPlanCostPln(EventSettlement $settlement): float
    {
        if (! $settlement->relationLoaded('costs')) {
            $settlement->load('costs');
        }

        return round((float) $this->paymentHealth
            ->evaluateSettlement($settlement)
            ->sum(fn (array $row): float => (float) ($row['remaining_pln'] ?? 0)), 2);
    }

    private function contractPaidPln(Event $event): float
    {
        if (! Schema::hasTable('contracts')) {
            return 0.0;
        }

        return round((float) Contract::query()
            ->where('event_id', $event->id)
            ->whereNotIn('status', ['cancelled', 'template'])
            ->sum('amount_paid'), 2);
    }

    private function contractDuePln(Event $event): float
    {
        if (! Schema::hasTable('contracts')) {
            return 0.0;
        }

        // amount_due to accessor na total_price — w SQL sumujemy kolumnę fizyczną.
        return round((float) Contract::query()
            ->where('event_id', $event->id)
            ->whereNotIn('status', ['cancelled', 'template'])
            ->sum('total_price'), 2);
    }

    private function resolvePayablesPln(Event $event): float
    {
        if (! Schema::hasTable('vendor_invoices')) {
            return 0.0;
        }

        return round((float) VendorInvoice::query()
            ->where('event_id', $event->id)
            ->where('payment_status', 'due')
            ->where('approval_status', 'approved')
            ->sum('gross_amount'), 2);
    }
}
