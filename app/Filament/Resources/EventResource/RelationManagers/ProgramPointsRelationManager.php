<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Filament\Concerns\InteractsWithTaskEditModal;
use App\Filament\Forms\ContractorWithLocationFields;
use App\Filament\Forms\EventProgramPointPricingFields;
use App\Filament\Resources\EventResource\Concerns\InteractsWithSettlementCostDrawer;
use App\Filament\Resources\EventResource\Concerns\ManagesProgramPointSettlementFinance;
use App\Filament\Resources\EventResource\Pages\EditEventProgram;
use App\Filament\Resources\ReservationResource;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\EventTemplateProgramPoint;
use App\Models\Reservation;
use App\Services\ContractorLocationService;
use App\Services\EventPaymentScheduleService;
use App\Services\EventProgramPointCreator;
use App\Services\EventProgramPointDeletionService;
use App\Services\EventProgramPointOrderService;
use App\Services\EventProgramScheduleService;
use App\Services\ProgramPointContractorBulkAssignService;
use App\Services\ProgramPointListFinanceDisplay;
use App\Services\ProgramPointSetFinanceAggregator;
use App\Services\ProgramPointSetTimePropagator;
use App\Services\ProgramPointSettlementCostCache;
use App\Support\ProgramPointCostPricing;
use App\Support\ProgramTimeSlots;
use App\Support\Reservations\ReservationWorkflowDisplay;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;
use Livewire\WithFileUploads;

class ProgramPointsRelationManager extends RelationManager
{
    use InteractsWithSettlementCostDrawer;
    use InteractsWithTaskEditModal;
    use ManagesProgramPointSettlementFinance;
    use WithFileUploads;

    /** @var array<string, mixed> */
    protected array $pendingProgramPointTimeEdit = [];

    protected static string $relationship = 'programPoints';

    protected static ?string $title = 'Program imprezy';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string $view = 'filament.resources.event-resource.relation-managers.program-points';

    public ?string $ownerProgramView = null;

    public ?int $ownerProgramDay = null;

    public ?string $ownerProgramFilter = 'all';

    /**
     * ID rodziców setów rozwiniętych w tabeli (podpunkty widoczne jako wiersze).
     *
     * @var array<int, int>
     */
    public array $expandedSetIds = [];

    /**
     * @var \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, EventProgramPoint>>|null
     */
    protected ?\Illuminate\Support\Collection $programPointChildrenByParent = null;

    protected ?ProgramPointSettlementCostCache $settlementCostCache = null;

    public function mount(): void
    {
        parent::mount();
        $this->initializeSettlementCostDrawerForms();

        app(EventProgramPointDeletionService::class)->cleanupOrphans($this->getOwnerRecord());

        $stored = session($this->expandedSetsSessionKey(), []);
        $storedIds = is_array($stored)
            ? array_values(array_filter(
                array_map('intval', $stored),
                fn (int $id): bool => $id > 0,
            ))
            : [];

        $this->expandedSetIds = array_values(array_unique(array_merge(
            $this->defaultExpandedSetIds(),
            $storedIds,
        )));

        $this->persistExpandedSetIds();
    }

    #[On('undo-last-program-point-deletion')]
    public function undoLastProgramPointDeletion(): void
    {
        $event = $this->getOwnerRecord();
        $service = app(EventProgramPointDeletionService::class);
        $pending = $service->pendingUndo((int) $event->id);

        if ($pending === null) {
            Notification::make()
                ->title('Brak usunięcia do cofnięcia')
                ->body('Ostatnie usunięcie wygasło albo zostało już przywrócone.')
                ->warning()
                ->send();

            return;
        }

        $restored = $service->restoreByIds((int) $event->id, $pending['point_ids']);

        Notification::make()
            ->title($restored > 0 ? 'Cofnięto usunięcie' : 'Nie przywrócono punktów')
            ->body($restored > 0
                ? 'Przywrócono „'.$pending['label'].'”'
                    .($pending['was_set'] ? ' (set z podpunktami)' : '').'.'
                : 'Punkty nie były już w koszu.')
            ->success()
            ->send();

        $this->resetTable();
        $this->dispatch('event-program-points-refresh');
    }

