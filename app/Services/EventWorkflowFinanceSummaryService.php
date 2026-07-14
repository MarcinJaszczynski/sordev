<?php

namespace App\Services;

use App\Filament\Resources\EventResource;
use App\Models\Event;
use App\Models\EventSettlement;
use App\Support\MoneyFormatter;
use Illuminate\Support\Facades\Schema;

final class EventWorkflowFinanceSummaryService
{
    public function __construct(
        private SettlementPayerBreakdownService $payerBreakdown,
    ) {}

    /**
     * @return array{
     *     planned: string,
     *     paid: string,
     *     remaining: string,
     *     pilot_cash: list<array{label: string, value: string}>,
     *     settlement_url: string
     * }|null
     */
    public function forEvent(Event $event): ?array
    {
        if (! Schema::hasTable('event_settlements')) {
            return null;
        }

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $total = $this->payerBreakdown->forSettlement($settlement)['total'] ?? [];

        return [
            'planned' => (string) ($total['planned_label'] ?? MoneyFormatter::format(0, 'PLN')),
            'paid' => (string) ($total['paid_label'] ?? MoneyFormatter::format(0, 'PLN')),
            'remaining' => (string) ($total['remaining_label'] ?? MoneyFormatter::format(0, 'PLN')),
            'pilot_cash' => $this->pilotCashLines($settlement),
            'settlement_url' => EventResource::getUrl('settlement-summary', ['record' => $event->getKey()]),
        ];
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function pilotCashLines(EventSettlement $settlement): array
    {
        if (! Schema::hasTable('pilot_cash_preparations')) {
            return [];
        }

        $settlement->loadMissing('pilotCashPreparations.currency');

        return $settlement->pilotCashPreparations
            ->map(function ($cash): ?array {
                $amount = (float) ($cash->provided_amount ?? $cash->approved_amount ?? $cash->calculated_amount ?? 0);

                if ($amount <= 0) {
                    return null;
                }

                $currencyCode = strtoupper((string) ($cash->currency?->code ?? $cash->currency?->symbol ?? 'PLN'));

                return [
                    'label' => $currencyCode,
                    'value' => MoneyFormatter::format($amount, $currencyCode),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
