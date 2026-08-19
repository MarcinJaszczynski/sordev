<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Actions\Events\AddEventDayInsurancesAction;
use App\Actions\Events\CopyEventDayInsuranceAction;
use App\Actions\Events\SyncEventDayInsurancesFromTemplateAction;
use App\Filament\Resources\EventResource\Concerns\InteractsWithSettlementCostDrawer;
use App\Filament\Resources\EventResource\Concerns\ManagesDayInsuranceSettlementFinance;
use App\Models\Event;
use App\Models\EventDayInsurance;
use App\Models\EventTemplate;
use App\Models\Insurance;
use App\Services\InsuranceCostCalculator;
use App\Support\MoneyFormatter;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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

    protected static ?string $title = 'Ubezpieczenia dzienne (kosztorys)';

    protected static string $view = 'filament.resources.event-resource.relation-managers.day-insurances';

    public function mount(): void
    {
        parent::mount();
        $this->initializeSettlementCostDrawerForms();
    }

    protected function invalidateSettlementCostCaches(): void
    {
        unset($this->selectedRow);
        $this->resetTable();
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
            Toggle::make('is_done')
                ->label('Zrobione (dzień)')
                ->helperText('Operacyjnie domknięte na dniu — nie księguje wpłaty automatycznie.')
                ->default(false)
                ->visible(fn (): bool => Schema::hasColumn('event_day_insurance', 'is_done')),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
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
                Tables\Columns\TextColumn::make('estimated_cost')
                    ->label('Kalkulacja')
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
                Tables\Columns\ToggleColumn::make('is_done')
                    ->label('Zrobione')
                    ->visible(fn (): bool => Schema::hasColumn('event_day_insurance', 'is_done'))
                    ->afterStateUpdated(fn () => $this->syncSettlementFromEvent()),
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
                                ->label('Szablon')
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
                    ->modalSubmitActionLabel('Zapisz')
                    ->modalWidth('lg')
                    ->fillForm(function (): array {
                        $day = 1;

                        return [
                            'day' => $day,
                            'insurance_ids' => $this->assignedInsuranceIdsForDay($day),
                        ];
                    })
                    ->form([
                        TextInput::make('day')
                            ->label('Dzień')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(fn (): int => $this->ownerEvent()->resolveCoreProgramDaysCount())
                            ->helperText(fn (): string => 'Horyzont imprezy: '.$this->ownerEvent()->resolveCoreProgramDaysCount().' dni.')
                            ->live()
                            ->afterStateUpdated(function (Set $set, mixed $state): void {
                                $set('insurance_ids', $this->assignedInsuranceIdsForDay((int) $state));
                            }),
                        CheckboxList::make('insurance_ids')
                            ->label('Produkty')
                            ->options(fn (): array => $this->insuranceCatalogOptions())
                            ->descriptions(function (Get $get): array {
                                $assigned = $this->assignedInsuranceIdsForDay((int) ($get('day') ?? 0));
                                $descriptions = [];
                                foreach ($assigned as $id) {
                                    $descriptions[$id] = 'Już na tym dniu';
                                }

                                return $descriptions;
                            })
                            ->disableOptionWhen(function (string $value, Get $get): bool {
                                return in_array(
                                    (int) $value,
                                    $this->assignedInsuranceIdsForDay((int) ($get('day') ?? 0)),
                                    true,
                                );
                            })
                            ->columns(2)
                            ->helperText('Zaznacz produkty do dodania. Już przypisane są zablokowane — usuwanie jest w tabeli.'),
                    ])
                    ->extraModalFooterActions(function (Tables\Actions\Action $action): array {
                        return [
                            $action->makeModalSubmitAction('save_and_next', arguments: ['next' => true])
                                ->label('Zapisz i następny dzień')
                                ->visible(fn (): bool => $this->canSubmitAddInsurancesAndGoNext()),
                        ];
                    })
                    ->action(function (array $data, array $arguments, Form $form, Tables\Actions\Action $action): void {
                        $this->submitAddedDayInsurances($data, $arguments, $form, $action);
                    }),
                Tables\Actions\CreateAction::make()
                    ->label('Dodaj jedną pozycję')
                    ->color('gray')
                    ->createAnother(false)
                    ->after(fn () => $this->syncSettlementFromEvent()),
            ])
            ->actions([
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
                    ->modalDescription('Ten sam produkt ubezpieczenia zostanie dodany na wszystkie kolejne dni trwania imprezy (bez nadpisywania istniejących).')
                    ->action(fn (EventDayInsurance $record) => $this->copyDayInsurance(
                        $record,
                        CopyEventDayInsuranceAction::MODE_REMAINING,
                    )),
                Tables\Actions\DeleteAction::make()
                    ->after(fn () => $this->syncSettlementFromEvent()),
            ]);
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
    private function assignedInsuranceIdsForDay(int $day): array
    {
        if ($day < 1) {
            return [];
        }

        return EventDayInsurance::query()
            ->where('event_id', $this->ownerEvent()->getKey())
            ->where('day', $day)
            ->whereNotNull('insurance_id')
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
    private function submitAddedDayInsurances(array $data, array $arguments, Form $form, Tables\Actions\Action $action): void
    {
        $day = (int) ($data['day'] ?? 0);
        $ids = $data['insurance_ids'] ?? [];
        if (! is_array($ids)) {
            $ids = [];
        }

        $created = app(AddEventDayInsurancesAction::class)($this->ownerEvent(), $day, $ids);
        $maxDay = $this->ownerEvent()->resolveCoreProgramDaysCount();
        $goNext = (bool) ($arguments['next'] ?? false) && $day < $maxDay;

        if ($created !== []) {
            $this->syncSettlementFromEvent();
            $this->resetTable();

            Notification::make()
                ->title($goNext ? 'Dodano — następny dzień' : 'Dodano ubezpieczenia')
                ->body('Dodano pozycji: '.count($created).'.')
                ->success()
                ->send();
        } elseif (! $goNext) {
            Notification::make()
                ->title('Nie dodano nowych pozycji')
                ->body('Nic nie zaznaczono albo wybrane produkty są już na tym dniu.')
                ->warning()
                ->send();

            $action->halt();

            return;
        }

        if (! $goNext) {
            return;
        }

        $nextDay = $day + 1;
        $form->fill([
            'day' => $nextDay,
            'insurance_ids' => $this->assignedInsuranceIdsForDay($nextDay),
        ]);
        $action->halt();
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
