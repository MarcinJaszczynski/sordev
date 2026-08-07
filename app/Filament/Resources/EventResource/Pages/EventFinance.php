<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventResource\Pages;

use App\Actions\Finance\AttachSettlementCostDocumentAction;
use App\Actions\Finance\RecordSettlementCostPaymentAction;
use App\Actions\Finance\UpdateSettlementCostPlanAction;
use App\Data\RecordSettlementCostPaymentData;
use App\Data\UpdateSettlementCostPlanData;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Concerns\HasEventFinanceSubNavigation;
use App\Filament\Resources\EventResource\Concerns\InteractsWithEventRecord;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\EventSettlementCostGroup;
use App\Models\EventSettlementDocument;
use App\Services\EventFinanceOverviewService;
use App\Services\EventSettlementCostGroupService;
use App\Services\SettlementPaymentHealthService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class EventFinance extends Page
{
    use HasEventFinanceSubNavigation;
    use InteractsWithEventRecord;
    use WithFileUploads;

    protected static string $resource = EventResource::class;

    protected static string $view = 'filament.resources.event-resource.pages.event-finance';

    protected static ?string $navigationLabel = 'Finanse';

    /** H1 = aktywna sekcja nested (primary: Finanse). */
    protected static ?string $title = 'Koszty';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    #[Url]
    public string $filter = EventFinanceOverviewService::FILTER_ALL;

    #[Url]
    public string $groupFilter = 'all';

    public ?int $selectedCostId = null;

    /** @var array<int, bool> */
    public array $collapsedGroups = [];

    /** @var list<int|string> */
    public array $selectedCostIds = [];

    public ?string $bulkTargetGroupId = null;

    public string $newGroupName = '';

    public ?int $renamingGroupId = null;

    public string $renameGroupName = '';

    /** @var array<string, mixed> */
    public array $paymentForm = [];

    /** @var array<string, mixed> */
    public array $planForm = [];

    /** @var array<string, mixed> */
    public array $documentForm = [];

    /** @var array<int, TemporaryUploadedFile> */
    public array $documentFiles = [];

    public bool $showPaymentForm = false;

    public bool $showPlanForm = false;

    public bool $showDocumentForm = false;

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return true;
    }

    /**
     * @param  array<string, mixed>  $urlParameters
     * @return array<\Filament\Navigation\NavigationItem>
     */
    public static function getNavigationItems(array $urlParameters = []): array
    {
        return [
            \Filament\Navigation\NavigationItem::make(static::getNavigationLabel())
                ->group(static::getNavigationGroup())
                ->parentItem(static::getNavigationParentItem())
                ->icon(static::getNavigationIcon())
                ->activeIcon(static::getActiveNavigationIcon())
                ->isActiveWhen(fn (): bool => collect(static::financeRouteNames())
                    ->contains(fn (string $routeName): bool => request()->routeIs($routeName)))
                ->sort(static::getNavigationSort())
                ->badge(static::getNavigationBadge(), color: static::getNavigationBadgeColor())
                ->url(EventResource::getUrl('finance', $urlParameters)),
        ];
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(static::getResource()::canEdit($this->getRecord()), 403);
        $event = $this->getRecord();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $hasPlanCosts = app(SettlementPaymentHealthService::class)
            ->listPlanCosts($settlement->costs()->get())
            ->isNotEmpty();

        if (! $hasPlanCosts) {
            $event->refreshActiveSettlementCosts();
        }

        $this->resetPaymentForm();
        $this->resetPlanForm();
        $this->resetDocumentForm();
    }

    #[Computed]
    public function financeOverview(): array
    {
        $groupFilter = match ($this->groupFilter) {
            'all' => null,
            'ungrouped' => EventFinanceOverviewService::FILTER_UNGROUPED,
            default => is_numeric($this->groupFilter) ? (int) $this->groupFilter : null,
        };

        return app(EventFinanceOverviewService::class)->forEvent(
            $this->getRecord(),
            $this->filter,
            $groupFilter,
        );
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
        unset($this->financeOverview);
    }

    public function setGroupFilter(string $groupFilter): void
    {
        $this->groupFilter = $groupFilter;
        unset($this->financeOverview);
    }

    public function toggleGroup(int $groupId): void
    {
        $this->collapsedGroups[$groupId] = ! ($this->collapsedGroups[$groupId] ?? false);
    }

    public function moveCostToGroup(int $costId, ?int $groupId): void
    {
        $cost = EventSettlementCost::query()->findOrFail($costId);
        $settlement = EventSettlement::findOrCreateActiveForEvent($this->getRecord());
        abort_unless((int) $cost->settlement_id === (int) $settlement->id, 403);

        app(\App\Services\EventSettlementCostGroupService::class)->moveCostToGroup(
            $cost,
            $groupId && $groupId > 0 ? $groupId : null,
        );

        unset($this->financeOverview, $this->selectedRow);
    }

    public function createGroup(): void
    {
        $name = trim($this->newGroupName);
        if ($name === '') {
            Notification::make()->title('Podaj nazwę grupy')->warning()->send();

            return;
        }

        $settlement = EventSettlement::findOrCreateActiveForEvent($this->getRecord());
        app(\App\Services\EventSettlementCostGroupService::class)->createGroup($settlement, $name);
        $this->newGroupName = '';
        unset($this->financeOverview);
        Notification::make()->title('Dodano grupę')->success()->send();
    }

    public function startRenameGroup(int $groupId, string $currentName): void
    {
        $this->renamingGroupId = $groupId;
        $this->renameGroupName = $currentName;
    }

    public function saveRenameGroup(): void
    {
        if (! $this->renamingGroupId) {
            return;
        }

        $name = trim($this->renameGroupName);
        if ($name === '') {
            return;
        }

        $group = \App\Models\EventSettlementCostGroup::query()->findOrFail($this->renamingGroupId);
        $settlement = EventSettlement::findOrCreateActiveForEvent($this->getRecord());
        abort_unless((int) $group->settlement_id === (int) $settlement->id, 403);

        app(\App\Services\EventSettlementCostGroupService::class)->renameGroup($group, $name);
        $this->renamingGroupId = null;
        $this->renameGroupName = '';
        unset($this->financeOverview);
        Notification::make()->title('Zmieniono nazwę grupy')->success()->send();
    }

    public function deleteGroup(int $groupId): void
    {
        $group = \App\Models\EventSettlementCostGroup::query()->findOrFail($groupId);
        $settlement = EventSettlement::findOrCreateActiveForEvent($this->getRecord());
        abort_unless((int) $group->settlement_id === (int) $settlement->id, 403);

        app(\App\Services\EventSettlementCostGroupService::class)->deleteGroup($group);
        unset($this->financeOverview);
        Notification::make()->title('Usunięto grupę')->success()->send();
    }

    public function openCost(int $costId): void
    {
        $this->selectedCostId = $costId;
        $this->showPaymentForm = false;
        $this->showPlanForm = false;
        $this->showDocumentForm = false;
        $this->resetPaymentForm();
        $this->resetDocumentForm();
        $this->hydratePlanFormFromSelection();
        unset($this->selectedRow);
    }

    public function closeCost(): void
    {
        $this->selectedCostId = null;
        $this->showPaymentForm = false;
        $this->showPlanForm = false;
        $this->showDocumentForm = false;
        unset($this->selectedRow);
    }

    public function toggleCostSelection(int $costId): void
    {
        $ids = array_map('intval', $this->selectedCostIds);
        if (in_array($costId, $ids, true)) {
            $this->selectedCostIds = array_values(array_filter($ids, fn (int $id): bool => $id !== $costId));
        } else {
            $ids[] = $costId;
            $this->selectedCostIds = array_values(array_unique($ids));
        }
    }

    public function selectAllVisible(): void
    {
        $this->selectedCostIds = collect($this->financeOverview['rows'] ?? [])
            ->pluck('cost_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function clearSelection(): void
    {
        $this->selectedCostIds = [];
        $this->bulkTargetGroupId = null;
    }

    public function bulkMoveToGroup(): void
    {
        $ids = array_map('intval', $this->selectedCostIds);
        if ($ids === []) {
            Notification::make()->title('Zaznacz pozycje')->warning()->send();

            return;
        }

        $target = $this->bulkTargetGroupId;
        $groupId = ($target === null || $target === '' || $target === '0')
            ? null
            : (int) $target;

        $settlement = EventSettlement::findOrCreateActiveForEvent($this->getRecord());
        $service = app(EventSettlementCostGroupService::class);

        $moved = 0;
        foreach ($ids as $costId) {
            $cost = EventSettlementCost::query()
                ->where('settlement_id', $settlement->id)
                ->find($costId);
            if (! $cost) {
                continue;
            }
            $service->moveCostToGroup($cost, $groupId);
            $moved++;
        }

        $this->clearSelection();
        unset($this->financeOverview, $this->selectedRow);
        Notification::make()->title("Przeniesiono {$moved} pozycji")->success()->send();
    }

    public function startAddDocument(): void
    {
        $this->resetDocumentForm();
        $this->showDocumentForm = true;
        $this->showPaymentForm = false;
        $this->showPlanForm = false;
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
            );
        } catch (\Throwable $e) {
            Notification::make()->title('Nie udało się dodać dokumentu')->body($e->getMessage())->danger()->send();

            return;
        }

        $this->showDocumentForm = false;
        $this->resetDocumentForm();
        unset($this->financeOverview, $this->selectedRow);
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

        unset($this->financeOverview, $this->selectedRow);
        Notification::make()->title('Usunięto dokument')->success()->send();
    }

    #[Computed]
    public function selectedRow(): ?array
    {
        if (! $this->selectedCostId) {
            return null;
        }

        $all = app(EventFinanceOverviewService::class)->forEvent(
            $this->getRecord(),
            EventFinanceOverviewService::FILTER_ALL,
        );

        foreach ($all['rows'] as $row) {
            if ((int) $row['cost_id'] === (int) $this->selectedCostId) {
                return $row;
            }
        }

        return null;
    }

    public function startAddPayment(): void
    {
        $this->resetPaymentForm();
        $this->showPaymentForm = true;
        $this->showPlanForm = false;
        $this->showDocumentForm = false;
    }

    public function startEditPlan(): void
    {
        $this->hydratePlanFormFromSelection();
        $this->showPlanForm = true;
        $this->showPaymentForm = false;
        $this->showDocumentForm = false;
    }

    public function savePayment(): void
    {
        $this->validate([
            'paymentForm.amount_pln' => ['required', 'numeric', 'min:0.01'],
            'paymentForm.payment_method' => ['required', 'in:cash,transfer,card,other'],
            'paymentForm.paid_by' => ['required', 'in:office,pilot'],
            'paymentForm.advance_type' => ['required', 'in:advance,deposit,final,full'],
        ], [], [
            'paymentForm.amount_pln' => 'kwota',
            'paymentForm.payment_method' => 'metoda',
            'paymentForm.paid_by' => 'płatnik',
            'paymentForm.advance_type' => 'rodzaj',
        ]);

        if (! $this->selectedCostId) {
            return;
        }

        $cost = EventSettlementCost::query()->findOrFail($this->selectedCostId);
        $form = $this->paymentForm;

        try {
            app(RecordSettlementCostPaymentAction::class)(new RecordSettlementCostPaymentData(
                planCost: $cost,
                amountPln: (float) $form['amount_pln'],
                paymentMethod: (string) $form['payment_method'],
                paidBy: (string) $form['paid_by'],
                advanceType: (string) $form['advance_type'],
                paidAt: filled($form['paid_at'] ?? null) ? Carbon::parse($form['paid_at']) : now(),
                dueDate: filled($form['due_date'] ?? null) ? Carbon::parse($form['due_date']) : null,
                documentNumber: $form['document_number'] ?? null,
                notes: $form['notes'] ?? null,
                paidByUserId: auth()->id(),
            ));
        } catch (\Throwable $e) {
            Notification::make()->title('Nie udało się dodać wpłaty')->body($e->getMessage())->danger()->send();

            return;
        }

        $this->showPaymentForm = false;
        $this->resetPaymentForm();
        unset($this->financeOverview, $this->selectedRow);
        Notification::make()->title('Dodano wpłatę')->success()->send();
    }

    public function savePlan(): void
    {
        $this->validate([
            'planForm.planned_amount_pln' => ['required', 'numeric', 'min:0'],
            'planForm.paid_by' => ['required', 'in:office,pilot'],
        ], [], [
            'planForm.planned_amount_pln' => 'plan',
            'planForm.paid_by' => 'płatnik',
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
        ));

        $this->showPlanForm = false;
        unset($this->financeOverview, $this->selectedRow);
        Notification::make()->title('Zapisano plan')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('full_calculation')
                ->label('Pełna kalkulacja')
                ->icon('heroicon-o-calculator')
                ->color('gray')
                ->url(fn (): string => EventResource::getUrl('calculation', ['record' => $this->record])),
        ];
    }

    private function resetPaymentForm(): void
    {
        $this->paymentForm = [
            'amount_pln' => null,
            'advance_type' => 'advance',
            'payment_method' => 'transfer',
            'paid_by' => 'office',
            'paid_at' => now()->format('Y-m-d'),
            'due_date' => null,
            'document_number' => null,
            'notes' => null,
        ];
    }

    private function resetPlanForm(): void
    {
        $this->planForm = [
            'planned_amount_pln' => null,
            'paid_by' => 'office',
            'due_date' => null,
            'notes' => null,
        ];
    }

    private function resetDocumentForm(): void
    {
        $this->documentForm = [
            'document_type' => 'invoice',
            'document_number' => null,
            'notes' => null,
        ];
        $this->documentFiles = [];
    }

    private function hydratePlanFormFromSelection(): void
    {
        $row = $this->selectedRow;
        if (! $row) {
            $this->resetPlanForm();

            return;
        }

        $this->planForm = [
            'planned_amount_pln' => $row['planned_pln'],
            'paid_by' => $row['paid_by'] ?? 'office',
            'due_date' => $row['next_due_label'] ?? null,
            'notes' => $row['notes'] ?? null,
        ];
    }

    public static function getResourcePageName(): string
    {
        foreach (EventResource::getPages() as $pageName => $pageRegistration) {
            if ($pageRegistration->getPage() !== static::class) {
                continue;
            }

            return $pageName;
        }

        throw new \Exception('Page ['.static::class.'] is not registered to the resource ['.EventResource::class.'].');
    }

    public static function getRouteName(?string $panel = null): string
    {
        return EventResource::getRouteBaseName(panel: $panel).'.'.static::getResourcePageName();
    }
}