    /**
     * @return array<int, int>
     */
    protected function defaultExpandedSetIds(): array
    {
        return EventProgramPoint::query()
            ->where('event_id', $this->getOwnerRecord()->getKey())
            ->whereNull('parent_id')
            ->has('children')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    protected function expandedSetsSessionKey(): string
    {
        return 'epp_expanded_sets_'.$this->getOwnerRecord()->getKey();
    }

    protected function persistExpandedSetIds(): void
    {
        session([$this->expandedSetsSessionKey() => $this->expandedSetIds]);
    }

    protected function finalizeProgramPointCreation(?EventProgramPoint $point): void
    {
        if (! $point) {
            return;
        }

        $point->refresh();

        if ($point->parent_id) {
            $this->expandSetParent((int) $point->parent_id);

            $parent = EventProgramPoint::query()->find($point->parent_id);

            if ($parent) {
                app(ProgramPointSetTimePropagator::class)->propagateFromParent($parent->fresh(['children']));
            }
        } elseif ($point->children()->exists()) {
            $this->expandSetParent((int) $point->id);
            app(ProgramPointSetTimePropagator::class)->propagateFromParent($point->fresh(['children']));
        }

        $this->invalidateSettlementCostCache();
        $this->resetTable();
        $this->dispatch('event-program-points-refresh');
    }

    #[On('event-program-points-refresh')]
    public function refreshProgramPointsTable(): void
    {
        $this->invalidateSettlementCostCache();
        $this->getOwnerRecord()->unsetRelation('activeSettlement');
        $this->resetTable();
    }

    protected function invalidateSettlementCostCache(): void
    {
        $this->settlementCostCache = null;
        $this->programPointFinanceViewDataCache = [];
    }

    protected function invalidateSettlementCostCaches(): void
    {
        \App\Services\EventFinanceOverviewService::forgetOverviewCacheForEvent((int) $this->getOwnerRecord()->id);
        unset($this->selectedRow);
        $this->invalidateSettlementCostCache();
        $this->resetTable();
        $this->dispatchSettlementFinanceChanged();
    }

    public function toggleSetExpanded(int $parentId): void
    {
        $parentId = (int) $parentId;

        if ($parentId <= 0) {
            return;
        }

        if (in_array($parentId, $this->expandedSetIds, true)) {
            $this->expandedSetIds = array_values(array_filter(
                $this->expandedSetIds,
                fn (int $id): bool => $id !== $parentId,
            ));
        } else {
            $this->expandedSetIds[] = $parentId;
        }

        $this->persistExpandedSetIds();
        $this->resetTable();
    }

    protected function expandSetParent(int $parentId): void
    {
        $parentId = (int) $parentId;

        if ($parentId <= 0 || in_array($parentId, $this->expandedSetIds, true)) {
            return;
        }

        $this->expandedSetIds[] = $parentId;
        $this->persistExpandedSetIds();
    }

    public function openSetChild(int $pointId): void
    {
        $point = EventProgramPoint::query()
            ->where('event_id', $this->getOwnerRecord()->id)
            ->withTrashed()
            ->find($pointId);

        if (! $point) {
            return;
        }

        if ($point->parent_id) {
            $this->expandSetParent((int) $point->parent_id);
        }

        if ($this->isProgramDaysTabView()) {
            $activeDay = $this->getActiveProgramDayTab();

            if ($activeDay !== null && (int) $point->day !== $activeDay) {
                $this->ownerProgramDay = (int) $point->day;
                $this->dispatch(
                    'event-program-context-changed',
                    programView: 'days',
                    programDay: (int) $point->day,
                )->to(EditEventProgram::class);
                $this->resetTable();
            }
        }

        $this->mountTableAction('edit', (string) $pointId);
    }

    public function revealSetChildInTable(int $pointId): void
    {
        $point = EventProgramPoint::query()
            ->where('event_id', $this->getOwnerRecord()->id)
            ->withTrashed()
            ->find($pointId);

        if (! $point) {
            return;
        }

        if ($point->parent_id) {
            $this->expandSetParent((int) $point->parent_id);
        }

        if (($this->ownerProgramFilter ?? 'all') !== 'program') {
            $filters = $this->tableFilters ?? [];

            foreach (['include_in_program', 'include_in_calculation', 'active'] as $key) {
                if (array_key_exists($key, $filters)) {
                    $filters[$key]['value'] = null;
                }
            }

            if (! empty($filters['program_only']['isActive'] ?? false)) {
                $filters['program_only']['isActive'] = false;
            }

            $this->tableFilters = $filters;
        }

        if ($this->isProgramDaysTabView() && (int) $point->day !== (int) ($this->ownerProgramDay ?? 1)) {
            $this->ownerProgramDay = (int) $point->day;
            $this->dispatch(
                'event-program-context-changed',
                programView: 'days',
                programDay: (int) $point->day,
            )->to(EditEventProgram::class);
        }

        $this->resetTable();
        $this->mountTableAction('edit', (string) $pointId);
    }

    #[On('event-program-context-changed')]
    public function syncProgramContext(?string $programView = null, ?int $programDay = null, ?string $programFilter = null): void
    {
        $changed = false;

        if ($programView !== null && ($this->ownerProgramView ?? 'days') !== $programView) {
            $this->ownerProgramView = $programView;
            $changed = true;
        }

        if ($programDay !== null && (int) ($this->ownerProgramDay ?? 1) !== $programDay) {
            $this->ownerProgramDay = $programDay;
            $changed = true;
        }

        if ($programFilter !== null && ($this->ownerProgramFilter ?? 'all') !== $programFilter) {
            $this->ownerProgramFilter = $programFilter;
            $changed = true;
        }

        if ($changed) {
            $this->resetTable();
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('event_template_program_point_id')
                    ->label('Punkt programu')
                    ->relationship('templatePoint', 'name')
                    ->searchable()
                    ->preload()
                    ->reactive()
                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get): void {
                        if (blank($state)) {
                            return;
                        }

                        $templatePoint = EventTemplateProgramPoint::find($state);
                        if (! $templatePoint) {
                            return;
                        }

                        if (blank($get('name'))) {
                            $set('name', $templatePoint->name);
                        }

                        EventProgramPointPricingFields::applyTemplateDefaults($set, $templatePoint);
                    })
                    ->required(),

                Forms\Components\TextInput::make('name')
                    ->label('Nazwa punktu programu')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Możesz nadać własną nazwę dla tej imprezy, np. Parking Wieliczka.'),

                ...ContractorWithLocationFields::append([
                    Forms\Components\Select::make('contractor_id')
                        ->label(fn (?EventProgramPoint $record): string => $record?->isSetParent()
                            ? 'Miejsce / kontrahent (set)'
                            : 'Wykonawca/Kontraktor')
                        ->relationship('contractor', 'name')
                        ->getOptionLabelFromRecordUsing(fn (\App\Models\Contractor $record): string => $record->displayLabel())
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->live()
                        ->helperText(fn (?EventProgramPoint $record): ?string => $record?->isSetParent()
                            ? 'Miejsce / punkt zborny setu — nie nadpisuje płatności ani rezerwacji podpunktów.'
                            : null)
                        ->afterStateUpdated(function ($state, Forms\Set $set): void {
                            app(ContractorLocationService::class)->syncLocationOnContractorChange(
                                $set,
                                filled($state) ? (int) $state : null,
                            );
                        }),
                ], columnSpan: 'full'),

                Forms\Components\Toggle::make('is_hotel')
                    ->label('Nocleg / Hotel')
                    ->helperText('Oznacz jeśli ten punkt to miejsce noclegu (niezależnie od typu kontrahenta). Dane trafią do raportu hotelowego.')
                    ->default(false)
                    ->inline(false)
                    ->reactive()
                    ->afterStateUpdated(function ($state, Forms\Set $set): void {
                        if ($state) {
                            $set('is_hotel_service', false);
                        }
                    }),

                Forms\Components\Toggle::make('is_transport')
                    ->label('Transport')
                    ->helperText('Oznacz jeśli ten punkt dotyczy transportu. Kontrahent z takiego punktu uzupełni dane transportowe imprezy.')
                    ->default(false)
                    ->inline(false),

                Forms\Components\Toggle::make('is_hotel_service')
                    ->label('Usługa hotelu')
                    ->helperText('Dodatkowa usługa świadczona przez hotel (bankiet, obiad, DJ...). Liczona raz w kosztach; nie zaznaczaj razem z „Nocleg / Hotel”.')
                    ->default(false)
                    ->inline(false)
                    ->reactive()
                    ->afterStateUpdated(function ($state, Forms\Set $set): void {
                        if ($state) {
                            $set('is_hotel', false);
                        }
                    }),

                Forms\Components\TextInput::make('day')
                    ->label('Dzień')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(fn (): int => $this->getOwnerRecord()->facultativeProgramDay())
                    ->helperText(fn (): string => 'Dni wycieczki: 1–'.$this->getOwnerRecord()->resolveCoreProgramDaysCount()
                        .'. '.$this->getOwnerRecord()->facultativeProgramDay().' = opcje fakultatywne.')
                    ->required(),

                Forms\Components\TextInput::make('order')
                    ->label('Kolejność')
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->afterStateHydrated(function (Forms\Components\TextInput $component, mixed $state, ?EventProgramPoint $record): void {
                        if (filled($state) || $record === null) {
                            return;
                        }

                        // Prefill z bieżącej kolejności rekordu (w tym legacy order=0).
                        $component->state((int) ($record->order ?? 0));
                    }),

                $this->programPointRichTextField('description')
                    ->label('Opis punktu programu (dla tej imprezy)')
                    ->columnSpanFull(),

                Forms\Components\Select::make('parent_id')
                    ->label('Punkt nadrzędny')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->options(function (?EventProgramPoint $record) {
                        return $this->getOwnerRecord()
                            ->programPoints()
                            ->with('templatePoint')
                            ->when($record, fn ($query) => $query->where('id', '!=', $record->id))
                            ->orderBy('day')
                            ->orderBy('order')
                            ->get()
                            ->mapWithKeys(fn (EventProgramPoint $point) => [
                                $point->id => sprintf(
                                    '%s • %02d. %s',
                                    $this->resolveProgramDayLabel((int) ($point->day ?? 1)),
                                    (int) ($point->order ?? 1),
                                    $point->name ?? $point->templatePoint?->name ?? ('Punkt #'.$point->id)
                                ),
                            ]);
                    }),

                Forms\Components\Select::make('start_time')
                    ->label('Godzina startu')
                    ->options(ProgramTimeSlots::options())
                    ->searchable()
                    ->nullable()
                    ->placeholder('—')
                    ->rule('required_with:end_time'),

                Forms\Components\Select::make('end_time')
                    ->label('Godzina końca')
                    ->options(ProgramTimeSlots::options())
                    ->searchable()
                    ->nullable()
                    ->placeholder('—')
                    ->rule('required_with:start_time'),

                Forms\Components\DatePicker::make('start_date')
                    ->label('Data rozpoczęcia')
                    ->native(false)
                    ->nullable(),

                Forms\Components\DatePicker::make('end_date')
                    ->label('Data zakończenia')
                    ->native(false)
                    ->nullable()
                    ->afterOrEqual('start_date'),

                Forms\Components\Toggle::make('hide_times')
                    ->label('Ukryj godziny')
                    ->helperText('Punkt zachowuje kolejność, ale bez wyświetlania godzin w programie.')
                    ->default(false)
                    ->inline(false),

                Forms\Components\Placeholder::make('reservations_preview')
                    ->label('Rezerwacje u dostawcy')
                    ->visible(fn (?EventProgramPoint $record): bool => filled($record))
                    ->content(function (?EventProgramPoint $record): HtmlString {
                        if (! $record) {
                            return new HtmlString('');
                        }

                        $reservations = $record->reservations()
                            ->orderByDesc('id')
                            ->get();

                        if ($reservations->isEmpty() && ($shared = $record->latestVisibleReservation())) {
                            $reservations = collect([$shared]);
                        }

                        if ($reservations->isEmpty()) {
                            return new HtmlString('<div class="text-sm text-gray-500">Brak rezerwacji — dodaj w bocznym panelu „Płatności”.</div>');
                        }

                        $html = $reservations
                            ->map(fn (Reservation $reservation): string => self::renderReservationPreviewHtml($reservation))
                            ->implode('<div class="my-2 border-t border-gray-200 dark:border-gray-700"></div>');

                        return new HtmlString('<div class="space-y-2">'.$html.'</div>');
                    })
                    ->columnSpanFull(),

                $this->programPointRichTextField('notes')
                    ->label('Uwagi dla kierowcy / ogólne')
                    ->columnSpanFull(),

                $this->programPointRichTextField('office_notes')
                    ->label('Uwagi dla biura')
                    ->columnSpanFull(),

                $this->programPointRichTextField('pilot_notes')
                    ->label('Uwagi dla pilota')
                    ->columnSpanFull(),

                $this->programPointScopeFields(),

                ...EventProgramPointPricingFields::costHeadcountToggles(
                    max(1, (int) ($this->getOwnerRecord()->participant_count ?? 1)),
                    max(0, $this->getOwnerRecord()->resolveGratisCountForParticipantCount()),
                    ProgramPointCostPricing::pilotCount($this->getOwnerRecord()),
                    max(0, $this->getOwnerRecord()->resolveDriverCountForParticipantCount()),
                ),

                Forms\Components\Toggle::make('active')
                    ->label('Aktywny')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->filtersLayout(FiltersLayout::Dropdown)
            ->filtersFormMaxHeight('min(22rem, 70vh)')
            ->persistFiltersInSession()
            ->modifyQueryUsing(function (Builder $query): Builder {
                $query
                    ->withoutGlobalScopes([
                        SoftDeletingScope::class,
                    ])
                    ->with([
                        'contractor',
                        'currency',
                        'templatePoint',
                        'reservations' => fn ($reservations) => $reservations
                            ->withTrashed()
                            ->with(['contractor', 'historyEntries.user:id,name']),
                        'hotelStays.reservation',
                        'sharedReservation.contractor',
                        'parent.templatePoint',
                        'event.activeSettlement',
                    ])
                    ->when(Schema::hasTable('vendor_invoices'), fn (Builder $query) => $query->with('vendorInvoices'))
                    ->withCount('children');

                $day = $this->getActiveProgramDayTab();
                if ($day !== null) {
                    $query->where('day', $day);
                }

                if (($this->ownerProgramFilter ?? 'all') === 'program') {
                    $query->where('include_in_program', true);
                }

                return $query;
            })
            ->reorderable('order')
            ->paginated([25, 50, 100])
            ->defaultSort('day')
            ->striped(false)
            ->recordAction('edit')
            ->recordClasses(fn (EventProgramPoint $record): string => trim(
                'epp-program-row '.$this->resolveProgramPointRowClass($record)
            ))
            ->groups(fn (): array => $this->isProgramListView()
                ? [
                    Group::make('day')
                        ->label('Dzień')
                        ->titlePrefixedWithLabel(false)
                        ->collapsible(false)
                        ->getTitleFromRecordUsing(
                            fn (EventProgramPoint $record): string => $this->resolveProgramDayLabel((int) ($record->day ?? 1))
                        )
                        ->getDescriptionFromRecordUsing(function (EventProgramPoint $record): ?string {
                            $event = $this->getOwnerRecord();
                            $day = (int) ($record->day ?? 1);

                            if ($event->isFacultativeProgramDay($day)) {
                                return null;
                            }

                            return $event->dateForProgramDay($day)?->format('d.m.Y');
                        }),
                ]
                : [])
            ->defaultGroup(fn (): ?string => $this->isProgramListView() ? 'day' : null)
            ->groupingSettingsHidden()
            ->columns([
                Tables\Columns\ViewColumn::make('program_point_name')
                    ->label('Punkt programu')
                    ->view('filament.components.program-point-name-cell')
                    ->extraAttributes(['class' => 'epp-name-col'])
                    ->extraCellAttributes(['class' => 'epp-name-col'])
                    ->searchable(query: function (\Illuminate\Database\Eloquent\Builder $query, string $search): \Illuminate\Database\Eloquent\Builder {
                        return $query->where(function ($q) use ($search) {
                            $q->whereHas('templatePoint', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                                ->orWhere('event_program_points.name', 'like', "%{$search}%")
                                ->orWhere('event_program_points.description', 'like', "%{$search}%")
                                ->orWhereHas('templatePoint', fn ($q) => $q->where('description', 'like', "%{$search}%"))
                                ->orWhereHas('contractor', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                                ->orWhereHas('reservations', fn ($q) => $q->where('booking_reference', 'like', "%{$search}%"));
                        });
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query
                            ->leftJoin('event_template_program_points as sortable_template_points', 'sortable_template_points.id', '=', 'event_program_points.event_template_program_point_id')
                            ->orderByRaw("COALESCE(sortable_template_points.name, event_program_points.name) {$direction}")
                            ->select('event_program_points.*');
                    }),

                Tables\Columns\ViewColumn::make('contractor_label')
                    ->label('Kontr.')
                    ->view('filament.components.program-point-contractor-cell')
                    ->toggleable()
                    ->extraAttributes(['class' => 'epp-contractor-col'])
                    ->extraCellAttributes(['class' => 'epp-contractor-col']),

                Tables\Columns\ViewColumn::make('reservation_status')
                    ->label('Rez.')
                    ->view('filament.components.program-point-reservation-status-cell')
                    ->toggleable()
                    ->extraAttributes(['class' => 'epp-status-col epp-rez-col'])
                    ->extraCellAttributes(['class' => 'epp-status-col epp-rez-col']),

                Tables\Columns\ViewColumn::make('payment_status')
                    ->label('Płat.')
                    ->view('filament.components.program-point-payment-status-cell')
                    ->toggleable()
                    ->extraAttributes(['class' => 'epp-status-col epp-pay-col'])
                    ->extraCellAttributes(['class' => 'epp-status-col epp-pay-col']),

                Tables\Columns\ViewColumn::make('finance')
                    ->label('S/P/Z')
                    ->tooltip('S = szablon · P = plan · Z = zapłacono')
                    ->view('filament.components.program-point-finance-cell')
                    ->toggleable()
                    ->extraAttributes(['class' => 'epp-finance-col'])
                    ->extraCellAttributes(['class' => 'epp-finance-col']),

                Tables\Columns\SelectColumn::make('settlement_paid_by')
                    ->label('Pł.')
                    ->options(EventSettlementCost::$paidByOptions)
                    ->tooltip('Płatnik pozostałej kwoty (plan − wpłaty)')
                    ->toggleable()
                    ->getStateUsing(function (EventProgramPoint $record): ?string {
                        if ($record->getAttribute('_is_set_parent')) {
                            return null;
                        }

                        return $this->settlementCosts()->baseCost((int) $record->id)?->paid_by ?? 'office';
                    })
                    ->updateStateUsing(function (EventProgramPoint $record, ?string $state): void {
                        if ($record->getAttribute('_is_set_parent') || ! filled($state)) {
                            return;
                        }

                        $this->updateProgramPointPaidBy($record, $state);
                        $this->invalidateSettlementCostCache();
                        $this->dispatch('event-program-points-refresh');
                    })
                    ->placeholder('—')
                    ->disabled(fn (EventProgramPoint $record): bool => (bool) $record->getAttribute('_is_set_parent'))
                    ->extraAttributes(['class' => 'epp-payer-col'])
                    ->extraCellAttributes(['class' => 'epp-payer-col'])
                    ->width('4.75rem'),

                Tables\Columns\TextColumn::make('finance_doc')
                    ->label('Dok.')
                    ->alignCenter()
                    ->html()
                    ->disabledClick()
                    ->toggleable()
                    ->extraCellAttributes(['class' => 'epp-doc-col'])
                    ->tooltip(fn (EventProgramPoint $record): ?string => $this->buildProgramPointPricesSummaryViewData($record)['documentStatusLabel'] ?? null)
                    ->state(function (EventProgramPoint $record): string {
                        $s = $this->buildProgramPointPricesSummaryViewData($record);
                        if (! empty($s['hideSetParentFinance'])) {
                            return '<span class="text-gray-400">—</span>';
                        }

                        $hint = (string) ($s['documentHint'] ?? 'Brak pliku');
                        $url = (string) ($s['documentFirstUrl'] ?? '');
                        $hasFile = ! empty($s['hasUploadedFile']) && $url !== '';
                        $title = e($s['documentStatusLabel'] ?? $hint);
                        $badge = e((string) ($s['documentBadgeLabel'] ?? $hint));

                        if ($hasFile) {
                            return '<a href="'.e($url).'" target="_blank" rel="noopener noreferrer"'
                                .' class="epp-doc-badge epp-doc-badge--has"'
                                .' title="'.$title.'">'
                                .$badge
                                .'</a>';
                        }

                        if ($hint !== '' && $hint !== 'Brak pliku') {
                            return '<span class="epp-doc-badge epp-doc-badge--warn" title="'.$title.'">Nr</span>';
                        }

                        return '<span class="epp-doc-badge epp-doc-badge--empty" title="Brak pliku">—</span>';
                    })
                    ->width('4.25rem'),

                Tables\Columns\ViewColumn::make('office_pilot_notes')
                    ->label('Uwagi')
                    ->view('filament.components.program-point-notes-preview')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->extraCellAttributes(['class' => 'epp-notes-col']),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('paid_by_settlement')
                    ->label('Płatnik')
                    ->options(EventSettlementCost::$paidByOptions)
                    ->placeholder('Wszyscy')
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;
                        if (! filled($value)) {
                            return $query;
                        }

                        $settlement = $this->getOwnerRecord()->activeSettlement;
                        if (! $settlement) {
                            return $query;
                        }

                        $pointIds = $settlement->costs()
                            ->where('source_type', 'program_point')
                            ->where('paid_by', $value)
                            ->pluck('source_id');

                        return $query->whereIn('id', $pointIds);
                    }),

                Tables\Filters\SelectFilter::make('day')
                    ->label('Dzień')
                    ->hidden(fn (): bool => $this->isProgramDaysTabView())
                    ->options(function () {
                        $event = $this->getOwnerRecord();
                        $maxDay = $event->resolveProgramDaysCount();
                        $options = [];
                        for ($i = 1; $i <= $maxDay; $i++) {
                            $options[$i] = $event->programDayLabel($i);
                        }

                        return $options;
                    }),

                Tables\Filters\SelectFilter::make('contractor_id')
                    ->label('Kontrahent')
                    ->relationship('contractor', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('program_only')
                    ->label('Tylko program')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query
                        ->where('include_in_program', true)
                        ->where('include_in_calculation', false)),

                Tables\Filters\TernaryFilter::make('include_in_program')
                    ->label('Uwzględniony w programie')
                    ->placeholder('Wszystkie')
                    ->trueLabel('Tak — w programie')
                    ->falseLabel('Nie — poza programem'),

                Tables\Filters\TernaryFilter::make('include_in_calculation')
                    ->label('Uwzględniony w kosztach')
                    ->placeholder('Wszystkie')
                    ->trueLabel('Tak — w kosztach')
                    ->falseLabel('Nie — poza kosztami'),

                Tables\Filters\TernaryFilter::make('active')
                    ->label('Aktywny')
                    ->placeholder('Wszystkie')
                    ->trueLabel('Aktywne')
                    ->falseLabel('Nieaktywne')
                    ->default(true),

                Tables\Filters\TrashedFilter::make()
                    ->label('Usunięte punkty'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('undo_last_deletion')
                    ->label('Cofnij ostatnie usunięcie')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (): bool => app(EventProgramPointDeletionService::class)
                        ->hasPendingUndo((int) $this->getOwnerRecord()->id))
                    ->action(fn () => $this->undoLastProgramPointDeletion()),

                Tables\Actions\Action::make('sync_from_template')
                    ->label('Przywróć kolejność ze szablonu')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn () => (bool) $this->getOwnerRecord()->event_template_id)
                    ->modalDescription('Ustawia day/order i powiązania setów według szablonu imprezy. Punkty dodane ręcznie (spoza szablonu) pozostają bez zmian.')
                    ->action(function (): void {
                        $deletionService = app(EventProgramPointDeletionService::class);
                        $orphans = $deletionService->cleanupOrphans($this->getOwnerRecord());

                        $service = app(EventProgramPointOrderService::class);
                        $updated = $service->syncOrderFromTemplate($this->getOwnerRecord());

                        Notification::make()
                            ->title('Przywrócono kolejność ze szablonu')
                            ->body(trim(
                                ($updated > 0
                                    ? "Zaktualizowano {$updated} pól (kolejność / powiązania)."
                                    : 'Kolejność była już zgodna ze szablonem.')
                                .($orphans > 0 ? " Usunięto też {$orphans} osieroconych podpunktów." : '')
                            ))
                            ->success()
                            ->send();

                        $this->resetTable();
                        $this->dispatch('event-program-points-refresh');
                    }),

                Tables\Actions\Action::make('repair_order')
                    ->label('Uporządkuj kolejność')
                    ->icon('heroicon-o-arrows-up-down')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Ustawia kolejność wg godzin rozpoczęcia (punkty bez godziny — na końcu dnia wg bieżącego order).')
                    ->action(function (): void {
                        $orphans = app(EventProgramPointDeletionService::class)
                            ->cleanupOrphans($this->getOwnerRecord());

                        $service = app(EventProgramPointOrderService::class);
                        $updated = $service->repairOrderByStartTimes($this->getOwnerRecord());

                        Notification::make()
                            ->title('Kolejność uporządkowana')
                            ->body(trim(
                                ($updated > 0
                                    ? "Zaktualizowano {$updated} pozycji wg godzin."
                                    : 'Kolejność była już zgodna z godzinami.')
                                .($orphans > 0 ? " Usunięto też {$orphans} osieroconych podpunktów." : '')
                            ))
                            ->success()
                            ->send();

                        $this->resetTable();
                        $this->dispatch('event-program-points-refresh');
                    }),

                Tables\Actions\Action::make('rebuild_schedule')
                    ->label('Przelicz godziny z czasu trwania')
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Układa godziny punktów bez ręcznej blokady według czasu trwania i kolejności. Ręcznie edytowane godziny pozostają bez zmian.')
                    ->action(function (): void {
                        $updated = app(EventProgramScheduleService::class)
                            ->bootstrapFromTemplate($this->getOwnerRecord(), onlyUnlocked: true);

                        \Filament\Notifications\Notification::make()
                            ->title('Godziny przeliczone')
                            ->body($updated > 0
                                ? "Zaktualizowano {$updated} pozycji programu."
                                : 'Brak punktów do przeliczenia.')
                            ->success()
                            ->send();

                        $this->resetTable();
                        $this->dispatch('event-program-points-refresh');
                    }),

                Tables\Actions\Action::make('repair_template_sets')
                    ->label('Napraw sety z szablonu')
                    ->icon('heroicon-o-link')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn () => (bool) $this->getOwnerRecord()->event_template_id)
                    ->action(function (): void {
                        $linked = app(EventProgramPointOrderService::class)
                            ->repairTemplateSetLinks($this->getOwnerRecord());

                        \Filament\Notifications\Notification::make()
                            ->title($linked > 0 ? 'Powiązano podpunkty' : 'Brak zmian')
                            ->body($linked > 0
                                ? "Przypisano parent_id dla {$linked} podpunktów na podstawie szablonu."
                                : 'Nie znaleziono podpunktów do naprawy.')
                            ->success()
                            ->send();

                        $this->resetTable();
                        $this->dispatch('event-program-points-refresh');
                    }),

                Tables\Actions\Action::make('add_program_point')
                    ->label('Dodaj punkt programu')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->modalHeading('Dodaj punkt programu do imprezy')
                    ->modalDescription('Wyszukaj w szablonach lub istniejących punktach innych imprez, albo utwórz nowy.')
                    ->modalWidth('7xl')
                    ->modalSubmitActionLabel('Dodaj punkt')
                    ->modalCancelActionLabel('Anuluj')
                    ->fillForm(function (): array {
                        $day = $this->resolveDayForNewProgramPoint(
                            max(1, (int) ($this->getActiveProgramDayTab() ?? $this->ownerProgramDay ?? 1))
                        );

                        return [
                            'day' => $day,
                            'order' => $this->nextProgramPointOrderForDay($day),
                            'include_in_program' => true,
                            'include_in_calculation' => true,
                            'include_gratis_in_cost' => false,
                            'include_pilot_in_cost' => false,
                            'include_driver_in_cost' => false,
                            'active' => true,
                        ];
                    })
                    ->form([
                        Forms\Components\Select::make('source_point')
                            ->label('Szukaj w katalogu szablonów i punktach innych imprez')
                            ->searchable()
                            ->nullable()
                            ->allowHtml()
                            ->optionsLimit(40)
                            ->getSearchResultsUsing(fn (string $search): array => app(EventProgramPointCreator::class)
                                ->searchCatalogSelectOptions($search, (int) $this->getOwnerRecord()->id))
                            ->getOptionLabelUsing(fn (?string $value): ?string => app(EventProgramPointCreator::class)
                                ->catalogOptionSelectedLabel($value))
                            ->placeholder('Wpisz min. 2 znaki, np. rejs Wisła Kraków…')
                            ->helperText('W wynikach widać, czy to set czy punkt, tagi (miasto), czas i cenę. Puste pole = nowy punkt tylko dla tej imprezy.')
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, Forms\Get $get) {
                                if ($state) {
                                    if (str_starts_with($state, 'template_')) {
                                        $id = (int) str_replace('template_', '', $state);
                                        $templatePoint = EventTemplateProgramPoint::find($id);
                                        if ($templatePoint) {
                                            $set('name', $templatePoint->name);
                                            $set('description', $templatePoint->description);
                                            EventProgramPointPricingFields::applyTemplateDefaults($set, $templatePoint);
                                        }
                                    } elseif (str_starts_with($state, 'event_')) {
                                        $id = (int) str_replace('event_', '', $state);
                                        $eventPoint = EventProgramPoint::find($id);
                                        if ($eventPoint) {
                                            $set('name', $eventPoint->name);
                                            $set('description', $eventPoint->description);
                                            EventProgramPointPricingFields::applyEventPointDefaults($set, $eventPoint);
                                        }
                                    }
                                } else {
                                    $set('name', '');
                                    $set('description', '');
                                    $set('unit_price', 0);
                                    $set('planned_price', 0);
                                    $set('paid_price', 0);
                                    $set('calculated_price', 0);
                                }
                            }),

                        Forms\Components\TextInput::make('name')
                            ->label('Nazwa punktu programu')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Wpisz nazwę punktu programu')
                            ->helperText('Zostanie automatycznie wypełniona przy wyborze z biblioteki'),

                        $this->programPointScopeFields(),

                        $this->programPointRichTextField('description')
                            ->label('Opis punktu programu (dla tej imprezy)')
                            ->columnSpanFull(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('day')
                                    ->label('Dzień')
                                    ->options(fn (): array => $this->programDaySelectOptions())
                                    ->default(fn (): int => $this->resolveDayForNewProgramPoint(
                                        max(1, (int) ($this->getActiveProgramDayTab() ?? $this->ownerProgramDay ?? 1))
                                    ))
                                    ->disabled(fn (): bool => $this->isProgramDaysTabView())
                                    ->dehydrated()
                                    ->helperText(fn (): ?string => $this->isProgramDaysTabView()
                                        ? 'Punkt trafi do aktywnej zakładki dnia.'
                                        : 'Ostatnia pozycja = opcje fakultatywne (po ostatnim dniu wycieczki).')
                                    ->live()
                                    ->afterStateUpdated(function (mixed $state, Forms\Set $set, Forms\Get $get): void {
                                        $set('order', $this->nextProgramPointOrderForDay(
                                            $this->resolveDayForNewProgramPoint(max(1, (int) ($state ?? 1))),
                                            filled($get('parent_id')) ? (int) $get('parent_id') : null,
                                        ));
                                    })
                                    ->required(),

                                Forms\Components\TextInput::make('order')
                                    ->label('Kolejność')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(fn (): int => $this->nextProgramPointOrderForDay(
                                        $this->resolveDayForNewProgramPoint(
                                            max(1, (int) ($this->getActiveProgramDayTab() ?? $this->ownerProgramDay ?? 1))
                                        ),
                                    ))
                                    ->required(),
                            ]),

