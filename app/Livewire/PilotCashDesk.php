<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\HandlesPilotExpenseLedger;
use App\Models\Currency;
use App\Models\Event;
use App\Services\PilotAccessService;
use App\Services\PilotAdvanceService;
use App\Services\PilotSettlementService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Wspólny ekran „Gotówka dla pilota”: wypłata z biura, saldo, wymiana, wydatki.
 * Używany w admin (/finance/pilot-cash) i w panelu pilota.
 */
class PilotCashDesk extends Component
{
    use HandlesPilotExpenseLedger;
    use WithFileUploads;

    public Event $event;

    /** admin|pilot */
    public string $context = 'admin';

    public bool $compact = false;

    /** Czy pokazać blok „Wypłata gotówki pilotowi” (w rozliczeniu pilota jest osobna sekcja zaliczki). */
    public bool $showOfficePayoutBlock = true;

    /**
     * Gdy true (Operacje → Pilot), sekcja wymiany szanuje flagę portalu.
     * Finanse / modal biura zostawiają false — pełny dostęp biura.
     */
    public bool $respectPortalVisibility = false;

    /** Na stronie Operacje → Pilot: wydatki w akordeonie (domyślnie otwartym). */
    public bool $collapseExpenses = false;

    /** Gdy false — ukryj blok wymiany w tym instancji (np. przeniesiony do prawej kolumny). */
    public bool $includeCurrencyExchange = true;

    /** all|exchange — modal / prawa kolumna pokazuje tylko sekcję wymiany. */
    public string $focus = 'all';

    public string $expenseName = '';

    public string $expenseAmount = '';

    public string $expenseInvoiceNumber = '';

    public ?int $expenseCurrencyId = null;

    public string $expenseNotes = '';

    public ?int $exchangeFromCurrencyId = null;

    public ?int $exchangeToCurrencyId = null;

    public string $exchangeFromAmount = '';

    public string $exchangeToAmount = '';

    public string $exchangeRate = '';

    public string $exchangeNotes = '';

    public ?int $editingExchangeId = null;

    public ?int $payoutCurrencyId = null;

    public string $payoutAmount = '';

    public string $payoutProvidedAt = '';

    public string $payoutComment = '';

    public ?int $editingPayoutCurrencyId = null;

    public function mount(
        Event $event,
        string $context = 'admin',
        bool $compact = false,
        bool $showOfficePayoutBlock = true,
        bool $respectPortalVisibility = false,
        bool $collapseExpenses = false,
        bool $includeCurrencyExchange = true,
        string $focus = 'all',
    ): void {
        $this->event = $event->loadMissing(['pilotFundsPaidByUser', 'pilotAdvancePaidCurrency']);
        $this->context = $context;
        $this->compact = $compact;
        $this->showOfficePayoutBlock = $showOfficePayoutBlock && $focus !== 'exchange';
        $this->respectPortalVisibility = $respectPortalVisibility;
        $this->collapseExpenses = $collapseExpenses;
        $this->includeCurrencyExchange = $includeCurrencyExchange;
        $this->focus = in_array($focus, ['all', 'exchange'], true) ? $focus : 'all';

        if ($context === 'pilot') {
            abort_unless(Auth::user()?->can('viewPilotDetails', $event), 403);
        } else {
            abort_unless(Auth::user()?->can('update', $event) || Auth::user()?->can('view', $event), 403);
        }

        $this->expenseCurrencyId = Currency::query()->where('code', 'PLN')->value('id')
            ?? Currency::defaultPlnId();
        $this->payoutCurrencyId = $this->expenseCurrencyId;
        $this->payoutProvidedAt = now()->format('Y-m-d');
        $this->mountExpenseLedgerState();
    }

    public function getEditableProperty(): bool
    {
        if ($this->context === 'pilot') {
            $hasAccess = app(PilotAccessService::class)->hasFullAccess($this->event, Auth::user());

            return $hasAccess && $this->settlement->isEditableByPilot();
        }

        return Auth::user()?->can('update', $this->event) ?? false;
    }

