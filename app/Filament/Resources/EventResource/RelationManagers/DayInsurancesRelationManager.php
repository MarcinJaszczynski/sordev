<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Actions\Events\CopyEventDayInsuranceAction;
use App\Actions\Events\CreateEventInsurancePolicyAction;
use App\Actions\Events\SyncEventDayInsurancesFromTemplateAction;
use App\Actions\Events\UpdateEventInsurancePolicyAction;
use App\Filament\Forms\EventReadinessFields;
use App\Filament\Resources\EventResource\Concerns\InteractsWithSettlementCostDrawer;
use App\Filament\Resources\EventResource\Concerns\ManagesDayInsuranceSettlementFinance;
use App\Models\Event;
use App\Models\EventDayInsurance;
use App\Models\EventInsurancePolicy;
use App\Models\EventTemplate;
use App\Models\Insurance;
use App\Services\InsuranceCostCalculator;
use App\Support\MoneyFormatter;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Livewire\WithFileUploads;

class DayInsurancesRelationManager extends RelationManager
{
    use InteractsWithSettlementCostDrawer;
    use ManagesDayInsuranceSettlementFinance;
    use WithFileUploads;

    protected static string $relationship = 'dayInsurances';

    protected static ?string $recordTitleAttribute = 'day';

    protected static ?string $title = 'Ubezpieczenia (polisy i pozycje dniowe)';

    protected static string $view = 'filament.resources.event-resource.relation-managers.day-insurances';

    public function mount(): void
    {
        parent::mount();
        $this->initializeSettlementCostDrawerForms();
    }

    protected function invalidateSettlementCostCaches(): void
    {
        \App\Services\EventFinanceOverviewService::forgetOverviewCacheForEvent((int) $this->settlementCostEvent()->id);
        unset($this->selectedRow);
        $this->resetTable();
        $this->dispatchSettlementFinanceChanged();
    }

