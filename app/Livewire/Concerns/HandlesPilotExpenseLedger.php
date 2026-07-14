<?php

namespace App\Livewire\Concerns;

use App\Models\Currency;
use App\Models\EventSettlementCost;
use App\Models\EventSettlementDocument;
use App\Services\PilotSettlementService;
use Filament\Notifications\Notification;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

trait HandlesPilotExpenseLedger
{
    public ?int $editingCostId = null;

    public string $editCostPaidBy = 'office';

    public string $editCostActualAmount = '';

    public ?int $editCostCurrencyId = null;

    public string $editCostNotes = '';

    public string $editCostInvoiceNumber = '';

    public string $editCostReceiptNumber = '';

    public string $editCostPaymentMethod = 'cash';

    public ?int $documentCostId = null;

    /** @var array<int|string, TemporaryUploadedFile|array<int, TemporaryUploadedFile>> */
    public array $costDocumentFiles = [];

    /** @var array<int|string, string> */
    public array $cashReturned = [];

    public function mountExpenseLedgerState(): void
    {
        $this->editCostCurrencyId = Currency::query()->where('code', 'PLN')->value('id');
        $this->loadCashReportingFields();
    }

    public function loadCashReportingFields(): void
    {
        $settlement = app(PilotSettlementService::class)->getOrCreateSettlement($this->event);
        $rows = app(PilotSettlementService::class)->getCashReconciliation($settlement);

        foreach ($rows as $row) {
            $this->cashReturned[$row->currency_id] = $row->returned > 0 ? (string) $row->returned : '';
        }
    }

    public function getExpenseLinesProperty()
    {
        $settlement = app(PilotSettlementService::class)->getOrCreateSettlement($this->event);

        return app(PilotSettlementService::class)->getExpenseLines($settlement);
    }

    public function getCashReconciliationProperty()
    {
        $settlement = app(PilotSettlementService::class)->getOrCreateSettlement($this->event);

        return app(PilotSettlementService::class)->getCashReconciliation($settlement);
    }

