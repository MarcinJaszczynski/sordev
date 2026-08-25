<?php

namespace App\Filament\Pages;

use App\Actions\Finance\CompletePendingPaymentAction;
use App\Actions\Finance\GenerateInstallmentPaymentLinkAction;
use App\Actions\Finance\RecordSettlementCostPaymentAction;
use App\Data\CompletePendingPaymentData;
use App\Data\GenerateInstallmentPaymentLinkData;
use App\Data\RecordSettlementCostPaymentData;
use App\Filament\Actions\HelpArticleAction;
use App\Filament\Concerns\AuthorizesVendorInvoices;
use App\Models\EventSettlementCost;
use App\Services\PendingPaymentAggregator;
use App\Support\FilamentNavigation;
use App\Support\FinanceModuleNavigation;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;

class PendingPaymentsInboxPage extends Page
{
    use AuthorizesVendorInvoices;

    private const SESSION_KEY = 'pending_payments_inbox';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static string $view = 'filament.pages.pending-payments-inbox';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_FINANCE;

    protected static ?string $navigationLabel = 'Skrzynka płatności';

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public string $displayMode = 'list';

    public string $typeFilter = 'all';

    public string $payerFilter = 'all';

    public bool $showCostPaymentModal = false;

    public ?string $completingRowId = null;

    /** @var array<string, mixed> */
    public array $costPaymentForm = [];

    public ?string $costPaymentContextTitle = null;

    public ?string $costPaymentContextMeta = null;

