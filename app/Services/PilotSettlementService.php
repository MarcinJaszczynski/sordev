<?php

namespace App\Services;

use App\Models\Currency;
use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\EventSettlementDocument;
use App\Models\PilotCashPreparation;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PilotSettlementService
{
    public function assertPilotCanAccessEvent(Event $event, ?User $user = null): void
    {
        $user ??= Auth::user();

        if (! $user || ! $user->can('view', $event)) {
            abort(403);
        }
    }

    public function getOrCreateSettlement(Event $event): EventSettlement
    {
        $this->assertPilotCanAccessEvent($event);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        if (! $settlement->pilot_id && $event->assigned_to) {
            $settlement->update(['pilot_id' => $event->assigned_to]);
        }

        if ($settlement->status === 'draft') {
            $settlement->update(['status' => 'active']);
        }

        if ($settlement->costs()->count() === 0) {
            $this->syncTripExpenses($event, $settlement);
        }

        return $settlement->fresh();
    }

    public function syncTripExpenses(Event $event, ?EventSettlement $settlement = null, bool $force = false): EventSettlement
    {
        $settlement ??= $this->getOrCreateSettlement($event);

        if (! $settlement->isEditableByPilot() && $force) {
            throw ValidationException::withMessages([
                'settlement' => 'Rozliczenie jest zamknięte — nie można odświeżyć listy wydatków.',
            ]);
        }

        if ($force || $settlement->costs()->count() === 0) {
            $settlement->importFromEvent();
            $settlement->recalculateTotals();
            $settlement->recalculatePilotCash();
            $this->syncPilotCashSpentFromCosts($settlement);
        }

        return $settlement->fresh();
    }

    /**
     * @return Collection<int, EventSettlementCost>
     */
    public function getExpenseLines(EventSettlement $settlement): Collection
    {
        return $settlement->costs()
            ->with(['plannedCurrency', 'actualCurrency'])
            ->where('paid_by', 'pilot')
            ->where('payment_status', '!=', 'cancelled')
            ->orderBy('order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, object{
     *     cash: PilotCashPreparation,
     *     currency_id: int,
     *     currency_name: string,
     *     office_provided: float,
     *     planned_expenses: float,
     *     actual_spent: float,
     *     returned: float,
     *     to_return: float,
     *     to_pay_pilot: float
     * }>
     */
    public function getCashReconciliation(EventSettlement $settlement): Collection
    {
        $settlement->recalculatePilotCash();
        $this->syncPilotCashSpentFromCosts($settlement);

        return $settlement->pilotCashPreparations()
            ->with('currency')
            ->orderBy('currency_id')
            ->get()
            ->map(function (PilotCashPreparation $cash) use ($settlement) {
                $currencyId = (int) $cash->currency_id;
                $officeProvided = (float) ($cash->provided_amount ?? $cash->approved_amount ?? 0);
                $exchangeIn = (float) $settlement->currencyExchanges()->where('to_currency_id', $currencyId)->sum('to_amount');
                $exchangeOut = (float) $settlement->currencyExchanges()->where('from_currency_id', $currencyId)->sum('from_amount');
                $officeProvided = round($officeProvided + $exchangeIn - $exchangeOut, 2);
                $plannedExpenses = $this->sumPilotCostsForCurrency($settlement, $currencyId, plannedOnly: true);
                $actualSpent = $this->sumPilotCostsForCurrency($settlement, $currencyId);
                $returned = (float) ($cash->returned_amount ?? 0);
                $remaining = round($officeProvided - $actualSpent - $returned, 2);

                return (object) [
                    'cash' => $cash,
                    'currency_id' => $currencyId,
                    'currency_name' => $cash->currency?->name ?? 'Waluta #'.$currencyId,
                    'office_provided' => $officeProvided,
                    'planned_expenses' => $plannedExpenses,
                    'actual_spent' => $actualSpent,
                    'returned' => $returned,
                    'to_return' => max(0, $remaining),
                    'to_pay_pilot' => max(0, -$remaining),
                ];
            });
    }

    /**
     * @param  array{
     *     pilot_report_notes?: string|null,
     *     reported_participant_count?: int|null,
     *     odometer_start?: int|null,
     *     odometer_end?: int|null,
     *     submit_to_office?: bool
     * }  $data
     */
    public function saveReport(Event $event, array $data): EventSettlement
    {
        $settlement = $this->getOrCreateSettlement($event);

        if (! $settlement->isEditableByPilot()) {
            throw ValidationException::withMessages([
                'settlement' => 'Rozliczenie jest zamknięte i nie można go edytować.',
            ]);
        }

        $odometerStart = isset($data['odometer_start']) && $data['odometer_start'] !== ''
            ? (int) $data['odometer_start']
            : null;
        $odometerEnd = isset($data['odometer_end']) && $data['odometer_end'] !== ''
            ? (int) $data['odometer_end']
            : null;

        if ($odometerStart !== null && $odometerEnd !== null && $odometerEnd < $odometerStart) {
            throw ValidationException::withMessages([
                'odometer_end' => 'Stan licznika końcowy nie może być mniejszy od początkowego.',
            ]);
        }

        $participantCount = isset($data['reported_participant_count']) && $data['reported_participant_count'] !== ''
            ? (int) $data['reported_participant_count']
            : null;

        if ($participantCount !== null && $participantCount < 0) {
            throw ValidationException::withMessages([
                'reported_participant_count' => 'Liczba osób nie może być ujemna.',
            ]);
        }

        $payload = [
            'pilot_report_notes' => $data['pilot_report_notes'] ?? null,
            'reported_participant_count' => $participantCount,
            'odometer_start' => $odometerStart,
            'odometer_end' => $odometerEnd,
            'pilot_report_updated_at' => now(),
        ];

        if (! empty($data['submit_to_office']) && $settlement->status !== 'closed') {
            $payload['status'] = 'pilot_settled';
        }

        $settlement->update($payload);

        if (! empty($data['submit_to_office'])) {
            $this->notifyOfficeOfUpdate($settlement);
        }

        return $settlement->fresh();
    }

    /**
     * @param  array{
     *     name: string,
     *     actual_amount: float|int|string,
     *     actual_currency_id?: int|null,
     *     notes?: string|null,
     *     payment_method?: string|null,
     *     paid_by?: string|null,
     *     invoice_number?: string|null,
     *     document_number?: string|null
     * }  $data
     */
    public function addExpense(Event $event, array $data): EventSettlementCost
    {
        $settlement = $this->getOrCreateSettlement($event);

        if (! $settlement->isEditableByPilot()) {
            throw ValidationException::withMessages([
                'settlement' => 'Rozliczenie jest zamknięte i nie można dodawać wydatków.',
            ]);
        }

        $currencyId = $data['actual_currency_id'] ?? $this->defaultPlnCurrencyId();
        $amount = (float) $data['actual_amount'];
        $rate = $this->resolveCurrencyRate($currencyId);
        $paidBy = $data['paid_by'] ?? 'pilot';

        $maxOrder = (int) $settlement->costs()->max('order');

        $cost = $settlement->costs()->create([
            'source_type' => 'manual',
            'name' => $data['name'],
            'planned_amount' => $amount,
            'planned_currency_id' => $currencyId,
            'planned_rate' => $rate,
            'planned_amount_pln' => round($amount * $rate, 2),
            'actual_amount' => $amount,
            'actual_currency_id' => $currencyId,
            'actual_rate' => $rate,
            'actual_amount_pln' => round($amount * $rate, 2),
            'paid_by' => in_array($paidBy, ['office', 'pilot'], true) ? $paidBy : 'pilot',
            'advance_type' => 'full',
            'payment_method' => $data['payment_method'] ?? 'cash',
            'payment_status' => 'paid',
            'paid_at' => now(),
            'paid_by_user_id' => Auth::id(),
            'notes' => $data['notes'] ?? null,
            'invoice_number' => filled($data['invoice_number'] ?? null) ? $data['invoice_number'] : null,
            'receipt_number' => filled($data['receipt_number'] ?? null) ? $data['receipt_number'] : null,
            'document_number' => filled($data['invoice_number'] ?? $data['document_number'] ?? null)
                ? ($data['invoice_number'] ?? $data['document_number'])
                : null,
            'order' => $maxOrder + 1,
            'approval_status' => 'pending',
        ]);

        $settlement->recalculateTotals();
        $settlement->recalculatePilotCash();
        $this->syncPilotCashSpentFromCosts($settlement);

        return $cost->fresh();
    }

    /**
     * @param  array{
     *     paid_by?: string|null,
     *     actual_amount?: float|int|string|null,
     *     actual_currency_id?: int|null,
     *     notes?: string|null,
     *     payment_method?: string|null,
     *     payment_status?: string|null
     * }  $data
     */
    public function updateExpenseLine(Event $event, EventSettlementCost $cost, array $data): EventSettlementCost
    {
        $settlement = $this->getOrCreateSettlement($event);

        if (! $settlement->isEditableByPilot()) {
            throw ValidationException::withMessages([
                'settlement' => 'Rozliczenie jest zamknięte i nie można edytować wydatków.',
            ]);
        }

        if ((int) $cost->settlement_id !== (int) $settlement->id) {
            abort(404);
        }

        $payload = [];

        if (array_key_exists('paid_by', $data) && in_array($data['paid_by'], ['office', 'pilot'], true)) {
            $payload['paid_by'] = $data['paid_by'];
        }

        if (array_key_exists('notes', $data)) {
            $payload['notes'] = $data['notes'] ?: null;
        }

        if (array_key_exists('document_number', $data)) {
            $payload['document_number'] = filled($data['document_number']) ? $data['document_number'] : null;
        }

        if (array_key_exists('invoice_number', $data)) {
            $payload['invoice_number'] = filled($data['invoice_number']) ? $data['invoice_number'] : null;
            $payload['document_number'] = $payload['invoice_number'];
        }

        if (array_key_exists('receipt_number', $data)) {
            $payload['receipt_number'] = filled($data['receipt_number']) ? $data['receipt_number'] : null;
        }

        if (array_key_exists('payment_method', $data) && $data['payment_method']) {
            $payload['payment_method'] = $data['payment_method'];
        }

        if (array_key_exists('actual_amount', $data) && $data['actual_amount'] !== '' && $data['actual_amount'] !== null) {
            $currencyId = $data['actual_currency_id'] ?? $cost->actual_currency_id ?? $cost->planned_currency_id ?? $this->defaultPlnCurrencyId();
            $amount = (float) $data['actual_amount'];
            $rate = $this->resolveCurrencyRate($currencyId);

            $payload['actual_amount'] = $amount;
            $payload['actual_currency_id'] = $currencyId;
            $payload['actual_rate'] = $rate;
            $payload['actual_amount_pln'] = round($amount * $rate, 2);
            $payload['payment_status'] = $data['payment_status'] ?? ($amount > 0 ? 'paid' : 'planned');
            $payload['paid_at'] = $amount > 0 ? now() : null;
            $payload['paid_by_user_id'] = Auth::id();
        }

        if ($payload !== []) {
            $cost->update($payload);
            $settlement->recalculateTotals();
            $settlement->recalculatePilotCash();
            $this->syncPilotCashSpentFromCosts($settlement);
        }

        return $cost->fresh();
    }

    public function deleteExpense(Event $event, EventSettlementCost $cost): void
    {
        $settlement = $this->getOrCreateSettlement($event);

        if (! $settlement->isEditableByPilot()) {
            abort(403);
        }

        if ((int) $cost->settlement_id !== (int) $settlement->id) {
            abort(404);
        }

        if ($cost->source_type !== 'manual') {
            throw ValidationException::withMessages([
                'cost' => 'Można usuwać tylko wydatki nieprzewidziane dodane ręcznie.',
            ]);
        }

        $cost->delete();
        $settlement->recalculateTotals();
        $settlement->recalculatePilotCash();
        $this->syncPilotCashSpentFromCosts($settlement);
    }

    /**
     * @param  array{
     *     from_currency_id: int,
     *     to_currency_id: int,
     *     from_amount: float|int|string,
     *     to_amount: float|int|string,
     *     exchange_rate?: float|int|string|null,
     *     exchanged_at?: \DateTimeInterface|string|null,
     *     notes?: string|null,
     * }  $data
     */
    public function recordCurrencyExchange(Event $event, array $data): \App\Models\PilotCurrencyExchange
    {
        $settlement = $this->getOrCreateSettlement($event);

        if (! $settlement->isEditableByPilot()) {
            throw ValidationException::withMessages([
                'exchange' => 'Rozliczenie jest zamknięte — nie można dodać wymiany walut.',
            ]);
        }

        $fromCurrencyId = (int) ($data['from_currency_id'] ?? 0);
        $toCurrencyId = (int) ($data['to_currency_id'] ?? 0);
        $fromAmount = round((float) str_replace(',', '.', (string) ($data['from_amount'] ?? 0)), 2);
        $toAmount = round((float) str_replace(',', '.', (string) ($data['to_amount'] ?? 0)), 2);

        if ($fromCurrencyId <= 0 || $toCurrencyId <= 0 || $fromCurrencyId === $toCurrencyId) {
            throw ValidationException::withMessages([
                'exchange' => 'Wybierz dwie różne waluty.',
            ]);
        }

        if ($fromAmount <= 0 || $toAmount <= 0) {
            throw ValidationException::withMessages([
                'exchange' => 'Podaj poprawne kwoty wymiany.',
            ]);
        }

        $rate = isset($data['exchange_rate']) && $data['exchange_rate'] !== ''
            ? round((float) $data['exchange_rate'], 5)
            : round($toAmount / $fromAmount, 5);

        $exchange = $settlement->currencyExchanges()->create([
            'from_currency_id' => $fromCurrencyId,
            'to_currency_id' => $toCurrencyId,
            'from_amount' => $fromAmount,
            'to_amount' => $toAmount,
            'exchange_rate' => $rate,
            'exchanged_at' => $data['exchanged_at'] ?? now(),
            'notes' => $data['notes'] ?? null,
            'created_by' => Auth::id(),
        ]);

        $settlement->recalculatePilotCash();

        return $exchange;
    }

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\PilotCurrencyExchange>
     */
    public function currencyExchanges(Event $event): Collection
    {
        $settlement = $this->getOrCreateSettlement($event);

        return $settlement->currencyExchanges()
            ->with(['fromCurrency', 'toCurrency'])
            ->orderByDesc('exchanged_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  array<int, array{currency_id: int, returned_amount?: float|int|string|null, notes?: string|null}>  $rows
     */
    public function saveCashReporting(Event $event, array $rows): EventSettlement
    {
        $settlement = $this->getOrCreateSettlement($event);

        if (! $settlement->isEditableByPilot()) {
            throw ValidationException::withMessages([
                'settlement' => 'Rozliczenie jest zamknięte i nie można edytować rozliczenia gotówki.',
            ]);
        }

        foreach ($rows as $row) {
            $currencyId = (int) ($row['currency_id'] ?? 0);

            if ($currencyId <= 0) {
                continue;
            }

            $cash = $settlement->pilotCashPreparations()->firstOrCreate(
                ['currency_id' => $currencyId],
                ['calculated_amount' => 0, 'status' => 'calculated']
            );

            $updates = [];

            if (array_key_exists('returned_amount', $row) && $row['returned_amount'] !== '' && $row['returned_amount'] !== null) {
                $updates['returned_amount'] = (float) $row['returned_amount'];
            }

            if (array_key_exists('notes', $row)) {
                $updates['notes'] = $row['notes'] ?: null;
            }

            if ($updates !== []) {
                $cash->update($updates);
            }
        }

        $this->syncPilotCashSpentFromCosts($settlement);

        return $settlement->fresh();
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    public function uploadDocument(Event $event, array $data, array $files = [], ?EventSettlementCost $cost = null): EventSettlementDocument
    {
        $settlement = $this->getOrCreateSettlement($event);

        if (! $settlement->isEditableByPilot()) {
            throw ValidationException::withMessages([
                'settlement' => 'Rozliczenie jest zamknięte i nie można dodawać dokumentów.',
            ]);
        }

        if ($cost && (int) $cost->settlement_id !== (int) $settlement->id) {
            abort(404);
        }

        $storedFiles = [];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $path = $file->store('event-settlement-documents', 'public');
            $storedFiles[] = $path;
        }

        $linkedCostIds = $cost ? [(int) $cost->id] : ($data['linked_cost_ids'] ?? []);

        $document = $settlement->documents()->create([
            'document_type' => $data['document_type'] ?? 'receipt',
            'document_number' => $data['document_number'] ?? null,
            'vendor_name' => $data['vendor_name'] ?? null,
            'total_amount' => $data['total_amount'] ?? null,
            'currency_id' => $data['currency_id'] ?? $this->defaultPlnCurrencyId(),
            'issue_date' => $data['issue_date'] ?? now()->toDateString(),
            'payment_method' => $data['payment_method'] ?? 'cash',
            'payer_scope' => 'pilot',
            'files' => $storedFiles,
            'linked_cost_ids' => array_values(array_filter($linkedCostIds)),
            'notes' => $data['notes'] ?? null,
            'attach_to_pilot_pdf' => true,
            'attach_to_folder_pdf' => true,
            'approval_status' => 'pending',
            'created_by' => Auth::id(),
        ]);

        $this->notifyOfficeOfUpdate($settlement);

        return $document->fresh();
    }

    public function deleteDocument(Event $event, EventSettlementDocument $document): void
    {
        $settlement = $this->getOrCreateSettlement($event);

        if (! $settlement->isEditableByPilot()) {
            abort(403);
        }

        if ((int) $document->settlement_id !== (int) $settlement->id) {
            abort(404);
        }

        if ((int) $document->created_by !== (int) Auth::id() && ! Auth::user()?->hasRole(['admin', 'super_admin'])) {
            throw ValidationException::withMessages([
                'document' => 'Można usuwać tylko własne dokumenty.',
            ]);
        }

        foreach ($document->files ?? [] as $path) {
            Storage::disk('public')->delete($path);
        }

        $document->delete();
    }

    public function syncPilotCashSpentFromCosts(EventSettlement $settlement): void
    {
        foreach ($settlement->pilotCashPreparations()->get() as $cash) {
            $spent = $this->sumPilotCostsForCurrency($settlement, (int) $cash->currency_id);

            $cash->update(['spent_amount' => $spent]);
        }
    }

    protected function sumPilotCostsForCurrency(EventSettlement $settlement, int $currencyId, bool $plannedOnly = false): float
    {
        $fallbackPlnCurrencyId = $this->defaultPlnCurrencyId();

        return (float) $settlement->costs()
            ->where('paid_by', 'pilot')
            ->where('payment_status', '!=', 'cancelled')
            ->get()
            ->filter(function (EventSettlementCost $cost) use ($currencyId, $fallbackPlnCurrencyId) {
                $costCurrencyId = (int) ($cost->actual_currency_id ?: $cost->planned_currency_id ?: $fallbackPlnCurrencyId);

                return $costCurrencyId === $currencyId;
            })
            ->sum(function (EventSettlementCost $cost) use ($plannedOnly) {
                if ($plannedOnly) {
                    return (float) ($cost->planned_amount ?? 0);
                }

                if ($cost->actual_amount !== null) {
                    return (float) $cost->actual_amount;
                }

                return (float) ($cost->planned_amount ?? 0);
            });
    }

    public function notifyOfficeOfUpdate(EventSettlement $settlement): void
    {
        $event = $settlement->event;

        if (! $event) {
            return;
        }

        $statusId = TaskStatus::query()->orderBy('order')->value('id');

        if (! $statusId) {
            return;
        }

        $title = 'Aktualizacja rozliczenia pilota: '.$event->name;

        $exists = Task::query()
            ->where('title', $title)
            ->where('created_at', '>=', now()->subDay())
            ->exists();

        if ($exists) {
            return;
        }

        Task::create([
            'title' => $title,
            'description' => 'Pilot zaktualizował raport rozliczenia imprezy #'.$event->id.'.',
            'priority' => TaskPriority::Normal->value,
            'source' => TaskSource::System->value,
            'status_id' => $statusId,
            'author_id' => Auth::id(),
            'due_date' => now()->addDay(),
            'order' => Task::where('status_id', $statusId)->max('order') + 1,
        ]);
    }

    protected function resolveCurrencyRate(?int $currencyId): float
    {
        if (! $currencyId) {
            return 1.0;
        }

        return (float) (Currency::find($currencyId)?->exchange_rate ?? 1);
    }

    protected function defaultPlnCurrencyId(): ?int
    {
        return Currency::query()
            ->where('code', 'PLN')
            ->orWhere('name', 'like', '%PLN%')
            ->orWhere('name', 'like', '%zł%')
            ->value('id');
    }
}