    public function getCurrencyOptions(): array
    {
        return Currency::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function refreshTripExpenses(): void
    {
        app(PilotSettlementService::class)->syncTripExpenses($this->event, force: true);
        $this->loadCashReportingFields();

        $this->notifyLedger('Lista wydatków odświeżona z wycieczki');
    }

    public function startEditCost(int $costId): void
    {
        $cost = EventSettlementCost::findOrFail($costId);
        $this->editingCostId = $cost->id;
        $this->editCostPaidBy = 'pilot';
        $this->editCostActualAmount = $cost->actual_amount !== null
            ? (string) $cost->actual_amount
            : (string) ($cost->planned_amount ?? '');
        $this->editCostCurrencyId = $cost->actual_currency_id
            ?? $cost->planned_currency_id
            ?? $this->editCostCurrencyId
            ?? Currency::query()->where('code', 'PLN')->value('id');
        $this->editCostNotes = (string) ($cost->notes ?? '');
        $this->editCostInvoiceNumber = (string) ($cost->invoice_number ?? $cost->document_number ?? '');
        $this->editCostReceiptNumber = (string) ($cost->receipt_number ?? '');
        $this->editCostPaymentMethod = filled($cost->payment_method) ? $cost->payment_method : 'cash';
    }

    public function cancelEditCost(): void
    {
        $this->editingCostId = null;
        $this->reset([
            'editCostPaidBy',
            'editCostActualAmount',
            'editCostNotes',
            'editCostInvoiceNumber',
            'editCostReceiptNumber',
            'editCostPaymentMethod',
        ]);
    }

    public function saveCost(): void
    {
        if (! $this->editingCostId) {
            return;
        }

        $this->editCostActualAmount = str_replace([' ', ','], ['', '.'], trim($this->editCostActualAmount));

        if (! filled($this->editCostPaymentMethod)) {
            $this->editCostPaymentMethod = 'cash';
        }

        if (! $this->editCostCurrencyId) {
            $this->editCostCurrencyId = Currency::query()->where('code', 'PLN')->value('id');
        }

        $this->validate([
            'editCostActualAmount' => 'required|numeric|min:0',
            'editCostCurrencyId' => 'nullable|exists:currencies,id',
            'editCostPaymentMethod' => 'required|in:cash,transfer,card,other',
            'editCostNotes' => 'nullable|string|max:2000',
            'editCostInvoiceNumber' => 'nullable|string|max:255',
            'editCostReceiptNumber' => 'nullable|string|max:255',
        ], [
            'editCostActualAmount.required' => 'Podaj kwotę faktyczną.',
            'editCostActualAmount.numeric' => 'Kwota musi być liczbą (użyj kropki lub przecinka).',
        ]);

        $cost = EventSettlementCost::findOrFail($this->editingCostId);

        app(PilotSettlementService::class)->updateExpenseLine($this->event, $cost, [
            'paid_by' => 'pilot',
            'actual_amount' => $this->editCostActualAmount,
            'actual_currency_id' => $this->editCostCurrencyId,
            'notes' => $this->editCostNotes,
            'invoice_number' => $this->editCostInvoiceNumber,
            'receipt_number' => $this->editCostReceiptNumber,
            'payment_method' => $this->editCostPaymentMethod,
        ]);

        $this->cancelEditCost();
        $this->loadCashReportingFields();
        $this->notifyLedger('Wydatek zaktualizowany');
    }

    public function saveCashReporting(): void
    {
        $settlement = app(PilotSettlementService::class)->getOrCreateSettlement($this->event);
        $currencyIds = app(PilotSettlementService::class)
            ->getCashReconciliation($settlement)
            ->pluck('currency_id');

        $rows = $currencyIds
            ->map(fn (int $currencyId) => [
                'currency_id' => $currencyId,
                'returned_amount' => isset($this->cashReturned[$currencyId]) && $this->cashReturned[$currencyId] !== ''
                    ? str_replace(',', '.', $this->cashReturned[$currencyId])
                    : null,
            ])
            ->values()
            ->all();

        app(PilotSettlementService::class)->saveCashReporting($this->event, $rows);
        $this->loadCashReportingFields();
        $this->notifyLedger('Kwota zwrotu zapisana');
    }

    public function updatedCostDocumentFiles(mixed $value, string $key): void
    {
        if ($this->costDocumentFilesPending($key)) {
            $this->uploadCostDocument((int) $key);
        }
    }

    protected function costDocumentFilesPending(string|int $key): bool
    {
        $files = $this->costDocumentFiles[$key] ?? null;

        if ($files === null || $files === []) {
            return false;
        }

        if (is_array($files)) {
            return collect($files)->filter()->isNotEmpty();
        }

        return true;
    }

    public function uploadCostDocument(int $costId): void
    {
        $cost = EventSettlementCost::findOrFail($costId);
        $files = $this->normalizeUploadedFiles($this->costDocumentFiles[$costId] ?? []);

        if ($files === []) {
            $this->addError("costDocumentFiles.{$costId}", 'Wybierz plik do przesłania.');

            return;
        }

        app(PilotSettlementService::class)->uploadDocument(
            $this->event,
            ['document_type' => 'receipt'],
            $files,
            $cost,
        );

        unset($this->costDocumentFiles[$costId]);
        $this->notifyLedger('Dokument dołączony do wydatku');
    }

    /**
     * @param  TemporaryUploadedFile|array<int, TemporaryUploadedFile>|mixed  $input
     * @return array<int, UploadedFile>
     */
    protected function normalizeUploadedFiles(mixed $input): array
    {
        $items = is_array($input) ? $input : [$input];

        return collect($items)
            ->filter(fn ($file) => $file instanceof TemporaryUploadedFile)
            ->map(fn (TemporaryUploadedFile $file) => new UploadedFile(
                $file->getRealPath(),
                $file->getClientOriginalName(),
                $file->getMimeType(),
                null,
                true,
            ))
            ->all();
    }

    protected function notifyLedger(string $message): void
    {
        if ($this instanceof \Filament\Pages\Page) {
            Notification::make()->title($message)->success()->send();
        } else {
            session()->flash('status', $message);
        }
    }

    public function deleteExpense(int $costId): void
    {
        $cost = EventSettlementCost::findOrFail($costId);
        app(PilotSettlementService::class)->deleteExpense($this->event, $cost);
        $this->loadCashReportingFields();
        $this->notifyLedger('Wydatek usunięty');
    }

    public function deleteDocument(int $documentId): void
    {
        $document = EventSettlementDocument::findOrFail($documentId);
        app(PilotSettlementService::class)->deleteDocument($this->event, $document);
        $this->notifyLedger('Dokument usunięty');
    }
}