    public function getCanRecordPayoutProperty(): bool
    {
        if ($this->context !== 'admin') {
            return false;
        }

        $user = Auth::user();
        if (! $user) {
            return false;
        }

        return $user->can('manageFinance', $this->event) || $user->can('update', $this->event);
    }

    public function getSettlementProperty(): \App\Models\EventSettlement
    {
        return app(PilotSettlementService::class)->getOrCreateSettlement($this->event);
    }

    public function getCurrencyExchangesProperty()
    {
        return app(PilotSettlementService::class)->currencyExchanges($this->event);
    }

    public function getShowCurrencyExchangeProperty(): bool
    {
        if ($this->context === 'pilot' || $this->respectPortalVisibility) {
            return $this->event->showsPilotCurrencyExchange();
        }

        return true;
    }

    #[On('pilot-portal-visibility-updated')]
    public function refreshPortalVisibility(?int $eventId = null): void
    {
        if ($eventId !== null && (int) $this->event->getKey() !== $eventId) {
            return;
        }

        $this->event = $this->event->fresh([
            'pilotFundsPaidByUser',
            'pilotAdvancePaidCurrency',
        ]) ?? $this->event;
    }

    public function getOfficePayoutsProperty()
    {
        return $this->settlement->pilotCashPreparations()
            ->with('currency')
            ->whereNotNull('provided_amount')
            ->where('provided_amount', '>', 0)
            ->orderBy('currency_id')
            ->get();
    }

    public function editOfficePayout(int $currencyId): void
    {
        abort_unless($this->canRecordPayout, 403);

        $cash = $this->officePayouts->firstWhere('currency_id', $currencyId);
        abort_unless($cash !== null, 404);

        $this->editingPayoutCurrencyId = $currencyId;
        $this->payoutCurrencyId = $currencyId;
        $this->payoutAmount = (string) $cash->provided_amount;
        $this->payoutProvidedAt = $cash->provided_at
            ? $cash->provided_at->format('Y-m-d')
            : ($this->event->pilot_funds_paid_at?->format('Y-m-d') ?: now()->format('Y-m-d'));
        $this->payoutComment = (string) ($cash->notes ?? '');
    }

    public function cancelEditOfficePayout(): void
    {
        $this->editingPayoutCurrencyId = null;
        $this->reset(['payoutAmount', 'payoutComment']);
        $this->payoutProvidedAt = now()->format('Y-m-d');
        $this->payoutCurrencyId = Currency::query()->where('code', 'PLN')->value('id')
            ?? Currency::defaultPlnId();
    }

    public function deleteOfficePayout(int $currencyId): void
    {
        abort_unless($this->canRecordPayout, 403);

        try {
            app(PilotAdvanceService::class)->clearOfficeCashPayout($this->event, $currencyId);
        } catch (\InvalidArgumentException $e) {
            $this->addError('payoutAmount', $e->getMessage());

            return;
        }

        $this->event = $this->event->fresh([
            'pilotAdvanceLines.currency',
            'pilotAdvancePaidCurrency',
            'pilotFundsPaidByUser',
        ]) ?? $this->event;

        if ($this->editingPayoutCurrencyId === $currencyId) {
            $this->cancelEditOfficePayout();
        }

        $this->loadCashReportingFields();
        $this->notifyLedger('Usunięto zaliczkę');
    }

