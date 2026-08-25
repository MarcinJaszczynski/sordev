<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventResource\Concerns;

use App\Actions\Finance\AttachSettlementCostDocumentAction;
use App\Actions\Finance\ChangeSettlementCostPayerAction;
use App\Actions\Finance\DeleteSettlementCostPaymentAction;
use App\Actions\Finance\RecordSettlementCostPaymentAction;
use App\Actions\Finance\UpdateSettlementCostPaymentAction;
use App\Actions\Finance\UpdateSettlementCostPlanAction;
use App\Actions\Finance\UpsertFinanceManualCostAction;
use App\Actions\Finance\UpsertFinanceProgramCostAction;
use App\Actions\Reservations\UpsertReservationAction;
use App\Data\ChangeSettlementCostPayerData;
use App\Data\RecordSettlementCostPaymentData;
use App\Data\UpdateSettlementCostPaymentData;
use App\Data\UpdateSettlementCostPlanData;
use App\Data\UpsertFinanceManualCostData;
use App\Data\UpsertFinanceProgramCostData;
use App\Data\UpsertReservationData;
use App\Filament\Forms\ProgramPointSettlementFinanceFields;
use App\Models\Contractor;
use App\Models\ContractorType;
use App\Models\Currency;
use App\Models\Event;
use App\Models\EventHotelStay;
use App\Models\EventProgramPoint;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\EventSettlementDocument;
use App\Models\Reservation;
use App\Services\ContractorLookupService;
use App\Services\EventFinanceOverviewService;
use App\Services\HotelStayReservationSync;
use App\Services\HotelStaySettlementSync;
use App\Services\ProgramPointPricingCalculator;
use App\Support\CurrencyAmountDisplay;
use App\Support\ProgramPointCostPricing;
use App\Support\Reservations\ProgramPointReservationGroup;
use App\Support\Reservations\ReservationFormDefaults;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Boczny panel kosztu (wpłaty / plan / dokumenty) — współdzielony przez Finanse i ubezpieczenia dni.
 */
trait InteractsWithSettlementCostDrawer
{
    public ?int $selectedCostId = null;

    /** Checkbox w panelu nadpłaty — wymagany przed approveOverpayment. */
    public bool $overpaymentConfirmAcknowledged = false;

    /** @var array<string, mixed> */
    public array $paymentForm = [];

    /** @var array<string, mixed> */
    public array $planForm = [];

    /** @var array<string, mixed> */
    public array $costForm = [];

    /** @var array<string, mixed> */
    public array $documentForm = [];

    /** @var array<int, TemporaryUploadedFile> */
    public array $documentFiles = [];

    public bool $showPaymentForm = false;

    public bool $showPlanForm = false;

    public bool $showCostForm = false;

    /** program = punkt programu; manual = wydatek tylko w finansach. */
    public string $costFormMode = 'program';

    public bool $showDocumentForm = false;

    public bool $showReservationForm = false;

    /** @var array<string, mixed> */
    public array $reservationForm = [];

    public string $reservationContractorSearch = '';

    /** @var array<int, string> */
    public array $reservationContractorSearchResults = [];

    public bool $showReservationContractorSearchResults = false;

    public bool $reservationContractorSearchAll = false;

    public string $reservationContractorLabel = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $reservationAttachmentFiles = [];

    public ?int $editingPaymentId = null;

    protected function initializeSettlementCostDrawerForms(): void
    {
        $this->resetPaymentForm();
        $this->resetPlanForm();
        $this->resetCostForm();
        $this->resetDocumentForm();
        $this->resetReservationForm();
    }

    /**
     * Host musi umieć rozwiązać Event (Page::getRecord / RelationManager::getOwnerRecord).
     */
    protected function settlementCostEvent(): Event
    {
        if (method_exists($this, 'getOwnerRecord')) {
            $owner = $this->getOwnerRecord();
            if ($owner instanceof Event) {
                return $owner;
            }
        }

        if (method_exists($this, 'getRecord')) {
            $record = $this->getRecord();
            if ($record instanceof Event) {
                return $record;
            }
        }

        throw new \LogicException('Settlement cost drawer requires an Event record.');
    }

    /**
     * Po mutacjach — odśwież overview / tabelę hosta.
     */
    protected function invalidateSettlementCostCaches(): void
    {
        unset($this->selectedRow, $this->drawerReservations);
        $this->dispatchSettlementFinanceChanged();
    }

    protected function dispatchSettlementFinanceChanged(): void
    {
        $this->dispatch('event-workflow-finance-changed');
        $this->dispatch('event-program-planner-refresh');
    }

    protected function ensureSettlement(): EventSettlement
    {
        return EventSettlement::findOrCreateActiveForEvent($this->settlementCostEvent());
    }

    public function openCost(int $costId): void
    {
        $this->selectedCostId = $costId;
        $this->editingPaymentId = null;
        $this->showPaymentForm = false;
        $this->showPlanForm = false;
        $this->showCostForm = false;
        $this->showDocumentForm = false;
        $this->showReservationForm = false;
        $this->overpaymentConfirmAcknowledged = false;
        $this->resetPaymentForm();
        $this->resetDocumentForm();
        $this->resetReservationForm();
        unset($this->selectedRow);
        $this->hydratePlanFormFromSelection();
        $this->hydrateReservationFormFromSelection();
    }

    public function closeCost(): void
    {
        $this->selectedCostId = null;
        $this->editingPaymentId = null;
        $this->showPaymentForm = false;
        $this->showPlanForm = false;
        $this->showCostForm = false;
        $this->showDocumentForm = false;
        $this->showReservationForm = false;
        $this->overpaymentConfirmAcknowledged = false;
        unset($this->selectedRow);
    }

    public function startAddCost(): void
    {
        $this->startCostForm('program');
    }

    public function startAddManualCost(): void
    {
        $this->startCostForm('manual');
    }

    protected function startCostForm(string $mode): void
    {
        $this->ensureSettlement();
        $this->selectedCostId = null;
        $this->costFormMode = $mode;
        $this->showCostForm = true;
        $this->showPaymentForm = false;
        $this->showPlanForm = false;
        $this->showDocumentForm = false;
        $this->resetCostForm();
        unset($this->selectedRow);
    }

