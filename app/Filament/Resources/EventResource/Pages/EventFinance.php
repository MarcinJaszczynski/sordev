<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventResource\Pages;

use App\Actions\Finance\ChangeSettlementCostPayerAction;
use App\Data\ChangeSettlementCostPayerData;
use App\Filament\Actions\HelpArticleAction;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Concerns\HasEventFinanceSubNavigation;
use App\Filament\Resources\EventResource\Concerns\InteractsWithEventRecord;
use App\Filament\Resources\EventResource\Concerns\InteractsWithSettlementCostDrawer;
use App\Models\Currency;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Services\EventFinanceOverviewService;
use App\Services\EventSettlementCostGroupService;
use App\Services\SettlementPaymentHealthService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;

class EventFinance extends Page
{
    use HasEventFinanceSubNavigation;
    use InteractsWithEventRecord;
    use InteractsWithSettlementCostDrawer;
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

    /** Domyślnie ukrywa pozycje z zerowym szablonem, planowanymi i zapłaconym. */
    #[Url]
    public bool $hideZero = true;

    #[Url]
    public string $search = '';

    #[Url]
    public string $sortBy = EventFinanceOverviewService::SORT_NAME;

    #[Url]
    public string $sortDir = 'asc';

    /** @var array<int, bool> */
    public array $collapsedGroups = [];

    /** @var list<int|string> */
    public array $selectedCostIds = [];

    public ?string $bulkTargetGroupId = null;

    public string $bulkPaidBy = 'office';

    public string $newGroupName = '';

    public ?int $renamingGroupId = null;

    public string $renameGroupName = '';

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

        // GET nie tworzy settlementu — empty state + jawne „Utwórz rozliczenie”.
        $settlement = EventSettlement::findActiveForEvent($this->getRecord());
        if ($settlement) {
            $hasPlanCosts = app(SettlementPaymentHealthService::class)
                ->listPlanCosts($settlement->costs()->get())
                ->isNotEmpty();

            if (! $hasPlanCosts) {
                $this->getRecord()->refreshActiveSettlementCosts();
            }
        }