    /** @var array{entries: array<int, array<string, mixed>>, truncated: bool}|null */
    protected ?array $aggregatedInboxCache = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && ($user->hasRole(['admin', 'super_admin', 'ksiegowosc']) || static::canViewInvoices());
    }

    public function mount(): void
    {
        $saved = session(self::SESSION_KEY, []);

        if (is_array($saved)) {
            $this->displayMode = in_array($saved['displayMode'] ?? null, ['list', 'calendar'], true)
                ? $saved['displayMode']
                : 'list';
            $this->typeFilter = is_string($saved['typeFilter'] ?? null) ? $saved['typeFilter'] : 'all';
            $this->payerFilter = is_string($saved['payerFilter'] ?? null) ? $saved['payerFilter'] : 'all';
        }

        $this->resetCostPaymentForm();
    }

    protected function getHeaderActions(): array
    {
        return [
            HelpArticleAction::make('wplaty-i-linki'),
        ];
    }

    public function updatedDisplayMode(string $value): void
    {
        $this->persistFilters();
    }

    public function updatedTypeFilter(string $value): void
    {
        $this->persistFilters();
        $this->forgetInboxCache();
    }

    public function updatedPayerFilter(string $value): void
    {
        $this->persistFilters();
        $this->forgetInboxCache();
    }

    public function getTitle(): string
    {
        return 'Skrzynka płatności';
    }

    public function getNavigationTabs(): array
    {
        return FinanceModuleNavigation::tabs('pending-payments');
    }

    public function beginComplete(string $rowId): void
    {
        $row = $this->findInboxRow($rowId);
        if (! $row) {
            Notification::make()->title('Nie znaleziono pozycji')->warning()->send();

            return;
        }

        if (($row['complete_mode'] ?? 'quick') === 'cost_payment_form' || str_starts_with($rowId, 'cost-')) {
            $this->openCostPaymentModal($row);

            return;
        }

        $this->markCompleted($rowId);
    }

    public function openCostPaymentModal(array $row): void
    {
        $this->completingRowId = (string) $row['id'];
        $this->costPaymentContextTitle = (string) ($row['title'] ?? 'Wpłata kosztowa');
        $this->costPaymentContextMeta = trim(implode(' · ', array_filter([
            $row['event_label'] ?? $row['event_name'] ?? null,
            $row['context'] ?? null,
            isset($row['amount_label']) ? 'Do zapłaty: '.$row['amount_label'] : null,
        ])));

        $paidBy = (string) ($row['suggested_paid_by'] ?? $row['paid_by'] ?? 'office');
        $this->costPaymentForm = [
            'amount_pln' => round((float) ($row['suggested_amount_pln'] ?? $row['amount'] ?? 0), 2),
            'payment_method' => $paidBy === 'pilot' ? 'cash' : 'transfer',
            'paid_by' => $paidBy,
            'advance_type' => 'advance',
            'paid_at' => now()->toDateString(),
            'due_date' => $row['suggested_due_date'] ?? $row['due_date'] ?? null,
            'document_number' => '',
            'notes' => '',
        ];
        $this->showCostPaymentModal = true;
    }

    public function closeCostPaymentModal(): void
    {
        $this->showCostPaymentModal = false;
        $this->completingRowId = null;
        $this->costPaymentContextTitle = null;
        $this->costPaymentContextMeta = null;
        $this->resetCostPaymentForm();
    }

    public function updatedCostPaymentFormPaidBy(?string $value): void
    {
        if ($value === 'pilot') {
            $this->costPaymentForm['payment_method'] = 'cash';
        }
    }

    public function saveCostPayment(): void
    {
        $this->validate([
            'costPaymentForm.amount_pln' => ['required', 'numeric', 'min:0.01'],
            'costPaymentForm.payment_method' => ['required', 'in:cash,transfer,card,other'],
            'costPaymentForm.paid_by' => ['required', 'in:office,pilot'],
            'costPaymentForm.advance_type' => ['required', EventSettlementCost::userSelectableAdvanceTypesValidationRule()],
        ], [], [
            'costPaymentForm.amount_pln' => 'kwota',
            'costPaymentForm.payment_method' => 'metoda',
            'costPaymentForm.paid_by' => 'płatnik',
            'costPaymentForm.advance_type' => 'rodzaj',
        ]);

        $rowId = (string) $this->completingRowId;
        if (! str_starts_with($rowId, 'cost-')) {
            Notification::make()->title('Ta pozycja nie obsługuje formularza wpłaty')->warning()->send();

            return;
        }

        $cost = EventSettlementCost::query()
            ->with(['settlement', 'plannedCurrency', 'contractor'])
            ->find((int) substr($rowId, 5));

        if (! $cost) {
            Notification::make()->title('Nie znaleziono kosztu')->danger()->send();

            return;
        }

        $plan = $cost->resolvePlanCostForPayment();
        if (EventSettlementCost::isPaymentSourceType($plan->source_type)) {
            Notification::make()
                ->title('Brak kosztu do zaksięgowania wpłaty')
                ->body('Otwórz Finanse imprezy i dodaj wpłatę ręcznie.')
                ->warning()
                ->send();

            return;
        }

        $form = $this->costPaymentForm;
        $paidBy = (string) ($form['paid_by'] ?? 'office');
        $method = $paidBy === 'pilot' ? 'cash' : (string) ($form['payment_method'] ?? 'transfer');

        try {
            app(RecordSettlementCostPaymentAction::class)(new RecordSettlementCostPaymentData(
                planCost: $plan,
                amountPln: (float) $form['amount_pln'],
                paymentMethod: $method,
                paidBy: $paidBy,
                advanceType: (string) $form['advance_type'],
                paidAt: filled($form['paid_at'] ?? null) ? Carbon::parse($form['paid_at']) : now(),
                dueDate: filled($form['due_date'] ?? null) ? Carbon::parse($form['due_date']) : null,
                documentNumber: filled($form['document_number'] ?? null) ? (string) $form['document_number'] : null,
                notes: filled($form['notes'] ?? null) ? (string) $form['notes'] : null,
                paidByUserId: auth()->id(),
                currencyId: $plan->planned_currency_id,
            ));
        } catch (\Throwable $e) {
            Notification::make()->title('Nie udało się zapisać wpłaty')->body($e->getMessage())->danger()->send();

            return;
        }

        $this->closeCostPaymentModal();
        $this->forgetInboxCache();

        Notification::make()
            ->title('Zaksięgowano wpłatę')
            ->body('Wpis trafił do Finansów imprezy (stos wpłat).')
            ->success()
            ->send();
    }

    public function markCompleted(string $rowId): void
    {
        if (str_starts_with($rowId, 'cost-')) {
            $row = $this->findInboxRow($rowId);
            if ($row) {
                $this->openCostPaymentModal($row);

                return;
            }
        }

        app(CompletePendingPaymentAction::class)(
            new CompletePendingPaymentData(rowId: $rowId)
        );

        $this->forgetInboxCache();

        Notification::make()
            ->title('Oznaczono jako wykonane')
            ->success()
            ->send();
    }

    public function copyPaymentLink(string $rowId): void
    {
        $schedule = $this->resolveScheduleFromRowId($rowId);
        if (! $schedule) {
            Notification::make()->title('Link dostępny tylko dla rat umów')->warning()->send();

            return;
        }

        $result = app(GenerateInstallmentPaymentLinkAction::class)(
            new GenerateInstallmentPaymentLinkData(schedule: $schedule)
        );

        $this->dispatch('copy-to-clipboard', text: $result['url']);

        Notification::make()
            ->title('Link płatności wygenerowany')
            ->body($result['url'])
            ->success()
            ->send();
    }

    protected function resolveScheduleFromRowId(string $rowId): ?\Illuminate\Database\Eloquent\Model
    {
        if (str_starts_with($rowId, 'contract-')) {
            return \App\Models\ContractPaymentSchedule::query()->find((int) substr($rowId, 9));
        }

        if (str_starts_with($rowId, 'agreement-')) {
            return \App\Models\EventAgreementPaymentSchedule::query()->find((int) substr($rowId, 10));
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    protected function findInboxRow(string $rowId): ?array
    {
        foreach ($this->inboxEntries as $row) {
            if (($row['id'] ?? null) === $rowId) {
                return $row;
            }
        }

        return null;
    }

    protected function resetCostPaymentForm(): void
    {
        $this->costPaymentForm = [
            'amount_pln' => null,
            'payment_method' => 'transfer',
            'paid_by' => 'office',
            'advance_type' => 'advance',
            'paid_at' => now()->toDateString(),
            'due_date' => null,
            'document_number' => '',
            'notes' => '',
        ];
    }

    #[Computed]
    public function wasTruncated(): bool
    {
        return (bool) ($this->aggregatedInbox()['truncated'] ?? false);
    }

    #[Computed]
    public function inboxEntries(): array
    {
        return $this->aggregatedInbox()['entries'];
    }

    #[Computed]
    public function calendarEvents(): array
    {
        return collect($this->inboxEntries)
            ->map(fn (array $row): array => [
                'id' => $row['id'],
                'title' => trim(($row['event_label'] ?? $row['event_code'] ?? '').' · '.$row['title'], ' ·'),
                'start' => $row['due_date'],
                'backgroundColor' => $row['color'],
                'borderColor' => $row['color'],
                'url' => $row['url'],
                'extendedProps' => [
                    'descriptionPreview' => $row['context'] ?? null,
                ],
            ])
            ->all();
    }

    /**
     * @return array{entries: array<int, array<string, mixed>>, truncated: bool}
     */
    protected function aggregatedInbox(): array
    {
        if ($this->aggregatedInboxCache !== null) {
            return $this->aggregatedInboxCache;
        }

        $aggregator = app(PendingPaymentAggregator::class);
        $entries = $aggregator->collect();

        if ($this->typeFilter !== 'all') {
            $entries = $entries->where('type', $this->typeFilter);
        }

        if ($this->payerFilter !== 'all') {
            $entries = $entries->where('paid_by', $this->payerFilter);
        }

        return $this->aggregatedInboxCache = [
            'entries' => $entries->values()->all(),
            'truncated' => $aggregator->wasTruncated(),
        ];
    }

    protected function forgetInboxCache(): void
    {
        $this->aggregatedInboxCache = null;
        unset($this->inboxEntries, $this->calendarEvents, $this->wasTruncated);
    }

    protected function persistFilters(): void
    {
        session([
            self::SESSION_KEY => [
                'displayMode' => $this->displayMode,
                'typeFilter' => $this->typeFilter,
                'payerFilter' => $this->payerFilter,
            ],
        ]);
    }
}