                        Forms\Components\Select::make('parent_id')
                            ->label('Punkt nadrzędny (opcjonalnie)')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->live()
                            ->afterStateUpdated(function (mixed $state, Forms\Set $set, Forms\Get $get): void {
                                $set('order', $this->nextProgramPointOrderForDay(
                                    max(1, (int) ($get('day') ?? 1)),
                                    filled($state) ? (int) $state : null,
                                ));
                            })
                            ->options(function () {
                                return $this->getOwnerRecord()
                                    ->programPoints()
                                    ->with('templatePoint')
                                    ->orderBy('day')
                                    ->orderBy('order')
                                    ->get()
                                    ->mapWithKeys(fn (EventProgramPoint $point) => [
                                        $point->id => sprintf(
                                            'Dzień %d • %02d. %s',
                                            (int) ($point->day ?? 1),
                                            (int) ($point->order ?? 1),
                                            $point->name ?? $point->templatePoint?->name ?? ('Punkt #'.$point->id)
                                        ),
                                    ]);
                            }),

                        EventProgramPointPricingFields::section([
                            'default_participant_count' => max(1, (int) ($this->getOwnerRecord()->participant_count ?? 1)),
                            'gratis_count' => max(0, $this->getOwnerRecord()->resolveGratisCountForParticipantCount()),
                            'pilot_count' => ProgramPointCostPricing::pilotCount($this->getOwnerRecord()),
                            'driver_count' => max(0, $this->getOwnerRecord()->resolveDriverCountForParticipantCount()),
                        ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('start_time')
                                    ->label('Godzina startu')
                                    ->options(ProgramTimeSlots::options())
                                    ->searchable()
                                    ->nullable()
                                    ->placeholder('—')
                                    ->rule('required_with:end_time'),

                                Forms\Components\Select::make('end_time')
                                    ->label('Godzina końca')
                                    ->options(ProgramTimeSlots::options())
                                    ->searchable()
                                    ->nullable()
                                    ->placeholder('—')
                                    ->rule('required_with:start_time'),
                            ]),