    public function saveCost(): void
    {
        if ($this->costFormMode === 'manual') {
            $this->saveManualCost();

            return;
        }

        $this->validate([
            'costForm.name' => ['required', 'string', 'max:255'],
            'costForm.amount' => ['required', 'numeric', 'min:0'],
            'costForm.paid_by' => ['required', 'in:office,pilot'],
            'costForm.day' => ['required', 'integer', 'min:1'],
            'costForm.currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'costForm.convert_to_pln' => ['nullable', 'boolean'],
            'costForm.contractor_id' => ['nullable', 'integer', 'exists:contractors,id'],
        ], [], [
            'costForm.name' => 'nazwa',
            'costForm.amount' => 'kwota',
            'costForm.paid_by' => 'płatnik',
            'costForm.day' => 'dzień',
            'costForm.currency_id' => 'waluta',
            'costForm.contractor_id' => 'kontrahent',
        ]);

        $form = $this->costForm;
        $existing = null;
        if ($this->selectedCostId) {
            $existing = EventSettlementCost::query()->find($this->selectedCostId);
        }

        try {
            $cost = app(UpsertFinanceProgramCostAction::class)(new UpsertFinanceProgramCostData(
                event: $this->settlementCostEvent(),
                name: (string) $form['name'],
                amount: (float) $form['amount'],
                currencyId: filled($form['currency_id'] ?? null) ? (int) $form['currency_id'] : null,
                convertToPln: (bool) ($form['convert_to_pln'] ?? true),
                paidBy: (string) $form['paid_by'],
                day: (int) $form['day'],
                notes: $form['notes'] ?? null,
                dueDate: filled($form['due_date'] ?? null) ? Carbon::parse($form['due_date']) : null,
                planCost: $existing,
                contractorId: filled($form['contractor_id'] ?? null) ? (int) $form['contractor_id'] : null,
            ));
        } catch (\Throwable $e) {
            Notification::make()->title('Nie udało się zapisać kosztu')->body($e->getMessage())->danger()->send();

            return;
        }

        $this->showCostForm = false;
        $this->selectedCostId = (int) $cost->id;
        $this->resetCostForm();
        $this->invalidateSettlementCostCaches();
        $this->hydratePlanFormFromSelection();

        Notification::make()
            ->title($existing ? 'Zaktualizowano koszt' : 'Dodano koszt')
            ->body('Pozycja jest widoczna w Finansach i w Programie.')
            ->success()
            ->send();
    }

    protected function saveManualCost(): void
    {
        $this->validate([
            'costForm.name' => ['required', 'string', 'max:255'],
            'costForm.amount' => ['required', 'numeric', 'min:0'],
            'costForm.paid_by' => ['required', 'in:office,pilot'],
            'costForm.currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'costForm.convert_to_pln' => ['nullable', 'boolean'],
            'costForm.contractor_id' => ['nullable', 'integer', 'exists:contractors,id'],
        ], [], [
            'costForm.name' => 'nazwa',
            'costForm.amount' => 'kwota',
            'costForm.paid_by' => 'płatnik',
            'costForm.currency_id' => 'waluta',
            'costForm.contractor_id' => 'kontrahent',
        ]);

        $form = $this->costForm;
        $existing = null;
        if ($this->selectedCostId) {
            $existing = EventSettlementCost::query()->find($this->selectedCostId);
        }

        try {
            $cost = app(UpsertFinanceManualCostAction::class)(new UpsertFinanceManualCostData(
                event: $this->settlementCostEvent(),
                name: (string) $form['name'],
                amount: (float) $form['amount'],
                currencyId: filled($form['currency_id'] ?? null) ? (int) $form['currency_id'] : null,
                convertToPln: (bool) ($form['convert_to_pln'] ?? true),
                paidBy: (string) $form['paid_by'],
                notes: $form['notes'] ?? null,
                dueDate: filled($form['due_date'] ?? null) ? Carbon::parse($form['due_date']) : null,
                planCost: $existing,
                contractorId: filled($form['contractor_id'] ?? null) ? (int) $form['contractor_id'] : null,
            ));
        } catch (\Throwable $e) {
            Notification::make()->title('Nie udało się zapisać wydatku')->body($e->getMessage())->danger()->send();

            return;
        }

        $this->showCostForm = false;
        $this->selectedCostId = (int) $cost->id;
        $this->resetCostForm();
        $this->invalidateSettlementCostCaches();
        $this->hydratePlanFormFromSelection();

        Notification::make()
            ->title($existing ? 'Zaktualizowano wydatek' : 'Dodano wydatek')
            ->body('Pozycja widoczna tylko w Finansach (bez programu).')
            ->success()
            ->send();
    }

    public function toggleCostApproval(int $costId): void
    {
        $settlement = $this->ensureSettlement();
        $cost = EventSettlementCost::query()
            ->where('settlement_id', $settlement->id)
            ->find($costId);

        if (! $cost || EventSettlementCost::isPaymentSourceType($cost->source_type)) {
            Notification::make()->title('Nie znaleziono kosztu')->warning()->send();

            return;
        }

        $isApproved = ($cost->approval_status ?? 'pending') === 'approved';

        $cost->update([
            'approval_status' => $isApproved ? 'pending' : 'approved',
            'reviewed_by' => $isApproved ? null : auth()->id(),
            'reviewed_at' => $isApproved ? null : now(),
        ]);

        EventFinanceOverviewService::forgetOverviewCacheForEvent((int) $this->settlementCostEvent()->id);
        $this->invalidateSettlementCostCaches();

        if ($this->selectedCostId === $costId) {
            unset($this->selectedRow);
        }
    }

    public function changeCostPaidBy(int $costId, string $paidBy): void
    {
        $settlement = $this->ensureSettlement();
        $cost = EventSettlementCost::query()
            ->where('settlement_id', $settlement->id)
            ->find($costId);

        if (! $cost || EventSettlementCost::isPaymentSourceType($cost->source_type)) {
            Notification::make()->title('Nie znaleziono kosztu')->warning()->send();

            return;
        }

        if (($cost->paid_by ?? 'office') === $paidBy) {
            return;
        }

        try {
            app(ChangeSettlementCostPayerAction::class)(new ChangeSettlementCostPayerData(
                planCost: $cost,
                paidBy: $paidBy,
            ));
        } catch (\Throwable $e) {
            Notification::make()->title('Nie udało się zmienić płatnika')->body($e->getMessage())->danger()->send();

            return;
        }

        $this->invalidateSettlementCostCaches();

        if ($this->selectedCostId === $costId) {
            $this->hydratePlanFormFromSelection();
        }

        $label = EventSettlementCost::$paidByOptions[$paidBy] ?? $paidBy;
        Notification::make()
            ->title('Zmieniono płatnika')
            ->body('Ustawiono „'.$label.'”.')
            ->success()
            ->send();
    }

    public function approveOverpayment(?int $costId = null): void
    {
        if (! $this->overpaymentConfirmAcknowledged) {
            Notification::make()
                ->title('Zaznacz potwierdzenie nadpłaty')
                ->body('Aby zatwierdzić, potwierdź checkboxem, że nadpłata jest prawidłowa.')
                ->warning()
                ->send();

            return;
        }

        $id = $costId ?? $this->selectedCostId;
        if (! $id) {
            return;
        }

        $settlement = $this->ensureSettlement();
        $plan = EventSettlementCost::query()
            ->where('settlement_id', $settlement->id)
            ->whereKey($id)
            ->firstOrFail();

        $health = app(\App\Services\SettlementPaymentHealthService::class);
        $health->approveOverpayment($plan, auth()->id());
        $health->syncPlanPaymentStatus($plan->fresh(), $settlement->fresh(['costs'])->costs);

        $this->overpaymentConfirmAcknowledged = false;
        $this->invalidateSettlementCostCaches();
        Notification::make()->title('Zatwierdzono nadpłatę')->success()->send();
    }

    public function startAddDocument(): void
    {
        $this->resetDocumentForm();
        $this->showDocumentForm = true;
        $this->showPaymentForm = false;
        $this->showPlanForm = false;
        $this->showCostForm = false;
    }

