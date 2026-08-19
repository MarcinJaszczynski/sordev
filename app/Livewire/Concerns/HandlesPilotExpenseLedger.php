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

    /** @var array<int|string, string> Typ dokumentu jak w Kosztach (invoice, payment_proof, receipt…). */
    public array $costDocumentTypes = [];

    /** @var array<int|string, string> */
    public array $costDocumentNumbers = [];

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

    /**
     * @return list<array{currency: string, planned: float, office_paid: float, pilot_paid: float, pilot_due: float}>
     */
    public function getExpenseLedgerTotalsProperty(): array
    {
        return app(PilotSettlementService::class)->summarizeExpenseLines($this->expenseLines);
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
        $line = $this->expenseLines->firstWhere('id', $costId);
        $cost = $line ?? EventSettlementCost::findOrFail($costId);

        $this->editingCostId = $cost->id;
        $this->editCostPaidBy = 'pilot';

        $alreadyPaidByPilot = $cost->ledger_paid_amount ?? null;
        if ($alreadyPaidByPilot === null && (float) ($cost->actual_amount ?? 0) > 0.009) {
            $alreadyPaidByPilot = (float) $cost->actual_amount;
        }

        // Po zaliczce biura domyślnie dopłata, nie pełny plan — żeby pilot nie zapłacił „całości”.
        $defaultAmount = $alreadyPaidByPilot !== null
            ? (float) $alreadyPaidByPilot
            : (float) ($cost->ledger_pilot_due ?? $cost->planned_amount ?? 0);

        $this->editCostActualAmount = $this->formatLedgerAmountInput($defaultAmount);
        $this->editCostCurrencyId = $cost->actual_currency_id
            ?? $cost->planned_currency_id
            ?? $this->editCostCurrencyId
            ?? Currency::query()->where('code', 'PLN')->value('id');
        $this->editCostNotes = (string) ($cost->notes ?? '');
        $this->editCostInvoiceNumber = (string) ($cost->invoice_number ?? $cost->document_number ?? '');
        $this->editCostReceiptNumber = (string) ($cost->receipt_number ?? '');
        $this->editCostPaymentMethod = 'cash';
    }

    private function formatLedgerAmountInput(float $amount): string
    {
        if (abs($amount - round($amount)) < 0.00001) {
            return (string) (int) round($amount);
        }

        return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');
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
        $this->ensurePilotMutationsAllowed();

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
            'payment_method' => 'cash',
        ]);

        $this->cancelEditCost();
        $this->loadCashReportingFields();
        $this->notifyLedger('Wydatek zaktualizowany');
    }

    public function saveCashReporting(): void
    {
        $this->ensurePilotMutationsAllowed();

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
        $this->ensurePilotMutationsAllowed();

        $cost = EventSettlementCost::findOrFail($costId);
        $files = $this->normalizeUploadedFiles($this->costDocumentFiles[$costId] ?? []);

        if ($files === []) {
            $this->addError("costDocumentFiles.{$costId}", 'Wybierz plik do przesłania.');

            return;
        }

        $allowedTypes = array_keys(EventSettlementDocument::$documentTypes);
        $documentType = (string) ($this->costDocumentTypes[$costId] ?? 'invoice');
        if (! in_array($documentType, $allowedTypes, true)) {
            $documentType = 'invoice';
        }

        $documentNumber = trim((string) ($this->costDocumentNumbers[$costId] ?? ''));

        app(PilotSettlementService::class)->uploadDocument(
            $this->event,
            [
                'document_type' => $documentType,
                'document_number' => $documentNumber !== '' ? $documentNumber : null,
            ],
            $files,
            $cost,
        );

        unset($this->costDocumentFiles[$costId], $this->costDocumentNumbers[$costId]);
        $this->costDocumentTypes[$costId] = $documentType;
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

        try {
            app(PilotSettlementService::class)->deleteDocument($this->event, $document);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->addError('document', collect($e->errors())->flatten()->first() ?: $e->getMessage());

            return;
        }

        $this->loadCashReportingFields();
        $this->notifyLedger('Dokument usunięty');
    }

    public function deleteDocumentFile(int $documentId, int $fileIndex): void
    {
        $document = EventSettlementDocument::findOrFail($documentId);

        try {
            app(PilotSettlementService::class)->deleteDocumentFile($this->event, $document, $fileIndex);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->addError('document', collect($e->errors())->flatten()->first() ?: $e->getMessage());

            return;
        }

        $this->loadCashReportingFields();
        $this->notifyLedger('Plik usunięty z dokumentu');
    }

    protected function ensurePilotMutationsAllowed(): void
    {
        if (property_exists($this, 'context') && ($this->context ?? null) === 'admin') {
            return;
        }

        app(\App\Services\PilotAccessService::class)->assertPilotMutationsAllowed();
    }
}