    public function saveOfficePayout(): void
    {
        abort_unless($this->canRecordPayout, 403);

        $this->payoutAmount = str_replace([' ', ','], ['', '.'], trim($this->payoutAmount));

        $this->validate([
            'payoutCurrencyId' => 'required|exists:currencies,id',
            'payoutAmount' => 'required|numeric|min:0.01',
            'payoutProvidedAt' => 'required|date',
            'payoutComment' => 'nullable|string|max:2000',
        ], [
            'payoutCurrencyId.required' => 'Wybierz walutę zaliczki.',
            'payoutAmount.required' => 'Podaj kwotę zaliczki.',
            'payoutProvidedAt.required' => 'Podaj datę wypłaty.',
        ]);

        try {
            app(PilotAdvanceService::class)->recordOfficeCashPayout($this->event, [
                'amount' => $this->payoutAmount,
                'currency_id' => $this->payoutCurrencyId,
                'provided_at' => $this->payoutProvidedAt,
                'comment' => $this->payoutComment !== '' ? $this->payoutComment : null,
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->addError('payoutAmount', $e->getMessage());

            return;
        }

        $this->event = $this->event->fresh([
            'pilotAdvanceLines.currency',
            'pilotAdvancePaidCurrency',
            'pilotFundsPaidByUser',
        ]) ?? $this->event;

        $wasEditing = $this->editingPayoutCurrencyId !== null;
        $this->cancelEditOfficePayout();
        $this->loadCashReportingFields();
        $this->notifyLedger(
            $wasEditing
                ? 'Zaktualizowano zaliczkę'
                : 'Dodano zaliczkę pilotowi'
        );
    }

    public function addExpense(): void
    {
        abort_unless($this->editable, 403);

        $this->expenseAmount = str_replace([' ', ','], ['', '.'], trim($this->expenseAmount));

        $this->validate([
            'expenseName' => 'required|string|max:255',
            'expenseAmount' => 'required|numeric|min:0.01',
            'expenseCurrencyId' => 'nullable|exists:currencies,id',
            'expenseInvoiceNumber' => 'nullable|string|max:255',
            'expenseNotes' => 'nullable|string|max:2000',
        ]);

        app(PilotSettlementService::class)->addExpense($this->event, [
            'name' => $this->expenseName,
            'actual_amount' => $this->expenseAmount,
            'actual_currency_id' => $this->expenseCurrencyId,
            'invoice_number' => $this->expenseInvoiceNumber ?: null,
            'notes' => $this->expenseNotes ?: null,
        ]);

        $this->reset(['expenseName', 'expenseAmount', 'expenseInvoiceNumber', 'expenseNotes']);
        $this->loadCashReportingFields();
        $this->notifyLedger('Dodano wydatek pilota');
    }

    public function updatedExchangeToAmount(mixed $value): void
    {
        $this->syncExchangeFromAmountFromRate();
    }

    public function updatedExchangeRate(mixed $value): void
    {
        $this->syncExchangeFromAmountFromRate();
    }

    protected function syncExchangeFromAmountFromRate(): void
    {
        $to = (float) str_replace([' ', ','], ['', '.'], trim((string) $this->exchangeToAmount));
        $rate = (float) str_replace([' ', ','], ['', '.'], trim((string) $this->exchangeRate));

        if ($to > 0 && $rate > 0) {
            $this->exchangeFromAmount = (string) round($to * $rate, 2);
        }
    }

    public function editExchange(int $exchangeId): void
    {
        abort_unless($this->editable, 403);
        abort_unless($this->showCurrencyExchange, 403);

        $exchange = app(PilotSettlementService::class)
            ->currencyExchanges($this->event)
            ->firstWhere('id', $exchangeId);

        abort_unless($exchange !== null, 404);

        $this->editingExchangeId = (int) $exchange->id;
        $this->exchangeFromCurrencyId = (int) $exchange->from_currency_id;
        $this->exchangeToCurrencyId = (int) $exchange->to_currency_id;
        $this->exchangeFromAmount = (string) $exchange->from_amount;
        $this->exchangeToAmount = (string) $exchange->to_amount;
        $this->exchangeRate = $exchange->exchange_rate !== null ? (string) $exchange->exchange_rate : '';
        $this->exchangeNotes = (string) ($exchange->notes ?? '');
    }

    public function cancelEditExchange(): void
    {
        $this->editingExchangeId = null;
        $this->reset([
            'exchangeFromCurrencyId',
            'exchangeToCurrencyId',
            'exchangeFromAmount',
            'exchangeToAmount',
            'exchangeRate',
            'exchangeNotes',
        ]);
    }

    public function deleteExchange(int $exchangeId): void
    {
        abort_unless($this->editable, 403);
        abort_unless($this->showCurrencyExchange, 403);

        $exchange = app(PilotSettlementService::class)
            ->currencyExchanges($this->event)
            ->firstWhere('id', $exchangeId);

        abort_unless($exchange !== null, 404);

        app(PilotSettlementService::class)->deleteCurrencyExchange($this->event, $exchange);

        if ($this->editingExchangeId === $exchangeId) {
            $this->cancelEditExchange();
        }

        $this->loadCashReportingFields();
        $this->notifyLedger('Usunięto wymianę walut');
    }

    public function saveExchange(): void
    {
        abort_unless($this->editable, 403);
        abort_unless($this->showCurrencyExchange, 403);

        $this->exchangeFromAmount = str_replace([' ', ','], ['', '.'], trim($this->exchangeFromAmount));
        $this->exchangeToAmount = str_replace([' ', ','], ['', '.'], trim($this->exchangeToAmount));
        $this->exchangeRate = str_replace([' ', ','], ['', '.'], trim($this->exchangeRate));

        $this->syncExchangeFromAmountFromRate();

        $this->validate([
            'exchangeFromCurrencyId' => 'required|exists:currencies,id',
            'exchangeToCurrencyId' => 'required|exists:currencies,id|different:exchangeFromCurrencyId',
            'exchangeFromAmount' => 'required|numeric|min:0.01',
            'exchangeToAmount' => 'required|numeric|min:0.01',
            'exchangeRate' => 'nullable|numeric|min:0.00001',
            'exchangeNotes' => 'nullable|string|max:2000',
        ]);

        $payload = [
            'from_currency_id' => $this->exchangeFromCurrencyId,
            'to_currency_id' => $this->exchangeToCurrencyId,
            'from_amount' => $this->exchangeFromAmount,
            'to_amount' => $this->exchangeToAmount,
            'exchange_rate' => $this->exchangeRate !== '' ? $this->exchangeRate : null,
            'notes' => $this->exchangeNotes !== '' ? $this->exchangeNotes : null,
        ];

        $service = app(PilotSettlementService::class);

        try {
            if ($this->editingExchangeId) {
                $exchange = $service->currencyExchanges($this->event)->firstWhere('id', $this->editingExchangeId);
                abort_unless($exchange !== null, 404);
                $service->updateCurrencyExchange($this->event, $exchange, $payload);
                $message = 'Zaktualizowano wymianę walut';
            } else {
                $service->recordCurrencyExchange($this->event, $payload);
                $message = 'Zapisano wymianę walut';
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?: $e->getMessage();
            $this->addError('exchangeFromAmount', $msg);

            return;
        }

        $this->cancelEditExchange();
        $this->loadCashReportingFields();
        $this->notifyLedger($message);
    }

    public function prefillPayoutFromCalculation(): void
    {
        abort_unless($this->canRecordPayout, 403);

        $rows = app(PilotSettlementService::class)
            ->getCashReconciliation($this->settlement)
            ->sortByDesc(fn ($row) => (float) ($row->needed ?? 0))
            ->values();

        $best = $rows->first(fn ($row) => (float) ($row->needed ?? 0) > 0.009);

        if (! $best) {
            $this->addError('payoutAmount', 'Brak wyliczonej gotówki do przygotowania z punktów programu.');

            return;
        }

        $this->payoutCurrencyId = (int) $best->currency_id;
        $this->payoutAmount = (string) round((float) $best->needed, 2);
        $this->notifyLedger('Uzupełniono kwotę z wyliczenia (Do przygotowania)');
    }

    protected function notifyLedger(string $message): void
    {
        Notification::make()->title($message)->success()->send();
        session()->flash('status', $message);
    }

    public function render()
    {
        return view('livewire.pilot-cash-desk', [
            'settlement' => $this->settlement,
        ]);
    }
}