    public function saveDocument(): void
    {
        $this->validate([
            'documentFiles' => ['required', 'array', 'min:1'],
            'documentFiles.*' => ['file', 'max:10240'],
            'documentForm.document_type' => ['required', 'string'],
        ], [], [
            'documentFiles' => 'pliki',
            'documentForm.document_type' => 'typ dokumentu',
        ]);

        if (! $this->selectedCostId) {
            return;
        }

        $cost = EventSettlementCost::query()->findOrFail($this->selectedCostId);
        $files = [];
        foreach ($this->documentFiles as $file) {
            if ($file instanceof TemporaryUploadedFile) {
                $files[] = new \Illuminate\Http\UploadedFile(
                    $file->getRealPath(),
                    $file->getClientOriginalName(),
                    $file->getMimeType(),
                    null,
                    true,
                );
            }
        }

        try {
            app(AttachSettlementCostDocumentAction::class)(
                planCost: $cost,
                files: $files,
                documentType: (string) ($this->documentForm['document_type'] ?? 'invoice'),
                documentNumber: $this->documentForm['document_number'] ?? null,
                notes: $this->documentForm['notes'] ?? null,
                attachToPilotPdf: (bool) ($this->documentForm['attach_to_pilot_pdf'] ?? false),
                attachToHotelPdf: (bool) ($this->documentForm['attach_to_hotel_pdf'] ?? false),
                attachToDriverPdf: (bool) ($this->documentForm['attach_to_driver_pdf'] ?? false),
                attachToFolderPdf: (bool) ($this->documentForm['attach_to_folder_pdf'] ?? false),
            );
        } catch (\Throwable $e) {
            Notification::make()->title('Nie udało się dodać dokumentu')->body($e->getMessage())->danger()->send();

            return;
        }

        $this->showDocumentForm = false;
        $this->resetDocumentForm();
        $this->invalidateSettlementCostCaches();
        Notification::make()->title('Dodano dokument')->success()->send();
    }

    public function deleteDocument(int $documentId): void
    {
        if (! $this->selectedCostId) {
            return;
        }

        $cost = EventSettlementCost::query()->findOrFail($this->selectedCostId);
        $document = EventSettlementDocument::query()->findOrFail($documentId);

        try {
            app(AttachSettlementCostDocumentAction::class)->delete($document, $cost);
        } catch (\Throwable $e) {
            Notification::make()->title('Nie udało się usunąć')->body($e->getMessage())->danger()->send();

            return;
        }

        $this->invalidateSettlementCostCaches();
        Notification::make()->title('Usunięto dokument')->success()->send();
    }

    #[Computed]
    public function selectedRow(): ?array
    {
        if (! $this->selectedCostId) {
            return null;
        }

        $overview = app(EventFinanceOverviewService::class)->forEvent(
            $this->settlementCostEvent(),
            EventFinanceOverviewService::FILTER_ALL,
            null,
            hideZero: false,
        );

        foreach ($overview['rows'] as $row) {
            if ((int) $row['cost_id'] === (int) $this->selectedCostId) {
                return $row;
            }
        }

        // Fallback: świeżo upsertowany / poza cache overview — zbuduj wiersz z kosztu.
        return app(EventFinanceOverviewService::class)->rowForCostId(
            $this->settlementCostEvent(),
            (int) $this->selectedCostId,
        );
    }

    public function openNewPaymentForm(): void
    {
        $this->openPaymentForm('advance', null);
    }

    public function startAddPayment(?string $paidBy = null): void
    {
        $this->openPaymentForm('final', $paidBy);
    }

    public function startAddAdvance(?string $paidBy = null): void
    {
        $this->openPaymentForm('advance', $paidBy);
    }

    public function startAddSupplement(?string $paidBy = null): void
    {
        $this->openPaymentForm('supplement', $paidBy);
    }

    public function startAddFullPayment(?string $paidBy = null): void
    {
        $this->openPaymentForm('full', $paidBy);
    }

    protected function openPaymentForm(string $advanceType, ?string $paidBy = null): void
    {
        $this->editingPaymentId = null;
        $this->resetPaymentForm();
        $this->hydratePaymentDefaultsFromSelection($advanceType, $paidBy);
        $this->showPaymentForm = true;
        $this->showPlanForm = false;
        $this->showCostForm = false;
        $this->showDocumentForm = false;
    }

    public function startEditPayment(int $paymentId): void
    {
        $row = $this->selectedRow;
        if (! is_array($row)) {
            return;
        }

        $payment = collect($row['payments'] ?? [])->firstWhere('id', $paymentId);
        if (! is_array($payment)) {
            Notification::make()->title('Nie znaleziono wpłaty')->danger()->send();

            return;
        }

        $currencyId = filled($payment['currency_id'] ?? null)
            ? (int) $payment['currency_id']
            : (filled($row['planned_currency_id'] ?? null) ? (int) $row['planned_currency_id'] : $this->defaultCurrencyId());
        $convertToPln = (bool) ($payment['convert_to_pln'] ?? $row['planned_convert_to_pln'] ?? true);
        $this->applyPaymentFormCurrency($currencyId, $convertToPln);

        $isForeign = (bool) ($this->paymentForm['is_foreign'] ?? false);
        $rate = (float) ($payment['rate'] ?? $this->paymentForm['rate'] ?? $row['planned_rate'] ?? 1);
        if ($rate <= 0) {
            $rate = 1.0;
        }

        $advanceType = EventSettlementCost::normalizeUserAdvanceType((string) ($payment['advance_type'] ?? 'advance'));

        $this->editingPaymentId = $paymentId;
        $this->paymentForm = [
            ...$this->paymentForm,
            'amount' => $isForeign ? (float) ($payment['amount'] ?? 0) : null,
            'amount_pln' => (float) ($payment['amount_pln'] ?? 0),
            'rate' => $rate,
            'advance_type' => $advanceType,
            'payment_method' => (string) ($payment['method'] ?? 'transfer'),
            'paid_by' => (string) ($payment['paid_by'] ?? 'office'),
            'paid_at' => $payment['paid_at'] ?? now()->format('Y-m-d'),
            'due_date' => $payment['due_date'] ?? null,
            'document_number' => $payment['document_number'] ?? null,
            'notes' => $payment['notes'] ?? null,
            'reservation_id' => $payment['reservation_id'] ?? null,
        ];

        $this->showPaymentForm = true;
        $this->showPlanForm = false;
        $this->showCostForm = false;
        $this->showDocumentForm = false;
    }

    public function deletePayment(int $paymentId): void
    {
        try {
            $payment = EventSettlementCost::query()->findOrFail($paymentId);
            app(DeleteSettlementCostPaymentAction::class)($payment);
        } catch (\Throwable $e) {
            Notification::make()->title('Nie udało się usunąć wpłaty')->body($e->getMessage())->danger()->send();

            return;
        }

        $this->showPaymentForm = false;
        $this->editingPaymentId = null;
        $this->resetPaymentForm();
        $this->invalidateSettlementCostCaches();
        if ($this->showReservationForm) {
            $this->hydrateReservationFormFromSelection();
        }
        Notification::make()->title('Usunięto wpłatę')->success()->send();
    }

    public function updatedPaymentFormCurrencyId(mixed $value): void
    {
        $this->applyPaymentFormCurrency(filled($value) ? (int) $value : null);
    }

