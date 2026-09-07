<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\EventAnalyticsPhase;
use App\Enums\ProfitRecognitionMode;

readonly class EventProfitSnapshotData
{
    public function __construct(
        public int $eventId,
        public ?int $settlementId,
        public ?string $eventCode,
        public ?string $eventName,
        public ?string $eventDate,
        public ?string $endDate,
        public ?string $eventStatus,
        public ?string $eventStatusLabel,
        public ?string $clientName,
        public ?int $templateId,
        public ?string $templateName,
        public EventAnalyticsPhase $phase,
        public ProfitRecognitionMode $recognitionMode,
        public ?string $settlementStatus,
        public ?string $settlementStatusLabel,
        public float $costPlannedPln,
        public float $costPaidPln,
        public float $costOutstandingPln,
        public float $costRecognizedPln,
        public float $revenueDuePln,
        public float $revenuePaidPln,
        public float $revenueRecognizedPln,
        public float $marginRecognizedPln,
        public ?float $marginRecognizedPercent,
        public float $receivablesPln,
        public float $payablesPln,
        public bool $fromOfferFallback,
        public float $offerMarkupPln = 0.0,
        public float $offerTaxPln = 0.0,
        public float $offerNetProfitPln = 0.0,
        public float $offerMarkupPercent = 0.0,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'settlement_id' => $this->settlementId,
            'event_code' => $this->eventCode,
            'event_name' => $this->eventName,
            'event_date' => $this->eventDate,
            'end_date' => $this->endDate,
            'event_status' => $this->eventStatus,
            'event_status_label' => $this->eventStatusLabel,
            'client_name' => $this->clientName,
            'template_id' => $this->templateId,
            'template_name' => $this->templateName,
            'phase' => $this->phase->value,
            'phase_label' => $this->phase->label(),
            'recognition_mode' => $this->recognitionMode->value,
            'recognition_mode_label' => $this->recognitionMode->label(),
            'settlement_status' => $this->settlementStatus,
            'status' => $this->settlementStatus,
            'status_label' => $this->settlementStatusLabel ?? 'Bez rozliczenia',
            'cost_planned_pln' => $this->costPlannedPln,
            'cost_paid_pln' => $this->costPaidPln,
            'cost_outstanding_pln' => $this->costOutstandingPln,
            'cost_recognized_pln' => $this->costRecognizedPln,
            'costs_pln' => $this->costRecognizedPln,
            'revenue_due_pln' => $this->revenueDuePln,
            'revenue_paid_pln' => $this->revenuePaidPln,
            'revenue_recognized_pln' => $this->revenueRecognizedPln,
            'revenue_pln' => $this->revenueRecognizedPln,
            'margin_recognized_pln' => $this->marginRecognizedPln,
            'margin_recognized_percent' => $this->marginRecognizedPercent,
            'net_result_pln' => $this->marginRecognizedPln,
            'receivables_pln' => $this->receivablesPln,
            'payables_pln' => $this->payablesPln,
            'from_offer_fallback' => $this->fromOfferFallback,
            'offer_markup_pln' => $this->offerMarkupPln,
            'offer_tax_pln' => $this->offerTaxPln,
            'offer_net_profit_pln' => $this->offerNetProfitPln,
            'offer_markup_percent' => $this->offerMarkupPercent,
        ];
    }
}
