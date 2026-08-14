<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Pilot;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Controllers\Api\V1\Concerns\AuthorizesAudienceTrip;
use App\Models\Event;
use App\Models\EventSettlementCost;
use App\Models\EventSettlementDocument;
use App\Models\PilotCurrencyExchange;
use App\Services\PilotAdvanceService;
use App\Services\PilotSettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettlementController extends BaseApiController
{
    use AuthorizesAudienceTrip;

    public function show(
        Event $event,
        PilotSettlementService $settlements,
        PilotAdvanceService $advances,
    ): JsonResponse {
        $this->authorizePilotDetails($event);

        $settlement = $settlements->getOrCreateSettlement($event);
        $expenses = $settlements->getExpenseLines($settlement)->map(fn (EventSettlementCost $cost) => $this->mapExpense($cost))->values();
        $cash = $settlements->getCashReconciliation($settlement)->map(fn ($row) => [
            'currency_id' => data_get($row, 'currency_id'),
            'currency_code' => data_get($row, 'currency_code'),
            'currency_name' => data_get($row, 'currency_name'),
            'from_office' => data_get($row, 'from_office'),
            'available' => data_get($row, 'available'),
            'actual_spent' => data_get($row, 'actual_spent'),
            'returned' => data_get($row, 'returned'),
            'remaining' => data_get($row, 'remaining'),
            'needed' => data_get($row, 'needed'),
        ])->values();
        $exchanges = $event->showsPilotCurrencyExchange()
            ? $settlements->currencyExchanges($event)->map(fn (PilotCurrencyExchange $row) => $this->mapExchange($row))->values()
            : collect();

        $plannedAdvances = $advances->plannedLines($event)->map(fn ($line) => [
            'id' => $line->id ?? null,
            'amount' => (float) ($line->amount ?? 0),
            'currency_id' => (int) ($line->currency_id ?? 0),
            'currency_code' => $line->currency?->code,
            'notes' => $line->notes ?? null,
        ])->values();

        $paidAdvances = $advances->paidLines($event)->map(function ($line) {
            if (is_array($line)) {
                return [
                    'amount' => (float) ($line['amount'] ?? 0),
                    'currency_id' => (int) ($line['currency_id'] ?? 0),
                    'currency_code' => $line['currency']->code ?? ($line['currency_code'] ?? null),
                ];
            }

            return [
                'amount' => (float) ($line->amount ?? 0),
                'currency_id' => (int) ($line->currency_id ?? 0),
                'currency_code' => $line->currency?->code,
            ];
        })->values();

        return $this->success([
            'event_id' => $event->id,
            'settlement' => [
                'id' => $settlement->id,
                'status' => $settlement->status,
                'editable_by_pilot' => $settlement->isEditableByPilot(),
                'pilot_report_notes' => $settlement->pilot_report_notes,
                'reported_participant_count' => $settlement->reported_participant_count,
                'odometer_start' => $settlement->odometer_start,
                'odometer_end' => $settlement->odometer_end,
                'pilot_report_updated_at' => $settlement->pilot_report_updated_at?->toIso8601String(),
            ],
            'flags' => [
                'currency_exchange' => $event->showsPilotCurrencyExchange(),
                'bus_collections' => $event->showsPilotBusCollections(),
            ],
            'advances' => [
                'paid' => $paidAdvances,
                'planned' => $plannedAdvances,
            ],
            'cash' => $cash,
            'expenses' => $expenses,
            'exchanges' => $exchanges,
        ]);
    }

    public function updateReport(Request $request, Event $event, PilotSettlementService $settlements): JsonResponse
    {
        $this->authorizePilotDetails($event);
        $this->assertPilotCanMutate($event);

        $validated = $request->validate([
            'pilot_report_notes' => ['nullable', 'string', 'max:10000'],
            'reported_participant_count' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'odometer_start' => ['nullable', 'integer', 'min:0'],
            'odometer_end' => ['nullable', 'integer', 'min:0'],
            'submit_to_office' => ['nullable', 'boolean'],
        ]);

        $settlements->saveReport($event, $validated);

        return $this->success(
            $this->show($event, $settlements, app(PilotAdvanceService::class))->getData(true)['data'],
            ! empty($validated['submit_to_office']) ? 'Wysłano rozliczenie do biura.' : 'Zapisano raport.'
        );
    }

    public function storeExpense(Request $request, Event $event, PilotSettlementService $settlements): JsonResponse
    {
        $this->authorizePilotDetails($event);
        $this->assertPilotCanMutate($event);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'actual_amount' => ['required', 'numeric', 'min:0'],
            'actual_currency_id' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'receipt_number' => ['nullable', 'string', 'max:255'],
        ]);

        $cost = $settlements->addExpense($event, $validated);

        return $this->success($this->mapExpense($cost), 'Dodano wydatek.', 201);
    }

    public function updateExpense(
        Request $request,
        Event $event,
        EventSettlementCost $cost,
        PilotSettlementService $settlements,
    ): JsonResponse {
        $this->authorizePilotDetails($event);
        $this->assertPilotCanMutate($event);

        $validated = $request->validate([
            'actual_amount' => ['nullable', 'numeric', 'min:0'],
            'actual_currency_id' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'receipt_number' => ['nullable', 'string', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:255'],
        ]);

        $updated = $settlements->updateExpenseLine($event, $cost, $validated);

        return $this->success($this->mapExpense($updated), 'Zaktualizowano wydatek.');
    }

    public function destroyExpense(
        Event $event,
        EventSettlementCost $cost,
        PilotSettlementService $settlements,
    ): JsonResponse {
        $this->authorizePilotDetails($event);
        $this->assertPilotCanMutate($event);

        $settlements->deleteExpense($event, $cost);

        return $this->success(null, 'Usunięto wydatek.');
    }

    public function storeExchange(Request $request, Event $event, PilotSettlementService $settlements): JsonResponse
    {
        $this->authorizePilotDetails($event);
        $this->assertPilotCanMutate($event);
        abort_unless($event->showsPilotCurrencyExchange(), 403, 'Wymiana walut wyłączona dla tej wycieczki.');

        $validated = $request->validate([
            'from_currency_id' => ['required', 'integer', 'min:1'],
            'to_currency_id' => ['required', 'integer', 'min:1', 'different:from_currency_id'],
            'from_amount' => ['required', 'numeric', 'gt:0'],
            'to_amount' => ['required', 'numeric', 'gt:0'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $row = $settlements->recordCurrencyExchange($event, $validated);

        return $this->success($this->mapExchange($row), 'Zapisano wymianę.', 201);
    }

    public function updateExchange(
        Request $request,
        Event $event,
        PilotCurrencyExchange $exchange,
        PilotSettlementService $settlements,
    ): JsonResponse {
        $this->authorizePilotDetails($event);
        $this->assertPilotCanMutate($event);
        abort_unless($event->showsPilotCurrencyExchange(), 403);

        $validated = $request->validate([
            'from_currency_id' => ['sometimes', 'integer', 'min:1'],
            'to_currency_id' => ['sometimes', 'integer', 'min:1'],
            'from_amount' => ['sometimes', 'numeric', 'gt:0'],
            'to_amount' => ['sometimes', 'numeric', 'gt:0'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $row = $settlements->updateCurrencyExchange($event, $exchange, $validated);

        return $this->success($this->mapExchange($row), 'Zaktualizowano wymianę.');
    }

    public function destroyExchange(
        Event $event,
        PilotCurrencyExchange $exchange,
        PilotSettlementService $settlements,
    ): JsonResponse {
        $this->authorizePilotDetails($event);
        $this->assertPilotCanMutate($event);

        $settlements->deleteCurrencyExchange($event, $exchange);

        return $this->success(null, 'Usunięto wymianę.');
    }

    public function updateCashReturn(Request $request, Event $event, PilotSettlementService $settlements): JsonResponse
    {
        $this->authorizePilotDetails($event);
        $this->assertPilotCanMutate($event);

        $validated = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.currency_id' => ['required', 'integer', 'min:1'],
            'rows.*.returned_amount' => ['nullable', 'numeric', 'min:0'],
            'rows.*.notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $settlements->saveCashReporting($event, $validated['rows']);

        return $this->success(
            $this->show($event, $settlements, app(PilotAdvanceService::class))->getData(true)['data'],
            'Zapisano zwrot gotówki.'
        );
    }

    public function storeDocument(Request $request, Event $event, PilotSettlementService $settlements): JsonResponse
    {
        $this->authorizePilotDetails($event);
        $this->assertPilotCanMutate($event);

        $validated = $request->validate([
            'document_type' => ['required', 'string', 'max:100'],
            'document_number' => ['nullable', 'string', 'max:255'],
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'currency_id' => ['nullable', 'integer', 'min:1'],
            'linked_cost_id' => ['nullable', 'integer', 'min:1'],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:12288'],
        ]);

        $cost = null;
        if (! empty($validated['linked_cost_id'])) {
            $cost = EventSettlementCost::query()->findOrFail((int) $validated['linked_cost_id']);
        }

        $document = $settlements->uploadDocument(
            $event,
            $validated,
            $request->file('files', []),
            $cost,
        );

        return $this->success([
            'id' => $document->id,
            'document_type' => $document->document_type,
            'document_number' => $document->document_number,
        ], 'Dodano dokument.', 201);
    }

    public function destroyDocument(
        Event $event,
        EventSettlementDocument $document,
        PilotSettlementService $settlements,
    ): JsonResponse {
        $this->authorizePilotDetails($event);
        $this->assertPilotCanMutate($event);

        $settlements->deleteDocument($event, $document);

        return $this->success(null, 'Usunięto dokument.');
    }

    public function syncExpenses(Event $event, PilotSettlementService $settlements): JsonResponse
    {
        $this->authorizePilotDetails($event);
        $this->assertPilotCanMutate($event);

        $settlements->syncTripExpenses($event, null, true);

        return $this->success(
            $this->show($event, $settlements, app(PilotAdvanceService::class))->getData(true)['data'],
            'Zsynchronizowano wydatki.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function mapExpense(EventSettlementCost $cost): array
    {
        return [
            'id' => $cost->id,
            'name' => $cost->name,
            'source_type' => $cost->source_type,
            'actual_amount' => $cost->actual_amount,
            'actual_currency_id' => $cost->actual_currency_id,
            'actual_currency_code' => $cost->actualCurrency?->code,
            'notes' => $cost->notes,
            'invoice_number' => $cost->invoice_number,
            'receipt_number' => $cost->receipt_number,
            'payment_status' => $cost->payment_status,
            'approval_status' => $cost->approval_status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapExchange(PilotCurrencyExchange $row): array
    {
        return [
            'id' => $row->id,
            'from_currency_id' => $row->from_currency_id,
            'to_currency_id' => $row->to_currency_id,
            'from_amount' => $row->from_amount,
            'to_amount' => $row->to_amount,
            'exchange_rate' => $row->exchange_rate,
            'notes' => $row->notes,
            'exchanged_at' => $row->exchanged_at?->toIso8601String(),
            'from_currency_code' => $row->fromCurrency?->code,
            'to_currency_code' => $row->toCurrency?->code,
        ];
    }
}