    public function savePayment(): void
    {
        $currencyId = filled($this->paymentForm['currency_id'] ?? null)
            ? (int) $this->paymentForm['currency_id']
            : $this->defaultCurrencyId();
        $isForeign = CurrencyAmountDisplay::isForeignCurrency($currencyId);
        $convertToPln = $isForeign ? (bool) ($this->paymentForm['convert_to_pln'] ?? true) : true;

        $rules = [
            'paymentForm.currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'paymentForm.payment_method' => ['required', 'in:cash,transfer,card,other'],
            'paymentForm.paid_by' => ['required', 'in:office,pilot'],
            'paymentForm.advance_type' => ['required', EventSettlementCost::userSelectableAdvanceTypesValidationRule()],
        ];
        $attributes = [
            'paymentForm.currency_id' => 'waluta',
            'paymentForm.payment_method' => 'metoda',
            'paymentForm.paid_by' => 'płatnik',
            'paymentForm.advance_type' => 'rodzaj',
        ];

        if ($isForeign) {
            $rules['paymentForm.amount'] = ['required', 'numeric', 'min:0.01'];
            $attributes['paymentForm.amount'] = 'kwota';
            if ($convertToPln) {
                $rules['paymentForm.rate'] = ['required', 'numeric', 'min:0.0001'];
                $attributes['paymentForm.rate'] = 'kurs';
            }
        } else {
            $rules['paymentForm.amount_pln'] = ['required', 'numeric', 'min:0.01'];
            $attributes['paymentForm.amount_pln'] = 'kwota';
        }

        $this->validate($rules, [], $attributes);

        if (! $this->selectedCostId) {
            return;
        }

        $form = $this->paymentForm;
        $paidBy = (string) ($form['paid_by'] ?? 'office');
        if ($paidBy === 'pilot') {
            $form['payment_method'] = 'cash';
            $this->paymentForm['payment_method'] = 'cash';
        }
        $amount = $isForeign ? (float) ($form['amount'] ?? 0) : null;
        $rate = $isForeign ? (float) ($form['rate'] ?? 1) : null;
        $amountPln = $isForeign
            ? ($convertToPln ? round(((float) ($form['amount'] ?? 0)) * ((float) ($form['rate'] ?? 1)), 2) : 0.0)
            : (float) ($form['amount_pln'] ?? 0);

        try {
            if ($this->editingPaymentId) {
                $payment = EventSettlementCost::query()->findOrFail($this->editingPaymentId);
                app(UpdateSettlementCostPaymentAction::class)(new UpdateSettlementCostPaymentData(
                    payment: $payment,
                    amountPln: $amountPln,
                    paymentMethod: (string) $form['payment_method'],
                    paidBy: (string) $form['paid_by'],
                    advanceType: (string) $form['advance_type'],
                    paidAt: filled($form['paid_at'] ?? null) ? Carbon::parse($form['paid_at']) : now(),
                    dueDate: filled($form['due_date'] ?? null) ? Carbon::parse($form['due_date']) : null,
                    documentNumber: $form['document_number'] ?? null,
                    notes: $form['notes'] ?? null,
                    amount: $amount,
                    rate: $rate,
                    currencyId: $currencyId,
                    reservationId: filled($form['reservation_id'] ?? null) ? (int) $form['reservation_id'] : null,
                    convertToPln: $convertToPln,
                ));
            } else {
                $cost = EventSettlementCost::query()->with('plannedCurrency')->findOrFail($this->selectedCostId);
                app(RecordSettlementCostPaymentAction::class)(new RecordSettlementCostPaymentData(
                    planCost: $cost,
                    amountPln: $amountPln,
                    paymentMethod: (string) $form['payment_method'],
                    paidBy: (string) $form['paid_by'],
                    advanceType: (string) $form['advance_type'],
                    paidAt: filled($form['paid_at'] ?? null) ? Carbon::parse($form['paid_at']) : now(),
                    dueDate: filled($form['due_date'] ?? null) ? Carbon::parse($form['due_date']) : null,
                    documentNumber: $form['document_number'] ?? null,
                    notes: $form['notes'] ?? null,
                    paidByUserId: auth()->id(),
                    amount: $amount,
                    rate: $rate,
                    currencyId: $currencyId,
                    reservationId: filled($form['reservation_id'] ?? null) ? (int) $form['reservation_id'] : null,
                    convertToPln: $convertToPln,
                ));
            }
        } catch (\Throwable $e) {
            Notification::make()->title('Nie udało się zapisać wpłaty')->body($e->getMessage())->danger()->send();

            return;
        }

        $wasEdit = $this->editingPaymentId !== null;
        $this->showPaymentForm = false;
        $this->editingPaymentId = null;
        $this->resetPaymentForm();
        $this->invalidateSettlementCostCaches();
        if ($this->showReservationForm) {
            $this->hydrateReservationFormFromSelection();
        }
        Notification::make()
            ->title($wasEdit ? 'Zapisano wpłatę' : 'Dodano wpłatę')
            ->success()
            ->send();
    }

    public function startEditPlan(): void
    {
        $this->hydratePlanFormFromSelection();
        $this->showPlanForm = true;
        $this->showCostForm = false;
        $this->showPaymentForm = false;
        $this->showDocumentForm = false;
    }

    public function recalculateProgramPointPlanTotals(): void
    {
        if (! ($this->planForm['is_program_point'] ?? false)) {
            return;
        }

        $event = $this->settlementCostEvent();
        $headcount = ProgramPointCostPricing::costHeadcount(
            $event,
            null,
            (bool) ($this->planForm['include_gratis_in_cost'] ?? false),
            (bool) ($this->planForm['include_pilot_in_cost'] ?? false),
            (bool) ($this->planForm['include_driver_in_cost'] ?? false),
        );
        $groupSizeInt = (int) ($this->planForm['group_size'] ?? 1);
        $fixedQty = max(1, (int) ($this->planForm['quantity'] ?? 1));
        $unit = (float) ($this->planForm['unit_price'] ?? 0);

        $calculated = ProgramPointPricingCalculator::totalPrice(
            $unit,
            $headcount,
            $groupSizeInt <= 0 ? 0 : $groupSizeInt,
            $fixedQty,
        );

        // Podpowiedź z formuły — przy zmianie parametrów przenosi się też do planu.
        // Ręczna edycja „Kwota planowana (suma)” nie woła tej metody, więc nadpis planu zostaje.
        $this->planForm['calculated_price'] = $calculated;
        $this->planForm['planned_price'] = $calculated;
    }

    public function savePlan(): void
    {
        if ($this->planForm['is_program_point'] ?? false) {
            $this->saveProgramPointPlan();

            return;
        }

        $this->validate([
            'planForm.planned_amount_pln' => ['required', 'numeric', 'min:0'],
            'planForm.paid_by' => ['required', 'in:office,pilot'],
            'planForm.contractor_id' => ['nullable', 'integer', 'exists:contractors,id'],
        ], [], [
            'planForm.planned_amount_pln' => 'plan',
            'planForm.paid_by' => 'płatnik',
            'planForm.contractor_id' => 'kontrahent',
        ]);

        if (! $this->selectedCostId) {
            return;
        }

        $cost = EventSettlementCost::query()->findOrFail($this->selectedCostId);
        $form = $this->planForm;

        app(UpdateSettlementCostPlanAction::class)(new UpdateSettlementCostPlanData(
            planCost: $cost,
            plannedAmountPln: (float) $form['planned_amount_pln'],
            paidBy: (string) $form['paid_by'],
            notes: $form['notes'] ?? null,
            dueDate: filled($form['due_date'] ?? null) ? Carbon::parse($form['due_date']) : null,
            touchContractor: true,
            contractorId: filled($form['contractor_id'] ?? null) ? (int) $form['contractor_id'] : null,
        ));

        $this->showPlanForm = false;
        $this->invalidateSettlementCostCaches();
        Notification::make()->title('Zapisano kwotę planowaną')->success()->send();
    }

