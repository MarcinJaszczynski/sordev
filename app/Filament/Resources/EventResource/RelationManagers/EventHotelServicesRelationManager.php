<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Filament\Forms\EventProgramPointPricingFields;
use App\Filament\Forms\ContractorWithLocationFields;
use App\Filament\Forms\TypedContractorSelect;
use App\Filament\Resources\EventResource\Concerns\ManagesProgramPointSettlementFinance;
use App\Models\ContractorType;
use App\Models\Currency;
use App\Models\EventHotelStay;
use App\Models\EventProgramPoint;
use App\Services\ContractorLocationService;
use App\Services\EventHotelServiceDuplicator;
use App\Services\ProgramPointPaymentStatusResolver;
use App\Support\CurrencyAmountDisplay;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class EventHotelServicesRelationManager extends RelationManager
{
    use ManagesProgramPointSettlementFinance;

    protected static string $relationship = 'hotelServiceProgramPoints';

    protected static ?string $title = 'Usługi hotelu';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $icon = 'heroicon-o-sparkles';

    protected bool $pendingApplyToAllDays = false;

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nazwa usługi')
                    ->placeholder('np. Bankiet, Obiadokolacja, DJ, Śniadanie')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('day')
                    ->label('Dzień')
                    ->numeric()
                    ->minValue(1)
                    ->default(1)
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get): void {
                        if (filled($get('contractor_id'))) {
                            return;
                        }

                        $contractorId = $this->defaultContractorForDay((int) $state);
                        if ($contractorId) {
                            $set('contractor_id', $contractorId);
                        }
                    }),

                Forms\Components\TextInput::make('order')
                    ->label('Kolejność')
                    ->numeric()
                    ->minValue(1)
                    ->helperText('Pozostaw puste, aby dodać na końcu dnia.'),

                Forms\Components\Placeholder::make('hotel_plan_required_notice')
                    ->label('Brak hotelu w planie noclegów')
                    ->content('Najpierw wybierz hotel w sekcji planu noclegów powyżej — zapisze się automatycznie. Potem dodaj usługę hotelu tutaj.')
                    ->visible(fn (): bool => $this->eventHotelContractorIds() === [])
                    ->columnSpanFull(),

                ...ContractorWithLocationFields::append(
                    TypedContractorSelect::make(
                        field: 'contractor_id',
                        label: 'Kontrahent (hotel)',
                        typeNames: ContractorType::hotelTypeNames(),
                        searchAllField: 'hotel_service_contractor_search_all',
                        defaultTypeOnCreate: 'hotel',
                        helperText: 'Tylko hotele z planu noclegów tej imprezy. Domyślnie hotel z wybranego dnia.',
                        default: fn (): ?int => $this->defaultContractorForDay(1),
                        restrictToContractorIds: fn (): array => $this->eventHotelContractorIds(),
                        afterStateUpdated: function ($state, callable $set): void {
                            app(ContractorLocationService::class)->syncLocationOnContractorChange(
                                $set,
                                filled($state) ? (int) $state : null,
                            );
                        },
                    ),
                ),

                EventProgramPointPricingFields::section([
                    'default_participant_count' => max(1, (int) ($this->getOwnerRecord()->participant_count ?? 1)),
                    'pricing_basis_selector' => true,
                ]),

                Forms\Components\Toggle::make('include_in_program')
                    ->label('Pokaż w programie')
                    ->helperText('Czy ta usługa ma być widoczna w programie imprezy.')
                    ->default(true)
                    ->inline(false),

                Forms\Components\Toggle::make('apply_to_all_days')
                    ->label('Dołącz do każdego dnia')
                    ->helperText('Po zapisie skopiuje tę usługę na wszystkie pozostałe noce imprezy.')
                    ->default(false)
                    ->inline(false)
                    ->visible(fn (string $operation): bool => $operation === 'create'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('day')
                    ->label('Dzień')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Usługa')
                    ->state(fn (EventProgramPoint $record): string => '🏨 '.($record->name ?? '—'))
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('contractor.name')
                    ->label('Hotel / kontrahent')
                    ->default('—')
                    ->wrap(),

                Tables\Columns\TextColumn::make('cost')
                    ->label('Koszt')
                    ->state(function (EventProgramPoint $record): string {
                        $unit = (float) ($record->unit_price ?? 0);
                        $qty = max(1, (int) ($record->quantity ?? 1));

                        return CurrencyAmountDisplay::format(
                            $unit * $qty,
                            $record->currency,
                            (bool) ($record->convert_to_pln ?? true),
                        );
                    }),

                Tables\Columns\TextColumn::make('planned_price')
                    ->label('Planowana')
                    ->state(function (EventProgramPoint $record): string {
                        $amount = (float) ($record->planned_price ?? $record->total_price ?? 0);

                        return CurrencyAmountDisplay::format(
                            $amount,
                            $record->currency,
                            (bool) ($record->convert_to_pln ?? true),
                        );
                    }),

                Tables\Columns\TextColumn::make('calculated_price')
                    ->label('Kalkulacja')
                    ->state(function (EventProgramPoint $record): string {
                        $amount = (float) ($record->calculated_price ?? $record->total_price ?? 0);

                        return CurrencyAmountDisplay::format(
                            $amount,
                            $record->currency,
                            (bool) ($record->convert_to_pln ?? true),
                        );
                    }),

                Tables\Columns\TextColumn::make('paid_price')
                    ->label('Zapłacona')
                    ->state(function (EventProgramPoint $record): string {
                        return CurrencyAmountDisplay::format(
                            (float) ($record->paid_price ?? 0),
                            $record->currency,
                            (bool) ($record->convert_to_pln ?? true),
                        );
                    }),

                Tables\Columns\TextColumn::make('settlement')
                    ->label('Rozliczenie')
                    ->state(function (EventProgramPoint $record): string {
                        $planned = (float) ($record->planned_price ?? $record->total_price ?? 0);
                        $paid = (float) ($record->paid_price ?? 0);

                        return number_format($paid, 2, ',', ' ').' / '.number_format($planned, 2, ',', ' ').' PLN';
                    }),

                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (EventProgramPoint $record): string => app(ProgramPointPaymentStatusResolver::class)
                        ->resolve($record, $this->getOwnerRecord())['code'])
                    ->color(function (EventProgramPoint $record): string {
                        $color = app(ProgramPointPaymentStatusResolver::class)
                            ->resolve($record, $this->getOwnerRecord())['color'];

                        return match ($color) {
                            'green' => 'success',
                            'red' => 'danger',
                            'orange' => 'warning',
                            default => 'gray',
                        };
                    })
                    ->tooltip(fn (EventProgramPoint $record): string => app(ProgramPointPaymentStatusResolver::class)
                        ->resolve($record, $this->getOwnerRecord())['tooltip']),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('hotel_scope')
                    ->label('Zakres hoteli')
                    ->options([
                        'event' => 'Tylko dla tej imprezy',
                        'all' => 'Wszystkie',
                    ])
                    ->default('event')
                    ->query(function ($query, array $data) {
                        if (($data['value'] ?? 'event') === 'all') {
                            return $query;
                        }

                        $event = $this->getOwnerRecord();
                        $contractorIds = $event->assignedHotelContractorIds();

                        if ($contractorIds->isEmpty()) {
                            return $query;
                        }

                        return $query->whereIn('contractor_id', $contractorIds);
                    }),
            ])
            ->defaultSort('day')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Dodaj usługę hotelu')
                    ->icon('heroicon-o-plus')
                    ->mutateFormDataUsing(fn (array $data): array => $this->prepareData($data))
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Usługa hotelu zapisana')
                    )
                    ->after(function (EventProgramPoint $record): void {
                        if ($this->pendingApplyToAllDays) {
                            $clones = app(EventHotelServiceDuplicator::class)
                                ->duplicateToAllDays($record, $this->getOwnerRecord());

                            foreach ($clones as $clone) {
                                $this->afterPersist($clone);
                            }

                            $this->pendingApplyToAllDays = false;
                        }

                        $this->afterPersist($record);
                    }),
            ])
            ->actions([
                ...$this->programPointFinanceTableActions(includePricingBreakdown: false),
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(fn (array $data): array => $this->prepareData($data))
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Usługa hotelu zapisana')
                    )
                    ->after(fn (EventProgramPoint $record) => $this->afterPersist($record)),
                Tables\Actions\DeleteAction::make()
                    ->after(fn () => $this->afterPersist()),
            ])
            ->emptyStateHeading('Brak dodatkowych usług hotelu')
            ->emptyStateDescription('Dodaj usługi takie jak bankiet, obiad czy DJ — zostaną policzone w kalkulacji i przypisane do hotelu.')
            ->emptyStateIcon('heroicon-o-sparkles');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareData(array $data): array
    {
        $data['is_hotel_service'] = true;
        $data['is_hotel'] = false;
        $data['is_transport'] = false;
        $data['active'] = true;
        $data['include_in_calculation'] = true;

        if (blank($data['contractor_id'] ?? null)) {
            $data['contractor_id'] = $this->defaultContractorForDay((int) ($data['day'] ?? 1));
        }

        if (blank($data['order'] ?? null)) {
            $data['order'] = $this->nextOrderForDay((int) ($data['day'] ?? 1));
        }

        $this->pendingApplyToAllDays = ! empty($data['apply_to_all_days']);
        unset(
            $data['apply_to_all_days'],
            $data['hotel_service_contractor_search_all'],
            $data['pricing_basis'],
        );

        if (! array_key_exists('group_size', $data) || $data['group_size'] === '' || $data['group_size'] === null) {
            $data['group_size'] = 1;
        }

        if (empty($data['currency_id'])) {
            $data['currency_id'] = Currency::defaultPlnId();
        }

        $participantCount = max(1, (int) ($this->getOwnerRecord()->participant_count ?? 1));

        return EventProgramPointPricingFields::mergePricingIntoPayload(
            $data,
            (float) ($data['unit_price'] ?? 0),
            $participantCount,
        );
    }

    /**
     * @return array<int, int>
     */
    protected function eventHotelContractorIds(): array
    {
        return $this->getOwnerRecord()
            ->assignedHotelContractorIds()
            ->all();
    }

    protected function afterPersist(?EventProgramPoint $record = null): void
    {
        $event = $this->getOwnerRecord();
        $event->calculateTotalCost();
        $event->refreshActiveSettlementCosts();

        // Atrybucja kontrahenta (hotel) w aktywnym rozliczeniu dla tej usługi.
        if ($record) {
            $settlement = $event->settlements()
                ->whereIn('status', ['draft', 'active', 'pilot_settled'])
                ->latest('id')
                ->first();

            $settlement?->upsertCostFromProgramPoint($record->loadMissing('templatePoint', 'currency'));
        }

        $this->dispatch('event-price-table-refresh');
        $this->dispatch('event-program-points-refresh');
    }

    protected function defaultContractorForDay(int $day): ?int
    {
        return EventHotelStay::query()
            ->where('event_id', $this->getOwnerRecord()->getKey())
            ->where('day', $day)
            ->value('contractor_id');
    }

    protected function nextOrderForDay(int $day): int
    {
        $max = EventProgramPoint::query()
            ->where('event_id', $this->getOwnerRecord()->getKey())
            ->where('day', $day)
            ->max('order');

        return ((int) $max) + 1;
    }
}