        $this->initializeSettlementCostDrawerForms();
    }

    protected function invalidateSettlementCostCaches(): void
    {
        unset($this->financeOverview, $this->selectedRow);
        $this->dispatchSettlementFinanceChanged();
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

    public function createSettlement(): void
    {
        $this->ensureSettlement();
        $this->getRecord()->refreshActiveSettlementCosts();
        unset($this->financeOverview, $this->selectedRow);

        Notification::make()
            ->title('Utworzono rozliczenie')
            ->body('Możesz uzupełniać koszty, wpłaty i dokumenty.')
            ->success()
            ->send();
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
            $this->hideZero,
            $this->search,
            $this->sortBy,
            $this->sortDir,
        );
    }

    public function updatedSearch(): void
    {
        unset($this->financeOverview);
    }

    public function setSort(string $column): void
    {
        if (! in_array($column, EventFinanceOverviewService::$sortableColumns, true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'asc';
        }

        unset($this->financeOverview);
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

    public function toggleHideZero(): void
    {
        $this->hideZero = ! $this->hideZero;
        unset($this->financeOverview);
    }

    public function toggleGroup(int $groupId): void
    {
        $this->collapsedGroups[$groupId] = ! ($this->collapsedGroups[$groupId] ?? false);
    }

    public function moveCostToGroup(int $costId, ?int $groupId): void
    {
        $cost = EventSettlementCost::query()->findOrFail($costId);
        $settlement = $this->ensureSettlement();
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

        $settlement = $this->ensureSettlement();
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
        $settlement = $this->ensureSettlement();
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
        $settlement = $this->ensureSettlement();
        abort_unless((int) $group->settlement_id === (int) $settlement->id, 403);

        try {
            app(\App\Services\EventSettlementCostGroupService::class)->deleteGroup($group);
        } catch (\Throwable $e) {
            Notification::make()->title('Nie usunięto grupy')->body($e->getMessage())->danger()->send();

            return;
        }

        unset($this->financeOverview);
        Notification::make()->title('Usunięto grupę')->success()->send();
    }

    public function bulkReplaceInsuranceActual(): void
    {
        $ids = array_map('intval', $this->selectedCostIds);
        if ($ids === []) {
            Notification::make()->title('Zaznacz pozycje ubezpieczeń')->warning()->send();

            return;
        }

        $settlement = $this->ensureSettlement();
        $plans = EventSettlementCost::query()
            ->where('settlement_id', $settlement->id)
            ->whereIn('id', $ids)
            ->where('source_type', 'insurance_day')
            ->get();

        if ($plans->isEmpty()) {
            Notification::make()->title('Brak zaznaczonych ubezpieczeń')->warning()->send();

            return;
        }

        $replaced = 0;
        $errors = [];
        foreach ($plans as $plan) {
            try {
                app(\App\Actions\Finance\ReplaceInsuranceActualFromPlanAction::class)($plan, auth()->id());
                $replaced++;
            } catch (\Throwable $e) {
                $errors[] = ($plan->name ?: '#'.$plan->id).': '.$e->getMessage();
            }
        }

        $this->clearSelection();
        unset($this->financeOverview, $this->selectedRow);

        if ($replaced > 0) {
            Notification::make()
                ->title('Zastąpiono rzeczywiste ubezpieczenia')
                ->body("Zaktualizowano pozycji: {$replaced}")
                ->success()
                ->send();
        }
        if ($errors !== []) {
            Notification::make()
                ->title('Część pozycji nie została zastąpiona')
                ->body(implode("\n", array_slice($errors, 0, 5)))
                ->warning()
                ->send();
        }
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
        $this->bulkPaidBy = 'office';
    }

    public function bulkChangePaidBy(): void
    {
        $ids = array_map('intval', $this->selectedCostIds);
        if ($ids === []) {
            Notification::make()->title('Zaznacz pozycje')->warning()->send();

            return;
        }

        $paidBy = array_key_exists($this->bulkPaidBy, EventSettlementCost::$paidByOptions)
            ? $this->bulkPaidBy
            : 'office';

        $settlement = $this->ensureSettlement();
        $plans = EventSettlementCost::query()
            ->where('settlement_id', $settlement->id)
            ->whereIn('id', $ids)
            ->get()
            ->filter(fn (EventSettlementCost $cost): bool => ! EventSettlementCost::isPaymentSourceType($cost->source_type));

        try {
            $updated = app(ChangeSettlementCostPayerAction::class)->forMany($plans, $paidBy);
        } catch (\Throwable $e) {
            Notification::make()->title('Nie udało się zmienić płatnika')->body($e->getMessage())->danger()->send();

            return;
        }

        $this->clearSelection();
        unset($this->financeOverview, $this->selectedRow);

        $label = EventSettlementCost::$paidByOptions[$paidBy] ?? $paidBy;
        Notification::make()
            ->title('Zaktualizowano płatnika')
            ->body('Ustawiono „'.$label.'” dla '.$updated.' pozycji.')
            ->success()
            ->send();
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

        $settlement = $this->ensureSettlement();
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

    protected function getHeaderActions(): array
    {
        return [
            HelpArticleAction::make('rozliczenie-imprezy'),
            Actions\Action::make('add_manual_cost')
                ->label('Dodaj wydatek')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->action('startAddManualCost')
                ->visible(fn (): bool => EventSettlement::findActiveForEvent($this->getRecord()) !== null),
            Actions\Action::make('add_cost')
                ->label('Koszt programu')
                ->icon('heroicon-o-map')
                ->color('gray')
                ->action('startAddCost')
                ->visible(fn (): bool => EventSettlement::findActiveForEvent($this->getRecord()) !== null),
            Actions\Action::make('full_calculation')
                ->label('Pełna kalkulacja')
                ->icon('heroicon-o-calculator')
                ->color('gray')
                ->url(fn (): string => EventResource::getUrl('calculation', ['record' => $this->record])),
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