    protected function saveProgramPointPlan(): void
    {
        $this->validate([
            'planForm.planned_price' => ['required', 'numeric', 'min:0'],
            'planForm.unit_price' => ['required', 'numeric', 'min:0'],
            'planForm.group_size' => ['required', 'integer', 'min:0'],
            'planForm.quantity' => ['nullable', 'integer', 'min:1'],
            'planForm.currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'planForm.convert_to_pln' => ['nullable', 'boolean'],
            'planForm.paid_by' => ['required', 'in:office,pilot'],
            'planForm.contractor_id' => ['nullable', 'integer', 'exists:contractors,id'],
        ], [], [
            'planForm.planned_price' => 'kwota planowana',
            'planForm.unit_price' => 'cena jednostkowa',
            'planForm.group_size' => 'wielkość grupy',
            'planForm.quantity' => 'ilość',
            'planForm.currency_id' => 'waluta',
            'planForm.paid_by' => 'płatnik',
            'planForm.contractor_id' => 'kontrahent',
        ]);

        if (! $this->selectedCostId) {
            return;
        }

        $cost = EventSettlementCost::query()->findOrFail($this->selectedCostId);
        if ($cost->source_type !== 'program_point' || ! $cost->source_id) {
            Notification::make()->title('Brak powiązanego punktu programu')->danger()->send();

            return;
        }

        $point = EventProgramPoint::query()
            ->where('event_id', $this->settlementCostEvent()->id)
            ->findOrFail((int) $cost->source_id);

        $event = $this->settlementCostEvent();
        $plannedAmount = (float) ($this->planForm['planned_price'] ?? $this->planForm['calculated_price'] ?? 0);
        $currencyId = filled($this->planForm['currency_id'] ?? null) ? (int) $this->planForm['currency_id'] : null;
        $convertToPln = (bool) ($this->planForm['convert_to_pln'] ?? false);

        // Plan żyje osobno od ceny szablonu / unit_price punktu — nie nadpisujemy kosztu jednostkowego.
        $point->update([
            'planned_price' => $plannedAmount,
            'currency_id' => $currencyId,
            'convert_to_pln' => $convertToPln,
            'include_gratis_in_cost' => (bool) ($this->planForm['include_gratis_in_cost'] ?? false),
            'include_pilot_in_cost' => (bool) ($this->planForm['include_pilot_in_cost'] ?? false),
            'include_driver_in_cost' => (bool) ($this->planForm['include_driver_in_cost'] ?? false),
        ]);

        $currency = $currencyId ? Currency::query()->find($currencyId) : null;
        $rate = (float) ($currency?->exchange_rate ?? 1);
        $plannedAmountPln = ProgramPointSettlementFinanceFields::isForeignCurrency($currencyId)
            ? ($convertToPln ? round($plannedAmount * $rate, 2) : 0.0)
            : round($plannedAmount, 2);

        app(UpdateSettlementCostPlanAction::class)(new UpdateSettlementCostPlanData(
            planCost: $cost,
            plannedAmountPln: $plannedAmountPln,
            paidBy: (string) ($this->planForm['paid_by'] ?? 'office'),
            notes: $this->planForm['notes'] ?? null,
            dueDate: filled($this->planForm['due_date'] ?? null) ? Carbon::parse($this->planForm['due_date']) : null,
            plannedAmount: $plannedAmount,
            plannedCurrencyId: $currencyId,
            plannedConvertToPln: $convertToPln,
            plannedRate: $rate,
            touchContractor: true,
            contractorId: filled($this->planForm['contractor_id'] ?? null) ? (int) $this->planForm['contractor_id'] : null,
        ));

        $this->showPlanForm = false;
        $this->invalidateSettlementCostCaches();
        Notification::make()->title('Zapisano kwotę planowaną')->success()->send();
    }

    protected function resetPaymentForm(): void
    {
        $this->paymentForm = [
            'amount' => null,
            'amount_pln' => null,
            'rate' => 1,
            'currency_id' => $this->defaultCurrencyId(),
            'currency_symbol' => 'PLN',
            'is_foreign' => false,
            'convert_to_pln' => true,
            'advance_type' => 'advance',
            'payment_method' => 'transfer',
            'paid_by' => 'office',
            'paid_at' => now()->format('Y-m-d'),
            'due_date' => null,
            'document_number' => null,
            'notes' => null,
            'reservation_id' => null,
        ];
    }

    protected function hydratePaymentDefaultsFromSelection(string $advanceType, ?string $paidByOverride = null): void
    {
        if (! array_key_exists($advanceType, EventSettlementCost::userSelectableAdvanceTypes())) {
            $advanceType = 'advance';
        }

        $row = $this->selectedRow;
        $override = in_array($paidByOverride, ['office', 'pilot'], true) ? $paidByOverride : null;

        if (! is_array($row)) {
            $this->paymentForm['advance_type'] = $advanceType;
            if ($override !== null) {
                $this->paymentForm['paid_by'] = $override;
                $this->paymentForm['payment_method'] = $override === 'pilot' ? 'cash' : 'transfer';
            }

            return;
        }

        $paidBy = $override ?? (string) ($row['paid_by'] ?? 'office');
        $currencyId = filled($row['planned_currency_id'] ?? null)
            ? (int) $row['planned_currency_id']
            : $this->defaultCurrencyId();
        $convertToPln = (bool) ($row['planned_convert_to_pln'] ?? true);
        $this->applyPaymentFormCurrency($currencyId, $convertToPln);

        $isForeign = (bool) ($this->paymentForm['is_foreign'] ?? false);
        $rate = (float) ($this->paymentForm['rate'] ?? $row['planned_rate'] ?? 1);
        if ($rate <= 0) {
            $rate = 1.0;
        }

        $plannedAmount = (float) ($row['planned_amount'] ?? 0);
        $plannedPln = (float) ($row['planned_pln'] ?? $row['planned_amount_pln'] ?? 0);
        $paidForeign = collect($row['payments'] ?? [])
            ->sum(fn (array $p): float => (float) ($p['amount'] ?? 0));
        $remainingForeign = max(0, round($plannedAmount - $paidForeign, 2));
        $remainingPln = (float) ($row['remaining_pln'] ?? 0);
        if ($isForeign && $remainingPln <= 0.01 && $remainingForeign > 0.01) {
            $remainingPln = round($remainingForeign * $rate, 2);
        }

        $this->paymentForm['paid_by'] = $paidBy;
        $this->paymentForm['advance_type'] = $advanceType;
        $this->paymentForm['payment_method'] = $paidBy === 'pilot' ? 'cash' : 'transfer';
        $this->paymentForm['rate'] = $rate;

        $amountHint = match ($advanceType) {
            'advance', 'supplement' => $remainingPln > 0.01
                ? ($isForeign && $remainingForeign > 0.01 ? round($remainingForeign / 2, 2) : round($remainingPln / 2, 2))
                : null,
            'final' => $remainingPln > 0.01
                ? ($isForeign && $remainingForeign > 0.01 ? $remainingForeign : round($remainingPln, 2))
                : null,
            'full' => $plannedPln > 0.01
                ? ($isForeign && $plannedAmount > 0.01 ? $plannedAmount : round($plannedPln, 2))
                : null,
            default => null,
        };

        if ($isForeign) {
            $this->paymentForm['amount'] = $amountHint;
            $this->paymentForm['amount_pln'] = $amountHint !== null ? round((float) $amountHint * $rate, 2) : null;
        } else {
            $this->paymentForm['amount_pln'] = $amountHint;
        }

        if (filled($row['next_due_label'] ?? null)) {
            $this->paymentForm['due_date'] = $row['next_due_label'];
        }

        $reservations = $this->drawerReservations;
        $this->paymentForm['reservation_id'] = $reservations->count() === 1
            ? (int) $reservations->first()->id
            : null;
    }