                        $this->programPointRichTextField('notes')
                            ->label('Uwagi specjalne dla tej imprezy')
                            ->placeholder('Dodatkowe uwagi specyficzne dla tej imprezy...')
                            ->columnSpanFull(),

                        $this->programPointRichTextField('office_notes')
                            ->label('Uwagi dla biura')
                            ->columnSpanFull(),

                        $this->programPointRichTextField('pilot_notes')
                            ->label('Uwagi dla pilota')
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('active')
                            ->label('Aktywny')
                            ->default(true),
                    ])
                    ->action(function (array $data) {
                        $source = $data['source_point'] ?? null;
                        $templatePoint = null;
                        $eventPoint = null;
                        $createdPoint = null;
                        if ($source && str_starts_with($source, 'template_')) {
                            $id = (int) str_replace('template_', '', $source);
                            $templatePoint = EventTemplateProgramPoint::find($id);
                        } elseif ($source && str_starts_with($source, 'event_')) {
                            $id = (int) str_replace('event_', '', $source);
                            $eventPoint = EventProgramPoint::find($id);
                        }

                        $day = $this->resolveDayForNewProgramPoint(
                            isset($data['day']) ? (int) $data['day'] : null
                        );
                        $data['day'] = $day;

                        $unitPrice = (float) ($data['unit_price'] ?? ($templatePoint?->unit_price ?? $eventPoint?->unit_price ?? 0));
                        $event = $this->getOwnerRecord();
                        $participantCount = max(1, (int) ($event->participant_count ?? 1));
                        $pricingPayload = EventProgramPointPricingFields::mergePricingIntoPayload(
                            $data,
                            $unitPrice,
                            $participantCount,
                            max(0, $event->resolveGratisCountForParticipantCount($participantCount)),
                            ProgramPointCostPricing::pilotCount($event),
                            max(0, $event->resolveDriverCountForParticipantCount($participantCount)),
                        );

                        // Klonowanie z szablonu lub innej imprezy
                        if ($templatePoint) {
                            $creator = app(EventProgramPointCreator::class);
                            $createdPoint = $creator->addFromTemplate(
                                $this->getOwnerRecord(),
                                $templatePoint,
                                $day,
                                $data['parent_id'] ?? null,
                                cloneTemplateChildren: empty($data['parent_id']),
                                options: [
                                    'name' => $data['name'] ?? null,
                                    'description' => $data['description'] ?? null,
                                    'order' => $data['order'] ?? null,
                                    'start_time' => $data['start_time'] ?? null,
                                    'end_time' => $data['end_time'] ?? null,
                                    'unit_price' => $pricingPayload['unit_price'],
                                    'quantity' => $pricingPayload['quantity'],
                                    'total_price' => $pricingPayload['total_price'],
                                    'currency_id' => $pricingPayload['currency_id'],
                                    'convert_to_pln' => $pricingPayload['convert_to_pln'],
                                    'planned_price' => $pricingPayload['planned_price'],
                                    'paid_price' => $pricingPayload['paid_price'],
                                    'group_size' => $data['group_size'] ?? $templatePoint->group_size ?? null,
                                    'notes' => $data['notes'] ?? null,
                                    'office_notes' => $data['office_notes'] ?? $templatePoint->office_notes ?? null,
                                    'pilot_notes' => $data['pilot_notes'] ?? $templatePoint->pilot_notes ?? null,
                                    'include_in_program' => (bool) ($data['include_in_program'] ?? true),
                                    'include_in_calculation' => (bool) ($data['include_in_calculation'] ?? true),
                                    'include_gratis_in_cost' => (bool) ($data['include_gratis_in_cost'] ?? $templatePoint->include_gratis_in_cost ?? false),
                                    'include_pilot_in_cost' => (bool) ($data['include_pilot_in_cost'] ?? $templatePoint->include_pilot_in_cost ?? false),
                                    'include_driver_in_cost' => (bool) ($data['include_driver_in_cost'] ?? $templatePoint->include_driver_in_cost ?? false),
                                    'active' => (bool) ($data['active'] ?? true),
                                ],
                            );
                        } elseif ($eventPoint) {
                            $cloneRecursive = function ($sourcePoint, $eventId, $parentId = null, $day = null) use (&$cloneRecursive) {
                                $cloned = $sourcePoint->replicate();
                                $cloned->event_id = $eventId;
                                $cloned->parent_id = $parentId;
                                $cloned->day = (int) $day;
                                $cloned->order = $sourcePoint->order;
                                $cloned->save();
                                foreach ($sourcePoint->children as $child) {
                                    $cloneRecursive($child, $eventId, $cloned->id, $cloned->day);
                                }

                                return $cloned;
                            };
                            $createdPoint = $cloneRecursive($eventPoint, $this->getOwnerRecord()->id, $data['parent_id'] ?? null, $day);
                            $createdPoint->update([
                                'include_in_program' => (bool) ($data['include_in_program'] ?? true),
                                'include_in_calculation' => (bool) ($data['include_in_calculation'] ?? true),
                                'active' => (bool) ($data['active'] ?? true),
                            ]);
                        } else {
                            $createdPoint = $this->getOwnerRecord()->programPoints()->create([
                                'name' => $data['name'],
                                'description' => $data['description'] ?? null,
                                'day' => $day,
                                'order' => $data['order'],
                                'parent_id' => $data['parent_id'] ?? null,
                                'start_time' => $data['start_time'] ?? null,
                                'end_time' => $data['end_time'] ?? null,
                                'unit_price' => $pricingPayload['unit_price'],
                                'quantity' => $pricingPayload['quantity'],
                                'total_price' => $pricingPayload['total_price'],
                                'currency_id' => $pricingPayload['currency_id'],
                                'convert_to_pln' => $pricingPayload['convert_to_pln'],
                                'planned_price' => $pricingPayload['planned_price'],
                                'paid_price' => $pricingPayload['paid_price'],
                                'group_size' => $data['group_size'] ?? null,
                                'notes' => $data['notes'] ?? null,
                                'office_notes' => $data['office_notes'] ?? null,
                                'pilot_notes' => $data['pilot_notes'] ?? null,
                                'include_in_program' => (bool) ($data['include_in_program'] ?? true),
                                'include_in_calculation' => (bool) ($data['include_in_calculation'] ?? true),
                                'include_gratis_in_cost' => (bool) ($data['include_gratis_in_cost'] ?? false),
                                'include_pilot_in_cost' => (bool) ($data['include_pilot_in_cost'] ?? false),
                                'include_driver_in_cost' => (bool) ($data['include_driver_in_cost'] ?? false),
                                'active' => (bool) ($data['active'] ?? true),
                            ]);
                        }

                        $this->finalizeProgramPointCreation($createdPoint);
                    }),

                Tables\Actions\Action::make('copy_from_template')
                    ->label('Skopiuj z szablonu')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->action(function (): void {
                        $event = $this->getOwnerRecord();
                        $event->copyProgramPointsFromTemplate();

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Skopiowano program ze szablonu')
                            ->body('Zaimportowano '.$event->programPoints()->count().' punktów (w tym podpunkty setów).')
                            ->send();

                        $this->resetTable();
                        $this->dispatch('event-program-points-refresh');
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Skopiuj program z szablonu')
                    ->modalDescription('To działanie skopiuje wszystkie punkty programu z szablonu. Istniejące punkty zostaną zastąpione.')
                    ->visible(fn () => $this->getOwnerRecord()->programPoints()->count() === 0),
            ])
            ->actions([
                Tables\Actions\Action::make('normalize_legacy_price')
                    ->label('Napraw kwotę')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('warning')
                    ->button()
                    ->extraAttributes(['class' => 'w-full'])
                    ->requiresConfirmation()
                    ->modalHeading('Naprawić kwotę legacy?')
                    ->modalDescription('Przeliczy ilość i kwotę punktu na podstawie uczestników imprezy, aby nie był traktowany jako pojedynczy wydatek.')
                    ->action(function (EventProgramPoint $record): void {
                        if ($this->normalizeLegacyPointPricing($record)) {
                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Kwota punktu naprawiona')
                                ->send();

                            return;
                        }

                        \Filament\Notifications\Notification::make()
                            ->info()
                            ->title('Brak zmian')
                            ->body('Punkt nie wygląda na legacy lub jest już poprawnie wyliczony.')
                            ->send();
                    })
                    ->visible(fn (EventProgramPoint $record): bool => $this->isLegacySingleUnitPoint($record)),

                ...$this->programPointFinanceTableActions(),

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make('edit')
                        ->mutateFormDataUsing(function (array $data): array {
                            if (array_key_exists('day', $data)) {
                                $data['day'] = $this->getOwnerRecord()
                                    ->clampProgramPointDay((int) $data['day']);
                            }

                            return $data;
                        })
                        ->before(function (EventProgramPoint $record): void {
                            $this->pendingProgramPointTimeEdit = [
                                'start_time' => $record->start_time ? substr((string) $record->start_time, 0, 5) : null,
                                'end_time' => $record->end_time ? substr((string) $record->end_time, 0, 5) : null,
                            ];
                        })
                        ->after(function (EventProgramPoint $record, array $data): void {
                            $newStart = filled($data['start_time'] ?? null)
                                ? substr((string) $data['start_time'], 0, 5)
                                : null;
                            $newEnd = filled($data['end_time'] ?? null)
                                ? substr((string) $data['end_time'], 0, 5)
                                : null;
                            $prev = $this->pendingProgramPointTimeEdit;
                            $timesChanged = ($prev['start_time'] ?? null) !== $newStart
                                || ($prev['end_time'] ?? null) !== $newEnd;

                            if (
                                $timesChanged
                                && $newStart
                                && $newEnd
                                && ! (bool) ($data['hide_times'] ?? $record->hide_times)
                            ) {
                                if ($record->parent_id === null) {
                                    app(EventProgramScheduleService::class)->applyManualTimeChange(
                                        $record->fresh(),
                                        $newStart,
                                        $newEnd,
                                    );
                                } else {
                                    $parent = EventProgramPoint::find($record->parent_id);
                                    if ($parent) {
                                        app(ProgramPointSetTimePropagator::class)->propagateFromParent($parent->fresh());
                                    }
                                }
                            } else {
                                app(ProgramPointSetTimePropagator::class)->propagateFromParent($record->fresh());
                            }

                            $this->dispatch('event-program-points-refresh');
                        })
                        ->extraModalFooterActions([
                            Tables\Actions\Action::make('assign_contractor_to_days')
                                ->label('Przypisz kontrahenta do innych dni')
                                ->icon('heroicon-o-user-group')
                                ->color('gray')
                                ->visible(fn (EventProgramPoint $record): bool => filled($record->contractor_id))
                                ->form([
                                    Forms\Components\Select::make('scope')
                                        ->label('Zakres')
                                        ->options([
                                            'same_point' => 'Ten sam punkt w innych dniach',
                                            'selected_days' => 'Ten sam punkt — wybrane dni',
                                            'same_type' => 'Ten sam typ punktu (np. przejazdy)',
                                            'all_days' => 'Wszystkie punkty imprezy',
                                        ])
                                        ->default('same_point')
                                        ->required()
                                        ->live()
                                        ->helperText('Domyślnie: identyczne punkty (ten sam szablon / nazwa). Szersze zakresy na dole listy.'),
                                    Forms\Components\CheckboxList::make('days')
                                        ->label('Dni')
                                        ->options(function (): array {
                                            $event = $this->getOwnerRecord();

                                            return app(ProgramPointContractorBulkAssignService::class)
                                                ->availableDays($event)
                                                ->mapWithKeys(fn (int $day) => [$day => $event->programDayLabel($day)])
                                                ->all();
                                        })
                                        ->visible(fn (Forms\Get $get): bool => $get('scope') === 'selected_days')
                                        ->columns(['default' => 1, 'md' => 2, 'xl' => 3]),
                                ])
                                ->action(function (EventProgramPoint $record, array $data): void {
                                    $updated = app(ProgramPointContractorBulkAssignService::class)->assign(
                                        $record,
                                        $this->getOwnerRecord(),
                                        $data['scope'] ?? 'same_point',
                                        $data['days'] ?? null,
                                    );

                                    \Filament\Notifications\Notification::make()
                                        ->title('Przypisano kontrahenta')
                                        ->body($updated > 0
                                            ? "Zaktualizowano {$updated} punktów programu."
                                            : 'Brak punktów do aktualizacji.')
                                        ->success()
                                        ->send();
                                }),
                        ]),

                    Tables\Actions\DeleteAction::make()
                        ->modalHeading(function (EventProgramPoint $record): string {
                            return app(EventProgramPointDeletionService::class)
                                ->describeDeletion($record)['heading'];
                        })
                        ->modalDescription(function (EventProgramPoint $record): string {
                            return app(EventProgramPointDeletionService::class)
                                ->describeDeletion($record)['description'];
                        })
                        ->modalSubmitActionLabel('Usuń')
                        ->successNotification(null)
                        ->before(fn (EventProgramPoint $record) => $this->purgeProgramPointSettlementCosts($record))
                        ->using(function (EventProgramPoint $record): void {
                            $result = app(EventProgramPointDeletionService::class)->softDelete($record);
                            $this->notifyProgramPointDeletion($result);
                        }),
                    Tables\Actions\RestoreAction::make()
                        ->modalHeading(function (EventProgramPoint $record): string {
                            $label = $record->name ?? $record->templatePoint?->name ?? ('#'.$record->id);
                            $childCount = EventProgramPoint::onlyTrashed()
                                ->where('parent_id', $record->id)
                                ->count();

                            return $childCount > 0
                                ? 'Przywrócić set „'.$label.'” z podpunktami?'
                                : 'Przywrócić punkt „'.$label.'”?';
                        })
                        ->modalDescription(function (EventProgramPoint $record): ?string {
                            $childCount = EventProgramPoint::onlyTrashed()
                                ->where('parent_id', $record->id)
                                ->count();

                            return $childCount > 0
                                ? 'Przywrócisz set wraz z '.$childCount.' usuniętymi podpunktami.'
                                : null;
                        })
                        ->using(function (EventProgramPoint $record): void {
                            app(EventProgramPointDeletionService::class)->restore($record);
                            app(EventProgramPointDeletionService::class)->forgetUndo((int) $record->event_id);
                        }),
                    Tables\Actions\ForceDeleteAction::make()
                        ->modalHeading(function (EventProgramPoint $record): string {
                            $label = $record->name ?? $record->templatePoint?->name ?? ('#'.$record->id);
                            $childCount = EventProgramPoint::withTrashed()
                                ->where('parent_id', $record->id)
                                ->count();

                            return $childCount > 0
                                ? 'Na zawsze usunąć set „'.$label.'”?'
                                : 'Na zawsze usunąć punkt „'.$label.'”?';
                        })
                        ->modalDescription('Tej operacji nie da się cofnąć.')
                        ->before(fn (EventProgramPoint $record) => $this->purgeProgramPointSettlementCosts($record))
                        ->using(fn (EventProgramPoint $record) => app(EventProgramPointDeletionService::class)
                            ->forceDelete($record)),
                ])
                    ->label('Więcej')
                    ->icon('heroicon-o-ellipsis-vertical'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->modalHeading('Usunąć zaznaczone punkty?')
                        ->modalDescription('Sety zostaną usunięte wraz z podpunktami. Po usunięciu możesz cofnąć ostatnią operację zbiorczą.')
                        ->successNotification(null)
                        ->before(function ($records): void {
                            foreach ($records as $record) {
                                if ($record instanceof EventProgramPoint) {
                                    $this->purgeProgramPointSettlementCosts($record);
                                }
                            }
                        })
                        ->action(function ($records): void {
                            $service = app(EventProgramPointDeletionService::class);
                            $allIds = [];
                            $setCount = 0;
                            $pointCount = 0;

                            foreach ($records as $record) {
                                if (! $record instanceof EventProgramPoint) {
                                    continue;
                                }

                                if ($record->trashed()) {
                                    continue;
                                }

                                $result = $service->softDelete($record);
                                $allIds = array_merge($allIds, $result['point_ids']);
                                if ($result['was_set']) {
                                    $setCount++;
                                } elseif ($result['point_ids'] !== []) {
                                    $pointCount++;
                                }
                            }

                            $allIds = array_values(array_unique($allIds));

                            if ($allIds === []) {
                                return;
                            }

                            $title = 'Usunięto zaznaczone punkty';
                            $body = trim(
                                ($setCount > 0 ? "{$setCount} set(ów). " : '')
                                .($pointCount > 0 ? "{$pointCount} punkt(ów). " : '')
                                .'Możesz to cofnąć.'
                            );

                            $service->rememberUndoPayload((int) $this->getOwnerRecord()->id, [
                                'point_ids' => $allIds,
                                'was_set' => $setCount > 0,
                                'child_count' => max(0, count($allIds) - $setCount - $pointCount),
                                'label' => 'zaznaczenie',
                                'title' => $title,
                                'body' => $body,
                            ]);

                            $this->notifyProgramPointDeletion([
                                'point_ids' => $allIds,
                                'was_set' => $setCount > 0,
                                'child_count' => 0,
                                'label' => 'zaznaczenie',
                                'title' => $title,
                                'body' => $body,
                            ]);
                        }),
                    Tables\Actions\RestoreBulkAction::make()
                        ->action(function ($records): void {
                            $service = app(EventProgramPointDeletionService::class);

                            foreach ($records as $record) {
                                if ($record instanceof EventProgramPoint) {
                                    $service->restore($record);
                                }
                            }

                            $service->forgetUndo((int) $this->getOwnerRecord()->id);
                        }),
                    Tables\Actions\ForceDeleteBulkAction::make()
                        ->before(function ($records): void {
                            foreach ($records as $record) {
                                if ($record instanceof EventProgramPoint) {
                                    $this->purgeProgramPointSettlementCosts($record);
                                }
                            }
                        })
                        ->action(function ($records): void {
                            $service = app(EventProgramPointDeletionService::class);

                            foreach ($records as $record) {
                                if ($record instanceof EventProgramPoint) {
                                    $service->forceDelete($record);
                                }
                            }
                        }),

                    Tables\Actions\BulkAction::make('bulk_include_in_program_on')
                        ->label('Zaznacz w programie')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['include_in_program' => true])),

                    Tables\Actions\BulkAction::make('bulk_include_in_program_off')
                        ->label('Odznacz z programu')
                        ->icon('heroicon-o-x-circle')
                        ->color('gray')
                        ->action(fn ($records) => $records->each->update(['include_in_program' => false])),

                    Tables\Actions\BulkAction::make('bulk_include_in_calculation_on')
                        ->label('Dodaj do kosztów')
                        ->icon('heroicon-o-calculator')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['include_in_calculation' => true])),

                    Tables\Actions\BulkAction::make('bulk_include_in_calculation_off')
                        ->label('Wyłącz z kosztów')
                        ->icon('heroicon-o-calculator')
                        ->color('gray')
                        ->action(fn ($records) => $records->each->update(['include_in_calculation' => false])),

                    Tables\Actions\BulkAction::make('bulk_program_only')
                        ->label('Zostaw tylko w programie')
                        ->icon('heroicon-o-eye')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            $records->each->update([
                                'include_in_program' => true,
                                'include_in_calculation' => false,
                                'active' => true,
                            ]);
                        }),

                    Tables\Actions\BulkAction::make('bulk_include_in_settlement')
                        ->label('W programie i w kosztach')
                        ->icon('heroicon-o-calculator')
                        ->color('success')
                        ->action(function ($records): void {
                            $records->each->update([
                                'include_in_program' => true,
                                'include_in_calculation' => true,
                                'active' => true,
                            ]);
                        }),

                    Tables\Actions\BulkAction::make('bulk_hide_times_on')
                        ->label('Ukryj godziny')
                        ->icon('heroicon-o-eye-slash')
                        ->color('gray')
                        ->action(function ($records): void {
                            $records->each->update(['hide_times' => true]);
                            $this->dispatch('event-program-points-refresh');
                        }),

                    Tables\Actions\BulkAction::make('bulk_hide_times_off')
                        ->label('Pokaż godziny')
                        ->icon('heroicon-o-clock')
                        ->color('gray')
                        ->action(function ($records): void {
                            $records->each->update(['hide_times' => false]);
                            $this->dispatch('event-program-points-refresh');
                        }),

                    Tables\Actions\BulkAction::make('bulk_set_paid_by')
                        ->label('Ustaw płatnika')
                        ->icon('heroicon-o-user-group')
                        ->color('info')
                        ->form([
                            Forms\Components\Select::make('paid_by')
                                ->label('Płatnik')
                                ->options(EventSettlementCost::$paidByOptions)
                                ->required(),
                        ])
                        ->action(function ($records, array $data): void {
                            $paidBy = in_array($data['paid_by'] ?? '', ['office', 'pilot'], true)
                                ? $data['paid_by']
                                : 'office';

                            $settlement = EventSettlement::findOrCreateActiveForEvent($this->getOwnerRecord());
                            $plans = [];

                            foreach ($records as $record) {
                                if (! $record instanceof EventProgramPoint) {
                                    continue;
                                }

                                $plans[] = $settlement->upsertCostFromProgramPoint(
                                    $record->loadMissing('templatePoint', 'currency', 'event', 'reservations')
                                );
                            }

                            // Tylko plan — nie nadpisuj historycznych wpłat (zaliczka biura zostaje biurem).
                            $updated = app(\App\Actions\Finance\ChangeSettlementCostPayerAction::class)
                                ->forMany($plans, $paidBy);

                            app(\App\Services\PilotSettlementService::class)
                                ->refreshCashFromCosts($settlement->fresh() ?? $settlement);

                            $this->dispatch('event-program-points-refresh');

                            $label = EventSettlementCost::$paidByOptions[$paidBy] ?? $paidBy;

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Zaktualizowano płatnika')
                                ->body('Ustawiono „'.$label.'” dla '.$updated.' punktów programu (bez zmiany historycznych wpłat).')
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('bulk_fix_legacy_prices')
                        ->label('Napraw kwoty legacy')
                        ->icon('heroicon-o-wrench-screwdriver')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            $updated = 0;

                            foreach ($records as $record) {
                                if ($this->normalizeLegacyPointPricing($record)) {
                                    $updated++;
                                }
                            }

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Naprawiono punkty legacy')
                                ->body('Zaktualizowano: '.$updated)
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('bulk_activate')
                        ->label('Aktywuj')
                        ->icon('heroicon-o-bolt')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['active' => true])),

                    Tables\Actions\BulkAction::make('bulk_deactivate')
                        ->label('Dezaktywuj')
                        ->icon('heroicon-o-bolt-slash')
                        ->color('danger')
                        ->action(fn ($records) => $records->each->update(['active' => false])),
                ]),
            ])
            ->emptyStateHeading('Brak punktów programu')
            ->emptyStateDescription('Dodaj punkty programu lub skopiuj je z szablonu.')
            ->emptyStateIcon('heroicon-o-calendar-days');
    }

    protected function resolveProgramDayLabel(int $day): string
    {
        return $this->getOwnerRecord()->programDayLabel($day);
    }

    /**
     * Dzień dla nowego punktu: w widoku „Dzień” zawsze aktywna zakładka;
     * poza tym clamp do core / core+1 (fakultatyw).
     */
    protected function resolveDayForNewProgramPoint(?int $requestedDay): int
    {
        $event = $this->getOwnerRecord();

        if ($this->isProgramDaysTabView()) {
            $active = max(1, (int) ($this->getActiveProgramDayTab() ?? $this->ownerProgramDay ?? 1));

            return $event->clampProgramPointDay(
                $active,
                allowFacultative: $event->isFacultativeProgramDay($active),
            );
        }

        return $event->clampProgramPointDay(
            max(1, (int) ($requestedDay ?? $this->ownerProgramDay ?? 1)),
            allowFacultative: true,
        );
    }

    /**
     * Opcje selecta dnia: dni wycieczki + zawsze slot fakultatywny (core+1).
     *
     * @return array<int, string>
     */
    protected function programDaySelectOptions(): array
    {
        $event = $this->getOwnerRecord();
        $core = $event->resolveCoreProgramDaysCount();
        $options = [];

        for ($i = 1; $i <= $core; $i++) {
            $options[$i] = $event->programDayLabel($i);
        }

        $options[$event->facultativeProgramDay()] = 'Opcje fakultatywne';

        return $options;
    }

    protected function updateProgramPointVisibility(
        EventProgramPoint $record,
        bool $includeInProgram,
        bool $includeInCalculation,
        bool $active,
        string $title
    ): void {
        $record->update([
            'include_in_program' => $includeInProgram,
            'include_in_calculation' => $includeInCalculation,
            'active' => $active,
        ]);

        \Filament\Notifications\Notification::make()
            ->success()
            ->title($title)
            ->body($record->name ?? $record->templatePoint?->name ?? ('Punkt #'.$record->id))
            ->send();
    }

    protected function isLegacySingleUnitPoint(EventProgramPoint $record): bool
    {
        if (blank($record->event_template_program_point_id)) {
            return false;
        }

        $unit = (float) ($record->unit_price ?? 0);
        $storedTotal = (float) ($record->total_price ?? 0);
        $storedQuantity = max(1, (int) ($record->quantity ?? 1));
        $participantCount = (int) ($this->getOwnerRecord()->participant_count ?? 1);
        $expectedQuantity = $record->resolveCalculatedQuantity($participantCount);

        return $storedQuantity <= 1
            && abs($storedTotal - $unit) < 0.01
            && $expectedQuantity > 1
            && $unit > 0;
    }

    protected function normalizeLegacyPointPricing(EventProgramPoint $record): bool
    {
        if (! $this->isLegacySingleUnitPoint($record)) {
            return false;
        }

        $participantCount = (int) ($this->getOwnerRecord()->participant_count ?? 1);
        $quantity = $record->resolveCalculatedQuantity($participantCount);
        $total = $record->resolveEffectiveTotalPrice($participantCount);

        $record->update([
            'quantity' => $quantity,
            'total_price' => $total,
        ]);

        return true;
    }

    public function reorderTable(array $order): void
    {
        try {
            $service = app(EventProgramPointOrderService::class);
            $event = $this->getOwnerRecord();
            $expanded = $service->expandReorderOrderWithSets($event, $order);
            $service->applyDomReorder($event, $expanded);

            \Filament\Notifications\Notification::make()
                ->success()
                ->title('Zapisano kolejność')
                ->body('Nowa kolejność punktów programu została zapisana.')
                ->send();

            $this->resetTable();
            $this->dispatch('event-program-points-refresh');
        } catch (\Throwable $e) {
            report($e);

            \Filament\Notifications\Notification::make()
                ->danger()
                ->title('Nie udało się zapisać kolejności')
                ->body('Spróbuj ponownie. Jeśli problem się powtarza, użyj „Uporządkuj kolejność”.')
                ->send();
        }
    }

    protected function getProgramPointEditorToolbarButtons(): array
    {
        return [
            'h2',
            'h3',
            'bold',
            'italic',
            'underline',
            'strike',
            'blockquote',
            'bulletList',
            'orderedList',
            'link',
            'undo',
            'redo',
        ];
    }

    public function getTableRecords(): EloquentCollection|Paginator|CursorPaginator
    {
        $service = app(EventProgramPointOrderService::class);
        $event = $this->getOwnerRecord();
        $event->loadMissing('activeSettlement');
        $filteredIds = $this->getFilteredTableQuery()->pluck('id');

        if ($filteredIds->isEmpty()) {
            return new EloquentCollection;
        }

        $allPoints = EventProgramPoint::query()
            ->where('event_id', $event->id)
            ->withTrashed()
            ->with([
                'templatePoint',
                'contractor',
                'contractorLocation',
                'reservations' => fn ($reservations) => $reservations
                    ->withTrashed()
                    ->with(['contractor', 'historyEntries.user:id,name']),
                'hotelStays.reservation',
                'sharedReservation.contractor',
                'children',
                'parent',
            ])
            ->withCount('children')
            ->get();

        $this->programPointChildrenByParent = $allPoints
            ->whereNotNull('parent_id')
            ->groupBy(fn (EventProgramPoint $point): int => (int) $point->parent_id);

        $includeIds = $this->expandProgramPointFilterIds($allPoints, $filteredIds);
        $subset = $allPoints->whereIn('id', $includeIds->all());

        if ($day = $this->getActiveProgramDayTab()) {
            $subset = $subset->where('day', $day)->values();
        }

        $sorted = $service->sortedForDisplay($event, $subset);

        $records = $this->annotateProgramPointsForTable(
            $this->hydratePivotRelationForTableRecords(
                $this->filterCollapsedSetChildren($sorted)
            )
        );

        app(EventPaymentScheduleService::class)->warmCacheForProgramPoints($records, $event);
        $this->warmSettlementCachesForTableRecords($records, $event);

        $table = $this->getTable();

        if (
            $table->isPaginated()
            && ! ($this->isTableReordering() && (! $table->isPaginatedWhileReordering()))
        ) {
            $perPage = $this->getTableRecordsPerPage();
            $total = $records->count();
            $perPage = ($perPage === 'all') ? max(1, $total) : (int) $perPage;
            $page = max(1, $this->getTablePage());

            return new LengthAwarePaginator(
                $records->forPage($page, $perPage)->values(),
                $total,
                $perPage,
                $page,
                ['path' => request()->url(), 'pageName' => $this->getTablePaginationPageName()],
            );
        }

        return $records;
    }

    protected function settlementCosts(): ProgramPointSettlementCostCache
    {
        return $this->settlementCostCache ??= new ProgramPointSettlementCostCache;
    }

    protected function setFinanceAggregator(): ProgramPointSetFinanceAggregator
    {
        return app(ProgramPointSetFinanceAggregator::class, [
            'costCache' => $this->settlementCosts(),
        ]);
    }

    /**
     * @param  EloquentCollection<int, EventProgramPoint>  $records
     */
    protected function warmSettlementCachesForTableRecords(EloquentCollection $records, Event $event): void
    {
        $this->settlementCostCache = new ProgramPointSettlementCostCache;

        $aggregator = app(ProgramPointSetFinanceAggregator::class, [
            'costCache' => $this->settlementCostCache,
        ]);

        $childIds = $aggregator->childIdsForParents($records);

        if ($childIds !== []) {
            $childPoints = EventProgramPoint::query()->whereIn('id', $childIds)->get();
            app(EventPaymentScheduleService::class)->warmCacheForProgramPoints(
                $records->merge($childPoints),
                $event,
            );
        }

        $this->settlementCostCache->warm($records, $event);
    }

    private function programPointScopeFields(): Forms\Components\Fieldset
    {
        return Forms\Components\Fieldset::make('Gdzie ma być ten punkt')
            ->schema([
                Forms\Components\Toggle::make('include_in_program')
                    ->label('W programie')
                    ->helperText('Oferta, PDF, widok klienta i pilota.')
                    ->default(true)
                    ->inline(false),
                Forms\Components\Toggle::make('include_in_calculation')
                    ->label('W kosztach')
                    ->helperText('Wchodzi do kosztów i rozliczenia.')
                    ->default(true)
                    ->inline(false),
            ])
            ->columns(2);
    }

    private function programPointRichTextField(string $name): \FilamentTiptapEditor\TiptapEditor
    {
        // TipTap dehydruje pustą treść do null; zapisujemy '' żeby nie włączać
        // dziedziczenia z szablonu (resolvedDescription / resolved*Notes).
        return \FilamentTiptapEditor\TiptapEditor::make($name)
            ->live(onBlur: true)
            ->mutateDehydratedStateUsing(fn (mixed $state): string => is_string($state) ? $state : '');
    }

    /**
     * @param  EloquentCollection<int, EventProgramPoint>  $records
     * @return EloquentCollection<int, EventProgramPoint>
     */
    protected function filterCollapsedSetChildren(EloquentCollection $records): EloquentCollection
    {
        $expandedParentIds = collect($this->expandedSetIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        return new EloquentCollection(
            $records
                ->filter(function (EventProgramPoint $point) use ($expandedParentIds): bool {
                    if ($point->parent_id === null) {
                        return true;
                    }

                    return $expandedParentIds->contains((int) $point->parent_id);
                })
                ->values()
                ->all()
        );
    }

    /**
     * @param  EloquentCollection<int, EventProgramPoint>  $records
     * @return EloquentCollection<int, EventProgramPoint>
     */
    protected function annotateProgramPointsForTable(EloquentCollection $records): EloquentCollection
    {
        $ordered = $records->values();

        foreach ($ordered as $index => $record) {
            $day = (int) ($record->day ?? 1);

            $next = $ordered->get($index + 1);
            $isChild = filled($record->parent_id);
            $record->setAttribute('_is_set_child', $isChild);
            $record->setAttribute('_is_set_parent', ! $isChild && (int) ($record->children_count ?? 0) > 0);
            $prev = $ordered->get($index - 1);
            $record->setAttribute(
                '_is_first_set_child',
                $isChild && (
                    ! $prev
                    || (int) $prev->parent_id !== (int) $record->parent_id
                )
            );
            $record->setAttribute(
                '_is_last_set_child',
                $isChild && (
                    ! $next
                    || (int) $next->parent_id !== (int) $record->parent_id
                    || (int) ($next->day ?? 0) !== $day
                )
            );

            if ($record->getAttribute('_is_set_parent')) {
                $isExpanded = in_array((int) $record->id, $this->expandedSetIds, true);
                $children = $this->programPointChildrenByParent?->get($record->id, collect()) ?? collect();
                $visibleIds = $isExpanded
                    ? $ordered
                        ->where('parent_id', $record->id)
                        ->pluck('id')
                        ->map(fn ($id): int => (int) $id)
                        ->values()
                    : collect();

                $record->setAttribute('_set_expanded', $isExpanded);
                $record->setAttribute('_set_children_preview', $children);
                $record->setAttribute('_set_children_visible_ids', $visibleIds);
            }
        }

        return $ordered;
    }

    protected function resolveProgramPointRowClass(EventProgramPoint $record): string
    {
        $classes = ['epp-table-row'];

        if ($record->getAttribute('_is_set_parent')) {
            $classes[] = 'epp-table-row--set-parent';

            if ($record->getAttribute('_set_expanded')) {
                $classes[] = 'epp-table-row--set-expanded';
            }
        } elseif ($record->getAttribute('_is_set_child')) {
            $classes[] = 'epp-table-row--set-child';

            if ($record->getAttribute('_is_first_set_child')) {
                $classes[] = 'epp-table-row--set-child-first';
            }

            if ($record->getAttribute('_is_last_set_child')) {
                $classes[] = 'epp-table-row--set-child-last';
            }
        } else {
            $classes[] = 'epp-table-row--single';
        }

        if (! $record->active) {
            $classes[] = 'epp-table-row--inactive';
        }

        if (! $record->getAttribute('_is_set_parent')) {
            $inProgram = (bool) $record->include_in_program;
            $inCalc = (bool) $record->include_in_calculation;
            if (! $inProgram && ! $inCalc) {
                $classes[] = 'epp-table-row--out-of-scope';
            } elseif ($inProgram && ! $inCalc) {
                $classes[] = 'epp-table-row--program-only';
            } elseif (! $inProgram && $inCalc) {
                $classes[] = 'epp-table-row--calc-only';
            }
        }

        if ($record->trashed()) {
            $classes[] = 'epp-table-row--trashed';
        }

        return implode(' ', $classes);
    }

    /**
     * @param  EloquentCollection<int, EventProgramPoint>  $allPoints
     * @param  \Illuminate\Support\Collection<int, int|string>  $filteredIds
     * @return \Illuminate\Support\Collection<int, int>
     */
    protected function expandProgramPointFilterIds(EloquentCollection $allPoints, $filteredIds): \Illuminate\Support\Collection
    {
        $ids = $filteredIds->map(fn ($id) => (int) $id)->unique()->values();

        if ($this->usesStrictProgramPointFiltering()) {
            return $ids;
        }

        $byId = $allPoints->keyBy('id');
        $allowed = $ids->flip();
        $include = collect();

        foreach ($ids as $id) {
            $point = $byId->get($id);

            if (! $point) {
                continue;
            }

            $include->push($id);

            if ($point->parent_id) {
                $include->push((int) $point->parent_id);
            }

            foreach ($allPoints->where('parent_id', $point->id) as $child) {
                if ($allowed->has((int) $child->id)) {
                    $include->push((int) $child->id);
                }
            }
        }

        return $include->unique()->values();
    }

    protected function usesStrictProgramPointFiltering(): bool
    {
        if (($this->ownerProgramFilter ?? 'all') === 'program') {
            return true;
        }

        $filters = $this->tableFilters ?? [];

        if (! empty($filters['program_only']['isActive'] ?? false)) {
            return true;
        }

        foreach (['include_in_program', 'include_in_calculation', 'active'] as $key) {
            if (! array_key_exists($key, $filters)) {
                continue;
            }

            $value = $filters[$key]['value'] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            return true;
        }

        return false;
    }

    protected function isProgramDaysTabView(): bool
    {
        return $this->getPageClass() === EditEventProgram::class
            && ($this->ownerProgramView ?? 'days') === 'days';
    }

    protected function isProgramListView(): bool
    {
        return $this->getPageClass() === EditEventProgram::class
            && ($this->ownerProgramView ?? 'days') === 'list';
    }

    protected function getActiveProgramDayTab(): ?int
    {
        if (! $this->isProgramDaysTabView()) {
            return null;
        }

        return max(1, (int) ($this->ownerProgramDay ?? 1));
    }

    /**
     * Następny numer kolejności w dniu (rodzice vs dzieci osobno — jak EventProgramPointCreator).
     */
    protected function nextProgramPointOrderForDay(int $day, ?int $parentId = null): int
    {
        $query = $this->getOwnerRecord()
            ->programPoints()
            ->where('day', $day);

        if ($parentId) {
            $query->where('parent_id', $parentId);
        } else {
            $query->whereNull('parent_id');
        }

        return (int) ($query->max('order') ?? 0) + 1;
    }

    protected static function renderReservationPreviewHtml(Reservation $reservation): string
    {
        $label = e($reservation->booking_reference ?: ('#'.$reservation->id));
        $status = e(Reservation::$statuses[$reservation->status] ?? $reservation->status);
        $deposit = e(ReservationWorkflowDisplay::depositStatusLabel($reservation));
        $lines = collect(ReservationWorkflowDisplay::workflowLines($reservation))
            ->map(fn (string $line): string => '<div class="text-xs text-gray-600">'.e($line).'</div>')
            ->implode('');
        $editUrl = e(ReservationResource::getUrl('edit', ['record' => $reservation]));

        return '<div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 p-3 text-sm space-y-1">'
            .'<div><span class="font-semibold">Rez. '.$label.'</span> · <span class="text-primary-700">'.$status.'</span> · '.$deposit.'</div>'
            .$lines
            .'<div class="pt-1"><a href="'.$editUrl.'" class="text-primary-600 hover:underline text-xs" target="_blank">Pełna edycja</a></div>'
            .'</div>';
    }

    /**
     * Memoizacja podsumowania finansów w ramach jednego renderu wiersza tabeli.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $programPointFinanceViewDataCache = [];

    /**
     * @return array{
     *     calc: string,
     *     planned: string,
     *     paid: string,
     *     paidStatus: string,
     *     advanceHtml: string|null,
     *     paymentHint: string|null,
     *     pilotDueHint: string|null,
     *     remainingHint: string|null,
     *     documentHint: string|null,
     *     hasUploadedFile: bool,
     *     statusLabel: string|null,
     *     statusColor: string,
     *     paidBy: string|null,
     *     payerHint: string|null,
     * }
     */
    protected function buildProgramPointPricesSummaryViewData(EventProgramPoint $record): array
    {
        $id = (int) $record->id;
        if (array_key_exists($id, $this->programPointFinanceViewDataCache)) {
            return $this->programPointFinanceViewDataCache[$id];
        }

        if ($record->getAttribute('_is_set_parent')) {
            return $this->programPointFinanceViewDataCache[$id] = $this->emptySetParentFinanceViewData();
        }

        $summary = app(ProgramPointListFinanceDisplay::class)->summarizePoint(
            $record,
            $this->settlementCosts(),
            max(1, (int) ($record->event?->participant_count ?? $this->getOwnerRecord()->participant_count ?? 1)),
        );

        return $this->programPointFinanceViewDataCache[$id] = $summary;
    }

    /**
     * @return array<string, mixed>
     */
    public function programPointFinanceViewData(EventProgramPoint $record): array
    {
        return $this->buildProgramPointPricesSummaryViewData($record);
    }

    /**
     * @return array{
     *     calc: string,
     *     planned: string,
     *     paid: string,
     *     paidStatus: string,
     *     advanceHtml: string|null,
     *     paymentHint: string|null,
     *     pilotDueHint: string|null,
     *     remainingHint: string|null,
     *     documentHint: string|null,
     *     hasUploadedFile: bool,
     *     statusLabel: string|null,
     *     statusColor: string,
     *     isSetRollup: bool,
     *     hideSetParentFinance: bool,
     * }
     */
    protected function emptySetParentFinanceViewData(): array
    {
        return [
            'calc' => '—',
            'calcSub' => null,
            'planned' => '—',
            'plannedSub' => null,
            'paid' => '—',
            'paidSub' => null,
            'paidStatus' => 'none',
            'advanceHtml' => null,
            'paymentHint' => null,
            'pilotDueHint' => null,
            'remainingHint' => null,
            'remaining' => '—',
            'remainingSub' => null,
            'dueDateLabel' => null,
            'documentHint' => null,
            'documentStatusLabel' => null,
            'documentFirstUrl' => null,
            'documentBadgeLabel' => null,
            'hasUploadedFile' => false,
            'statusLabel' => null,
            'statusColor' => 'gray',
            'planDiffersFromCalc' => false,
            'paidBy' => null,
            'payerHint' => null,
            'totalLine' => null,
            'advanceLine' => null,
            'remainingLine' => null,
            'paymentLines' => [],
            'isSetRollup' => false,
            'hideSetParentFinance' => true,
        ];
    }

    protected function notifyProgramPointDeletion(array $result): void
    {
        if (($result['point_ids'] ?? []) === []) {
            return;
        }

        Notification::make()
            ->title((string) ($result['title'] ?? 'Usunięto punkt programu'))
            ->body((string) ($result['body'] ?? 'Możesz to cofnąć.'))
            ->success()
            ->persistent()
            ->actions([
                NotificationAction::make('undo')
                    ->label('Cofnij')
                    ->button()
                    ->dispatch('undo-last-program-point-deletion'),
            ])
            ->send();

        $this->dispatch('event-program-points-refresh');
    }

    protected function purgeProgramPointSettlementCosts(EventProgramPoint $record): void
    {
        $pointIds = EventProgramPoint::query()
            ->withTrashed()
            ->where('event_id', $record->event_id)
            ->where(function ($query) use ($record): void {
                $query->where('id', $record->id)
                    ->orWhere('parent_id', $record->id);
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($pointIds === []) {
            return;
        }

        EventSettlementCost::query()
            ->whereIn('source_type', ['program_point', 'program_point_payment'])
            ->whereIn('source_id', $pointIds)
            ->delete();

        $settlement = $this->getOwnerRecord()->activeSettlement;

        if ($settlement) {
            $settlement->recalculateTotals();
        }

        $this->invalidateSettlementCostCache();
    }
}