    public function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form->schema([
            TextInput::make('day')->label('Dzień')->numeric()->required(),
            Select::make('insurance_id')
                ->label('Ubezpieczenie')
                ->relationship(
                    name: 'insurance',
                    titleAttribute: 'name',
                    modifyQueryUsing: fn ($query) => $query->orderBy('name'),
                )
                ->getOptionLabelFromRecordUsing(function (Insurance $record): string {
                    $type = Insurance::coverageTypeLabel($record->coverage_type);

                    return $type ? "{$record->name} ({$type})" : (string) $record->name;
                })
                ->preload()
                ->required()
                ->rules([
                    fn (Get $get): \Illuminate\Validation\Rules\Unique => Rule::unique('event_day_insurance', 'insurance_id')
                        ->where(fn ($query) => $query
                            ->where('event_id', $this->getOwnerRecord()->getKey())
                            ->where('day', (int) ($get('day') ?? 0))),
                ])
                ->validationMessages([
                    'unique' => 'To ubezpieczenie jest już przypisane do wybranego dnia.',
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['insurance', 'policy']))
            ->columns([
                Tables\Columns\TextColumn::make('day')->label('Dzień')->sortable(),
                Tables\Columns\TextColumn::make('insurance.name')->label('Ubezpieczenie'),
                Tables\Columns\TextColumn::make('insurance.coverage_type')
                    ->label('Typ')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => Insurance::coverageTypeLabel($state) ?? '—')
                    ->color(fn (?string $state): string => match ($state) {
                        'nnw' => 'info',
                        'kl' => 'success',
                        default => 'gray',
                    })
                    ->visible(fn (): bool => Schema::hasColumn('insurances', 'coverage_type')),
                Tables\Columns\TextColumn::make('policy.policy_number')
                    ->label('Nr polisy')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('estimated_cost')
                    ->label('Koszt (szablon)')
                    ->tooltip('Koszt z gratisami (jak w ofercie); cena/os. klienta = suma ÷ płacący.')
                    ->state(function (EventDayInsurance $record): string {
                        $event = $record->event ?? $this->getOwnerRecord();
                        $paying = max(1, (int) ($event->participant_count ?? 1));
                        $gratis = $event->resolveGratisCountForParticipantCount($paying);
                        $amount = InsuranceCostCalculator::dayAssignmentCost(
                            $record->insurance,
                            $paying,
                            $gratis
                        );

                        return $amount > 0
                            ? MoneyFormatter::format($amount, 'PLN')
                            : '—';
                    }),
                ...$this->dayInsuranceFinanceTableColumns(),
            ])
            ->recordAction('open_finance')
            ->headerActions([
                Tables\Actions\Action::make('import_from_template')
                    ->label('Wgraj z szablonu')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->form(function (): array {
                        if ($this->ownerEvent()->event_template_id) {
                            return [];
                        }

                        return [
                            Select::make('event_template_id')
                                ->label('Szablon imprezy')
                                ->options(
                                    EventTemplate::query()
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                )
                                ->searchable()
                                ->required()
                                ->helperText('Impreza nie ma podpiętego szablonu — wybierz, z którego wgrać ubezpieczenia dniowe.'),
                        ];
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Wgrać ubezpieczenia z szablonu?')
                    ->modalDescription(function (): string {
                        $templateName = $this->ownerEvent()->eventTemplate?->name;
                        $base = 'Doda brakujące produkty na dni trwania imprezy. Istniejące pozycje zostaną bez zmian.';

                        return filled($templateName)
                            ? 'Szablon: '.$templateName.'. '.$base
                            : $base;
                    })
                    ->action(function (array $data): void {
                        $this->importDayInsurancesFromTemplate(
                            isset($data['event_template_id']) ? (int) $data['event_template_id'] : null,
                        );
                    }),
                Tables\Actions\Action::make('add_insurances')
                    ->label('Dodaj ubezpieczenia')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Dodaj ubezpieczenia')
                    ->modalDescription('Jedna polisa + produkty na wybrany dzień. Możesz dodać kolejne polisy osobno (np. NNW i KL).')
                    ->modalSubmitActionLabel('Zapisz')
                    ->modalWidth('2xl')
                    ->fillForm(function (): array {
                        $day = 1;

                        return array_merge(
                            EventReadinessFields::insuranceFormStateFromPolicy(null),
                            [
                                'event_insurance_policy_id' => null,
                                'day' => $day,
                                'insurance_ids' => [],
                                '__insurance_upload_key' => 'create-'.$day.'-'.uniqid('', true),
                            ],
                        );
                    })
                    ->form($this->unifiedInsuranceFormSchema(creating: true))
                    ->extraModalFooterActions(function (Tables\Actions\Action $action): array {
                        return [
                            $action->makeModalSubmitAction('save_and_next', arguments: ['next' => true])
                                ->label('Zapisz i następny dzień')
                                ->visible(fn (): bool => $this->canSubmitAddInsurancesAndGoNext()),
                        ];
                    })
                    ->action(function (array $data, array $arguments, Form $form, Tables\Actions\Action $action): void {
                        $this->submitUnifiedInsuranceModal($data, $arguments, $form, $action);
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('edit_policy')
                    ->label('Edytuj')
                    ->icon('heroicon-o-pencil-square')
                    ->modalHeading('Edytuj ubezpieczenie')
                    ->modalSubmitActionLabel('Zapisz')
                    ->modalWidth('2xl')
                    ->fillForm(function (EventDayInsurance $record): array {
                        $policy = $record->policy;
                        $day = (int) $record->day;

                        // Bez polisy: tylko produkt z tego wiersza — nie wszystkie produkty dnia
                        // (zapis nowej polisy nie może podpiąć KL+TFG+TFP „przy okazji”).
                        $insuranceIds = $policy
                            ? $this->assignedInsuranceIdsForDay($day, (int) $policy->id)
                            : array_values(array_filter([(int) ($record->insurance_id ?? 0)]));

                        $doc = Event::normalizeInsuranceDocumentPath($policy?->document_path) ?? '';
                        $list = Event::normalizeInsuranceDocumentPath($policy?->insured_list_path) ?? '';

                        return array_merge(
                            EventReadinessFields::insuranceFormStateFromPolicy($policy, $this->ownerEvent()),
                            [
                                'event_insurance_policy_id' => $policy?->id,
                                'day' => $day,
                                'insurance_ids' => $insuranceIds,
                                // Remount Filepond przy każdym wierszu / zestawie plików (bez zmiany bazy).
                                '__insurance_upload_key' => implode('-', [
                                    'edit',
                                    (string) $record->getKey(),
                                    (string) ($policy?->id ?? 0),
                                    substr(sha1($doc.'|'.$list), 0, 10),
                                ]),
                            ],
                        );
                    })
                    ->form($this->unifiedInsuranceFormSchema(creating: false))
                    ->action(function (array $data, EventDayInsurance $record): void {
                        $this->persistUnifiedInsurance($data, creating: false);
                        $this->syncSettlementFromEvent();
                        $this->resetTable();

                        Notification::make()
                            ->title('Zapisano ubezpieczenie')
                            ->success()
                            ->send();
                    }),
                ...$this->dayInsuranceFinanceTableActions(),
                Tables\Actions\Action::make('copy_to_next_day')
                    ->label('Kopiuj na następny dzień')
                    ->icon('heroicon-o-arrow-right')
                    ->color('gray')
                    ->visible(fn (EventDayInsurance $record): bool => $this->canCopyDayInsuranceForward($record))
                    ->action(fn (EventDayInsurance $record) => $this->copyDayInsurance(
                        $record,
                        CopyEventDayInsuranceAction::MODE_NEXT,
                    )),
                Tables\Actions\Action::make('fill_remaining_days')
                    ->label('Wypełnij pozostałe dni')
                    ->icon('heroicon-o-forward')
                    ->color('gray')
                    ->visible(fn (EventDayInsurance $record): bool => $this->canCopyDayInsuranceForward($record))
                    ->requiresConfirmation()
                    ->modalHeading('Wypełnić pozostałe dni?')
                    ->modalDescription('Ten sam produkt ubezpieczenia zostanie dodany na wszystkie kolejne dni trwania imprezy (bez nadpisywania istniejących). Polisa zostanie zachowana.')
                    ->action(fn (EventDayInsurance $record) => $this->copyDayInsurance(
                        $record,
                        CopyEventDayInsuranceAction::MODE_REMAINING,
                    )),
                Tables\Actions\DeleteAction::make()
                    ->after(fn () => $this->syncSettlementFromEvent()),
            ]);
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    private function unifiedInsuranceFormSchema(bool $creating): array
    {
        return [
            Hidden::make('event_insurance_policy_id'),
            Fieldset::make('Polisa')
                ->columns(['default' => 1, 'md' => 2])
                ->schema(EventReadinessFields::insuranceInputComponents()),
            Fieldset::make('Produkty na dzień')
                ->columns(1)
                ->schema([
                    TextInput::make('day')
                        ->label('Dzień')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->maxValue(fn (): int => $this->ownerEvent()->resolveCoreProgramDaysCount())
                        ->helperText(fn (): string => 'Horyzont imprezy: '.$this->ownerEvent()->resolveCoreProgramDaysCount().' dni.')
                        ->live()
                        ->afterStateUpdated(function (Set $set, Get $get, mixed $state) use ($creating): void {
                            $day = (int) $state;
                            $policyId = $get('event_insurance_policy_id');
                            if ($creating && ! $policyId) {
                                $set('insurance_ids', []);

                                return;
                            }

                            $set(
                                'insurance_ids',
                                $this->assignedInsuranceIdsForDay($day, $policyId ? (int) $policyId : null),
                            );
                        }),
                    CheckboxList::make('insurance_ids')
                        ->label('Produkty')
                        ->options(fn (): array => $this->insuranceCatalogOptions())
                        ->descriptions(function (Get $get): array {
                            $day = (int) ($get('day') ?? 0);
                            $policyId = $get('event_insurance_policy_id');
                            $assignedElsewhere = $this->assignedInsuranceIdsForDay($day);
                            $onThisPolicy = $policyId
                                ? $this->assignedInsuranceIdsForDay($day, (int) $policyId)
                                : [];
                            $descriptions = [];
                            foreach ($assignedElsewhere as $id) {
                                if (in_array($id, $onThisPolicy, true)) {
                                    $descriptions[$id] = 'Już w tej polisie';
                                } else {
                                    $descriptions[$id] = 'Już na tym dniu (inna polisa / pozycja)';
                                }
                            }

                            return $descriptions;
                        })
                        ->disableOptionWhen(function (string $value, Get $get): bool {
                            $day = (int) ($get('day') ?? 0);
                            $policyId = $get('event_insurance_policy_id');
                            $id = (int) $value;
                            $onThisPolicy = $policyId
                                ? $this->assignedInsuranceIdsForDay($day, (int) $policyId)
                                : [];
                            if (in_array($id, $onThisPolicy, true)) {
                                return true;
                            }

                            return in_array($id, $this->assignedInsuranceIdsForDay($day), true);
                        })
                        ->columns(2)
                        ->helperText($creating
                            ? 'Zaznacz produkty do dodania w tej polisie. Już przypisane na dniu są zablokowane.'
                            : 'Zaznacz dodatkowe produkty. Już w tej polisie / na dniu są zablokowane — usuwanie w tabeli.'),
                ]),
        ];
    }

    private function canCopyDayInsuranceForward(EventDayInsurance $record): bool
    {
        if (! $record->insurance_id) {
            return false;
        }

        $event = $this->ownerEvent();

        return (int) $record->day < $event->resolveCoreProgramDaysCount();
    }

    /**
     * @return array<int, string>
     */
    private function insuranceCatalogOptions(): array
    {
        return Insurance::query()
            ->active()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function (Insurance $insurance): array {
                $type = Insurance::coverageTypeLabel($insurance->coverage_type);
                $label = $type ? "{$insurance->name} ({$type})" : (string) $insurance->name;

                return [$insurance->id => $label];
            })
            ->all();
    }

    /**
     * @return list<int>
     */
    private function assignedInsuranceIdsForDay(int $day, ?int $policyId = null): array
    {
        if ($day < 1) {
            return [];
        }

        $query = EventDayInsurance::query()
            ->where('event_id', $this->ownerEvent()->getKey())
            ->where('day', $day)
            ->whereNotNull('insurance_id');

        if ($policyId !== null && Schema::hasColumn('event_day_insurance', 'event_insurance_policy_id')) {
            $query->where('event_insurance_policy_id', $policyId);
        }

        return $query
            ->orderBy('insurance_id')
            ->pluck('insurance_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    private function canSubmitAddInsurancesAndGoNext(): bool
    {
        $data = $this->mountedTableActionsData[0] ?? [];
        $day = (int) ($data['day'] ?? 1);

        return $day < $this->ownerEvent()->resolveCoreProgramDaysCount();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $arguments
     */
    private function submitUnifiedInsuranceModal(array $data, array $arguments, Form $form, Tables\Actions\Action $action): void
    {
        $day = (int) ($data['day'] ?? 0);
        $ids = $data['insurance_ids'] ?? [];
        if (! is_array($ids)) {
            $ids = [];
        }

        $maxDay = $this->ownerEvent()->resolveCoreProgramDaysCount();
        $goNext = (bool) ($arguments['next'] ?? false) && $day < $maxDay;

        $policyIdBefore = isset($data['event_insurance_policy_id']) ? (int) $data['event_insurance_policy_id'] : 0;
        $isUpdate = $policyIdBefore > 0;

        if (! $isUpdate && $ids === [] && ! $this->policyFormHasContent($data)) {
            Notification::make()
                ->title('Uzupełnij polisę lub produkty')
                ->body('Podaj dane polisy i/lub zaznacz produkty na dzień.')
                ->warning()
                ->send();
            $action->halt();

            return;
        }

        $policy = $this->persistUnifiedInsurance($data, creating: ! $isUpdate);

        $this->syncSettlementFromEvent();
        $this->resetTable();

        Notification::make()
            ->title($goNext ? 'Zapisano — następny dzień' : ($isUpdate ? 'Zapisano ubezpieczenie' : 'Dodano ubezpieczenie'))
            ->success()
            ->send();

        if (! $goNext) {
            return;
        }

        $nextDay = $day + 1;
        $doc = Event::normalizeInsuranceDocumentPath($policy->document_path) ?? '';
        $list = Event::normalizeInsuranceDocumentPath($policy->insured_list_path) ?? '';
        $form->fill(array_merge(
            $policy->toFormState(),
            [
                'event_insurance_policy_id' => $policy->id,
                'day' => $nextDay,
                'insurance_ids' => [],
                '__insurance_upload_key' => implode('-', [
                    'next',
                    (string) $policy->id,
                    (string) $nextDay,
                    substr(sha1($doc.'|'.$list), 0, 10),
                ]),
            ],
        ));
        $action->halt();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistUnifiedInsurance(array $data, bool $creating): EventInsurancePolicy
    {
        $day = (int) ($data['day'] ?? 0);
        $ids = $data['insurance_ids'] ?? [];
        if (! is_array($ids)) {
            $ids = [];
        }

        $policyId = isset($data['event_insurance_policy_id']) ? (int) $data['event_insurance_policy_id'] : 0;

        if (! $creating && $policyId > 0) {
            $policy = EventInsurancePolicy::query()
                ->where('event_id', $this->ownerEvent()->getKey())
                ->whereKey($policyId)
                ->firstOrFail();

            return app(UpdateEventInsurancePolicyAction::class)(
                $policy,
                $data,
                $day > 0 ? $day : null,
                $ids,
            );
        }

        if ($policyId > 0) {
            // „Zapisz i następny dzień” — ta sama polisa.
            $policy = EventInsurancePolicy::query()
                ->where('event_id', $this->ownerEvent()->getKey())
                ->whereKey($policyId)
                ->firstOrFail();

            return app(UpdateEventInsurancePolicyAction::class)(
                $policy,
                $data,
                $day > 0 ? $day : null,
                $ids,
            );
        }

        return app(CreateEventInsurancePolicyAction::class)(
            $this->ownerEvent(),
            $data,
            $day > 0 ? $day : null,
            $ids,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function policyFormHasContent(array $data): bool
    {
        return filled($data['insurance_policy_number'] ?? null)
            || filled($data['insurance_terms'] ?? null)
            || filled($data['insurance_document_path'] ?? null)
            || filled($data['insurance_insured_list_path'] ?? null)
            || filled($data['insurance_amount'] ?? null);
    }

    private function importDayInsurancesFromTemplate(?int $templateId = null): void
    {
        $event = $this->ownerEvent();
        $resolvedTemplateId = $templateId ?: (int) ($event->event_template_id ?? 0);
        $template = $resolvedTemplateId > 0
            ? EventTemplate::query()->find($resolvedTemplateId)
            : null;

        if (! $template) {
            Notification::make()
                ->title('Brak szablonu')
                ->body('Wybierz szablon, z którego wgrać ubezpieczenia dniowe.')
                ->warning()
                ->send();

            return;
        }

        $created = app(SyncEventDayInsurancesFromTemplateAction::class)($event, $template);

        if ($created === []) {
            Notification::make()
                ->title('Nie dodano nowych pozycji')
                ->body('Szablon nie ma ubezpieczeń w horyzoncie imprezy albo wszystkie są już wgrane.')
                ->warning()
                ->send();

            return;
        }

        $this->syncSettlementFromEvent();
        $this->resetTable();

        Notification::make()
            ->title('Wgrano ubezpieczenia z szablonu')
            ->body('Dodano pozycji: '.count($created).'.')
            ->success()
            ->send();
    }

    private function copyDayInsurance(EventDayInsurance $record, string $mode): void
    {
        $created = app(CopyEventDayInsuranceAction::class)($record, $mode);

        if ($created === []) {
            Notification::make()
                ->title('Brak dni do uzupełnienia')
                ->body('Produkt jest już na kolejnych dniach albo to ostatni dzień imprezy.')
                ->warning()
                ->send();

            return;
        }

        $this->syncSettlementFromEvent();
        $this->resetTable();

        $label = $mode === CopyEventDayInsuranceAction::MODE_NEXT
            ? 'Dodano ubezpieczenie na następny dzień'
            : 'Uzupełniono pozostałe dni';

        Notification::make()
            ->title($label)
            ->body('Dodano pozycji: '.count($created).'.')
            ->success()
            ->send();
    }

    private function ownerEvent(): Event
    {
        /** @var Event $event */
        $event = $this->getOwnerRecord();

        return $event;
    }

    private function syncSettlementFromEvent(): void
    {
        $this->ownerEvent()->refreshActiveSettlementCosts();
    }
}