    protected function applyPaymentFormCurrency(?int $currencyId, ?bool $convertToPln = null): void
    {
        $currencyId = $currencyId ?: $this->defaultCurrencyId();
        $currency = $currencyId ? Currency::query()->find($currencyId) : null;
        $isForeign = CurrencyAmountDisplay::isForeignCurrency($currencyId);
        $rate = CurrencyAmountDisplay::rate($currency);
        if ($rate <= 0) {
            $rate = 1.0;
        }

        $this->paymentForm['currency_id'] = $currencyId;
        $this->paymentForm['currency_symbol'] = CurrencyAmountDisplay::symbol($currency);
        $this->paymentForm['is_foreign'] = $isForeign;
        $this->paymentForm['rate'] = $rate;

        if ($convertToPln !== null) {
            $this->paymentForm['convert_to_pln'] = $isForeign ? $convertToPln : true;
        } elseif (! array_key_exists('convert_to_pln', $this->paymentForm) || ! $isForeign) {
            $this->paymentForm['convert_to_pln'] = $this->paymentForm['convert_to_pln'] ?? true;
            if (! $isForeign) {
                $this->paymentForm['convert_to_pln'] = true;
            }
        }
    }

    protected function resetPlanForm(): void
    {
        $this->planForm = [
            'is_program_point' => false,
            'planned_amount_pln' => null,
            'unit_price' => null,
            'group_size' => 1,
            'quantity' => 1,
            'currency_id' => $this->defaultCurrencyId(),
            'convert_to_pln' => true,
            'include_gratis_in_cost' => false,
            'include_pilot_in_cost' => false,
            'include_driver_in_cost' => false,
            'calculated_price' => null,
            'planned_price' => null,
            'paid_by' => 'office',
            'due_date' => null,
            'notes' => null,
            'contractor_id' => null,
        ];
    }

    protected function resetCostForm(): void
    {
        $this->costForm = [
            'name' => '',
            'amount' => null,
            'currency_id' => $this->defaultCurrencyId(),
            'convert_to_pln' => true,
            'paid_by' => 'office',
            'day' => 1,
            'due_date' => null,
            'notes' => null,
            'contractor_id' => null,
        ];
    }

    protected function defaultCurrencyId(): ?int
    {
        $id = Currency::query()->where('symbol', 'PLN')->value('id');

        return $id ? (int) $id : null;
    }

    protected function programPointDayForSelectedCost(): int
    {
        if (! $this->selectedCostId) {
            return 1;
        }

        $cost = EventSettlementCost::query()->find($this->selectedCostId);
        if (! $cost || $cost->source_type !== 'program_point' || ! $cost->source_id) {
            return 1;
        }

        $day = EventProgramPoint::query()->whereKey($cost->source_id)->value('day');

        return max(1, (int) ($day ?? 1));
    }

    protected function resetDocumentForm(): void
    {
        $this->documentForm = [
            'document_type' => 'invoice',
            'document_number' => null,
            'notes' => null,
            'attach_to_pilot_pdf' => false,
            'attach_to_hotel_pdf' => false,
            'attach_to_driver_pdf' => false,
            'attach_to_folder_pdf' => false,
        ];
        $this->documentFiles = [];
    }

    protected function hydratePlanFormFromSelection(): void
    {
        $row = $this->selectedRow;
        if (! $row) {
            $this->resetPlanForm();

            return;
        }

        if ($row['is_program_point'] ?? false) {
            $this->hydrateProgramPointPlanFormFromSelection($row);

            return;
        }

        $this->planForm = [
            'is_program_point' => false,
            'planned_amount_pln' => $row['planned_pln'],
            'paid_by' => $row['paid_by'] ?? 'office',
            'due_date' => $row['next_due_label'] ?? null,
            'notes' => $row['notes'] ?? null,
            'contractor_id' => $row['contractor_id'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function hydrateProgramPointPlanFormFromSelection(array $row): void
    {
        $point = null;
        if ($this->selectedCostId) {
            $cost = EventSettlementCost::query()->find($this->selectedCostId);
            if ($cost?->source_type === 'program_point' && $cost->source_id) {
                $point = EventProgramPoint::query()
                    ->where('event_id', $this->settlementCostEvent()->id)
                    ->find((int) $cost->source_id);
            }
        }

        $event = $this->settlementCostEvent();
        $includeGratis = (bool) ($point?->include_gratis_in_cost ?? false);
        $includePilot = (bool) ($point?->include_pilot_in_cost ?? false);
        $includeDriver = (bool) ($point?->include_driver_in_cost ?? false);
        $headcount = ProgramPointCostPricing::costHeadcount(
            $event,
            null,
            $includeGratis,
            $includePilot,
            $includeDriver,
        );
        $groupSize = (int) ($point?->group_size ?? 1);
        $quantity = max(1, (int) ($point?->quantity ?? 1));
        $unitPrice = (float) ($point?->unit_price ?? 0);
        $calculated = ProgramPointPricingCalculator::totalPrice(
            $unitPrice,
            $headcount,
            $groupSize <= 0 ? 0 : $groupSize,
            $quantity,
        );

        $this->planForm = [
            'is_program_point' => true,
            'unit_price' => $unitPrice,
            'group_size' => $groupSize,
            'quantity' => $quantity,
            'currency_id' => $row['planned_currency_id'] ?? $point?->currency_id ?? $this->defaultCurrencyId(),
            'convert_to_pln' => (bool) ($row['planned_convert_to_pln'] ?? $point?->convert_to_pln ?? true),
            'include_gratis_in_cost' => $includeGratis,
            'include_pilot_in_cost' => $includePilot,
            'include_driver_in_cost' => $includeDriver,
            'calculated_price' => $calculated,
            'planned_price' => (float) ($point?->planned_price ?? $row['planned_amount'] ?? $calculated),
            'paid_by' => $row['paid_by'] ?? 'office',
            'due_date' => $row['next_due_label'] ?? null,
            'notes' => $row['notes'] ?? null,
            'contractor_id' => $row['contractor_id'] ?? null,
        ];
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function contractorOptions(): array
    {
        return \App\Models\Contractor::filamentSelectOptions();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function currencyOptions(): array
    {
        return Currency::query()
            ->orderByRaw("CASE WHEN symbol = 'PLN' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Currency $c): array => [
                (int) $c->id => trim($c->name.' ('.$c->symbol.')'),
            ])
            ->all();
    }

    #[Computed]
    public function settlementCostDrawerMaxDay(): int
    {
        return max(1, (int) ($this->settlementCostEvent()->duration_days ?? 1));
    }

    public function startEditReservation(): void
    {
        $this->showReservationForm = true;
        $this->showPaymentForm = false;
        $this->showPlanForm = false;
        $this->showDocumentForm = false;
        $this->showCostForm = false;
        $this->hydrateReservationFormFromSelection();
    }

    public function saveReservation(): void
    {
        $point = $this->selectedProgramPointForDrawer();
        if (! $point) {
            Notification::make()
                ->title('Rezerwacja dostępna tylko dla punktów programu')
                ->warning()
                ->send();

            return;
        }

        $reservationId = $this->reservationForm['reservation_id'] ?? null;
        $reservation = $this->resolveReservationForDrawer(
            filled($reservationId) ? (int) $reservationId : null
        );

        $formData = $this->reservationForm;
        unset($formData['reservation_id'], $formData['amount_hint'], $formData['coverage_label']);

        if (array_key_exists('contractor_id', $formData) && blank($formData['contractor_id'])) {
            $formData['contractor_id'] = null;
        }

        if (! empty($this->reservationAttachmentFiles)) {
            $formData['pending_attachments'] = $this->reservationAttachmentFiles;
        }

        $settlementCost = $this->selectedCostId
            ? EventSettlementCost::query()->find($this->selectedCostId)
            : null;

        $linkedPoint = $point;
        if (
            $reservation
            && filled($reservation->program_point_id)
            && (int) $reservation->program_point_id !== (int) $point->id
        ) {
            $linkedPoint = $reservation->programPoint ?? $point;
        }

        try {
            app(UpsertReservationAction::class)(UpsertReservationData::fromForm(
                formData: $formData,
                reservation: $reservation,
                programPoint: $linkedPoint,
                settlementCost: $settlementCost,
            ));
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Nie udało się zapisać rezerwacji')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->showReservationForm = false;
        $this->resetReservationForm();
        unset($this->selectedRow, $this->drawerReservations);
        $this->invalidateSettlementCostCaches();

        Notification::make()
            ->title('Zapisano rezerwację')
            ->success()
            ->send();
    }

    public function deleteReservation(?int $reservationId = null): void
    {
        $point = $this->selectedProgramPointForDrawer();
        if (! $point) {
            Notification::make()
                ->title('Rezerwacja dostępna tylko dla punktów programu')
                ->warning()
                ->send();

            return;
        }

        $reservationId ??= $this->reservationForm['reservation_id'] ?? null;
        $reservation = $this->resolveReservationForDrawer(
            filled($reservationId) ? (int) $reservationId : null
        );

        if (! $reservation) {
            Notification::make()->title('Nie znaleziono rezerwacji')->warning()->send();

            return;
        }

        try {
            $reservation->delete();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Nie udało się usunąć rezerwacji')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->showReservationForm = false;
        $this->resetReservationForm();
        unset($this->selectedRow, $this->drawerReservations);
        $this->invalidateSettlementCostCaches();

        Notification::make()
            ->title('Usunięto rezerwację')
            ->success()
            ->send();
    }

    protected function resetReservationForm(): void
    {
        $this->reservationForm = [
            'reservation_id' => null,
            'contractor_id' => null,
            'booking_reference' => null,
            'status' => 'pending',
            'confirm_by' => null,
            'confirmed_at' => null,
            'deposit_due_at' => null,
            'deposit_paid_at' => null,
            'reserved_amount' => null,
            'participant_count' => 1,
            'currency_id' => null,
            'amount_basis' => 'lump_sum',
            'participant_scope' => 'all',
            'convert_to_pln' => true,
            'amount_hint' => null,
            'coverage_label' => null,
            'office_notes' => null,
        ];
        $this->reservationAttachmentFiles = [];
        $this->resetReservationContractorSearch();
        $this->reservationContractorLabel = '';
    }

    public function updatedReservationContractorSearch(): void
    {
        $this->refreshReservationContractorSearch();
    }

    public function updatedReservationContractorSearchAll(): void
    {
        $this->refreshReservationContractorSearch();
    }

    public function selectReservationContractor(int $contractorId): void
    {
        if ($contractorId <= 0) {
            return;
        }

        $label = $this->reservationContractorSearchResults[$contractorId]
            ?? $this->formatReservationContractorLabel($contractorId);

        $this->reservationForm['contractor_id'] = $contractorId;
        $this->reservationContractorLabel = $label ?? '';
        $this->resetReservationContractorSearch();
    }

    public function clearReservationContractor(): void
    {
        $this->reservationForm['contractor_id'] = null;
        $this->reservationContractorLabel = '';
        $this->resetReservationContractorSearch();
    }

    protected function resetReservationContractorSearch(): void
    {
        $this->reservationContractorSearch = '';
        $this->reservationContractorSearchResults = [];
        $this->showReservationContractorSearchResults = false;
    }

    protected function refreshReservationContractorSearch(): void
    {
        $query = trim($this->reservationContractorSearch);

        if (mb_strlen($query) < 2) {
            $this->reservationContractorSearchResults = [];
            $this->showReservationContractorSearchResults = false;

            return;
        }

        $typeNames = $this->reservationContractorTypeNames();
        $searchAll = $this->reservationContractorSearchAll || $typeNames === [];

        $this->reservationContractorSearchResults = app(ContractorLookupService::class)->searchOptions(
            search: $query,
            typeNames: $typeNames,
            searchAll: $searchAll,
            includeId: filled($this->reservationForm['contractor_id'] ?? null)
                ? (int) $this->reservationForm['contractor_id']
                : null,
        );
        $this->showReservationContractorSearchResults = true;
    }

    /**
     * @return array<int, string>
     */
    protected function reservationContractorTypeNames(): array
    {
        $point = $this->selectedProgramPointForDrawer();
        if (! $point) {
            return [];
        }

        if ((bool) $point->is_hotel) {
            return ContractorType::hotelTypeNames();
        }

        if ((bool) $point->is_transport) {
            return ContractorType::transportTypeNames();
        }

        return [];
    }

    public function reservationContractorHasTypeFilter(): bool
    {
        return $this->reservationContractorTypeNames() !== [];
    }

    protected function syncReservationContractorLabel(?int $contractorId): void
    {
        if (! $contractorId) {
            $this->reservationContractorLabel = '';

            return;
        }

        $this->reservationContractorLabel = $this->formatReservationContractorLabel($contractorId) ?? ('#'.$contractorId);
    }

    protected function formatReservationContractorLabel(int $contractorId): ?string
    {
        $contractor = Contractor::query()->find($contractorId);

        return $contractor
            ? app(ContractorLookupService::class)->formatOptionLabel($contractor)
            : null;
    }

    protected function hydrateReservationFormFromSelection(): void
    {
        $point = $this->selectedProgramPointForDrawer();
        if (! $point) {
            $this->resetReservationForm();

            return;
        }

        $settlementCost = $this->selectedCostId
            ? EventSettlementCost::query()->find($this->selectedCostId)
            : null;

        $reservation = $this->resolveReservationForDrawer();
        $defaults = ReservationFormDefaults::forProgramPoint($point, $settlementCost, $reservation);
        $depositDue = $defaults['deposit_due_at']
            ?? (filled($this->planForm['due_date'] ?? null) ? (string) $this->planForm['due_date'] : null);
        $contractorId = $reservation?->contractor_id
            ?? $point->contractor_id
            ?? $settlementCost?->contractor_id;
        $coverage = ProgramPointReservationGroup::coverageLabel($point);

        if (! $reservation) {
            $this->reservationForm = [
                'reservation_id' => null,
                'contractor_id' => $contractorId,
                'booking_reference' => null,
                'status' => $defaults['status'],
                'confirm_by' => null,
                'confirmed_at' => null,
                'deposit_due_at' => $depositDue,
                'deposit_paid_at' => null,
                'reserved_amount' => $defaults['reserved_amount'],
                'participant_count' => $defaults['participant_count'],
                'currency_id' => $defaults['currency_id'],
                'amount_basis' => $defaults['amount_basis'],
                'participant_scope' => $defaults['participant_scope'],
                'convert_to_pln' => $defaults['convert_to_pln'],
                'amount_hint' => $defaults['amount_hint'],
                'coverage_label' => $coverage,
                'office_notes' => null,
            ];
            $this->reservationAttachmentFiles = [];
            $this->resetReservationContractorSearch();
            $this->syncReservationContractorLabel(filled($contractorId) ? (int) $contractorId : null);

            return;
        }

        $this->reservationForm = [
            'reservation_id' => $reservation->id,
            'contractor_id' => $contractorId,
            'booking_reference' => $reservation->booking_reference,
            'status' => $reservation->status,
            'confirm_by' => $reservation->confirm_by?->toDateString(),
            'confirmed_at' => $reservation->confirmed_at?->toDateString(),
            'deposit_due_at' => $reservation->deposit_due_at?->toDateString() ?? $depositDue,
            'deposit_paid_at' => $reservation->deposit_paid_at?->toDateString(),
            'reserved_amount' => $reservation->reserved_amount !== null
                ? round((float) $reservation->reserved_amount, 2)
                : $defaults['reserved_amount'],
            'participant_count' => max(1, (int) ($reservation->participant_count ?? $defaults['participant_count'])),
            'currency_id' => $reservation->currency_id ?? $defaults['currency_id'],
            'amount_basis' => $reservation->amount_basis ?? $defaults['amount_basis'],
            'participant_scope' => $reservation->participant_scope ?? $defaults['participant_scope'],
            'convert_to_pln' => (bool) ($reservation->convert_to_pln ?? $defaults['convert_to_pln']),
            'amount_hint' => $reservation->reserved_amount !== null ? null : $defaults['amount_hint'],
            'coverage_label' => $coverage,
            'office_notes' => $reservation->office_notes,
        ];
        $this->reservationAttachmentFiles = [];
        $this->resetReservationContractorSearch();
        $this->syncReservationContractorLabel(filled($contractorId) ? (int) $contractorId : null);
    }

    protected function selectedProgramPointForDrawer(): ?EventProgramPoint
    {
        if (! $this->selectedCostId) {
            return null;
        }

        $cost = EventSettlementCost::query()->find($this->selectedCostId);
        if (! $cost) {
            return null;
        }

        if ($cost->source_type === 'program_point' && $cost->source_id) {
            return EventProgramPoint::query()
                ->where('event_id', $this->settlementCostEvent()->id)
                ->with(['reservations.contractor', 'sharedReservation.contractor', 'contractor', 'hotelStays.reservation.contractor'])
                ->find((int) $cost->source_id);
        }

        if ($cost->source_type === HotelStaySettlementSync::SOURCE_HOTEL && $cost->source_id) {
            $stay = EventHotelStay::query()
                ->where('event_id', $this->settlementCostEvent()->id)
                ->where('contractor_id', (int) $cost->source_id)
                ->whereNotNull('event_program_point_id')
                ->orderBy('day')
                ->first();

            if (! $stay?->event_program_point_id) {
                return null;
            }

            return EventProgramPoint::query()
                ->where('event_id', $this->settlementCostEvent()->id)
                ->with(['reservations.contractor', 'sharedReservation.contractor', 'contractor', 'hotelStays.reservation.contractor'])
                ->find((int) $stay->event_program_point_id);
        }

        if ($cost->source_type === HotelStaySettlementSync::SOURCE_STAY && $cost->source_id) {
            $stay = EventHotelStay::query()
                ->where('event_id', $this->settlementCostEvent()->id)
                ->find((int) $cost->source_id);

            if (! $stay?->event_program_point_id) {
                return null;
            }

            return EventProgramPoint::query()
                ->where('event_id', $this->settlementCostEvent()->id)
                ->with(['reservations.contractor', 'sharedReservation.contractor', 'contractor', 'hotelStays.reservation.contractor'])
                ->find((int) $stay->event_program_point_id);
        }

        return null;
    }

    protected function resolveReservationForDrawer(?int $reservationId = null): ?Reservation
    {
        if ($this->selectedCostId) {
            $cost = EventSettlementCost::query()->find($this->selectedCostId);
            if ($cost && in_array($cost->source_type, [HotelStaySettlementSync::SOURCE_HOTEL, HotelStaySettlementSync::SOURCE_STAY], true)) {
                if ($reservationId) {
                    $found = Reservation::query()
                        ->where('event_id', $this->settlementCostEvent()->id)
                        ->whereKey($reservationId)
                        ->first();

                    if ($found) {
                        return $found;
                    }
                }

                if ($cost->reservation_id) {
                    return Reservation::query()->find((int) $cost->reservation_id);
                }

                if ($cost->source_type === HotelStaySettlementSync::SOURCE_HOTEL && $cost->source_id) {
                    $stay = EventHotelStay::query()
                        ->where('event_id', $this->settlementCostEvent()->id)
                        ->where('contractor_id', (int) $cost->source_id)
                        ->orderBy('day')
                        ->first();

                    return $stay ? app(HotelStayReservationSync::class)->findForStay($stay) : null;
                }
            }
        }

        $point = $this->selectedProgramPointForDrawer();
        if (! $point) {
            return null;
        }

        if ($reservationId) {
            $found = Reservation::query()
                ->where('event_id', $point->event_id)
                ->whereKey($reservationId)
                ->first();

            if ($found) {
                return $found;
            }
        }

        return $point->latestVisibleReservation()
            ?? $point->reservations()->latest('id')->first();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Reservation>
     */
    #[Computed]
    public function drawerReservations(): \Illuminate\Support\Collection
    {
        $point = $this->selectedProgramPointForDrawer();
        if (! $point) {
            return collect();
        }

        $own = $point->reservations;
        $visible = $point->latestVisibleReservation();

        if ($visible && $own->doesntContain(fn (Reservation $reservation): bool => (int) $reservation->id === (int) $visible->id)) {
            return $own->prepend($visible)->values();
        }

        return $own;
    }
}
