<?php

namespace App\Filament\Resources\EventSettlementResource\RelationManagers;

use App\Filament\Resources\EventSettlementResource\Traits\DispatchesSettlementDataChanged;
use App\Filament\Forms\ParticipantPricingFields;
use App\Models\Contractor;
use App\Models\Currency;
use App\Models\EventProgramPoint;
use App\Models\EventSettlementCost;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ProgramPointsCostsRelationManager extends RelationManager
{
    use DispatchesSettlementDataChanged;

    protected static string $relationship = 'programPoints';

    protected static ?string $title = 'Punkty programu';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['templatePoint', 'currency', 'contractor']))
            ->columns([
                Tables\Columns\TextColumn::make('day')
                    ->label('Dzień')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('order')
                    ->label('Kolejność')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('display_name')
                    ->label('Punkt programu')
                    ->state(fn (EventProgramPoint $record): string => $record->name
                        ?: ($record->templatePoint?->name ?? ('Punkt #'.$record->id)))
                    ->description(fn (EventProgramPoint $record): ?string => $record->contractor?->name)
                    ->wrap()
                    ->searchable(query: function ($query, string $search) {
                        $query->where(function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhereHas('templatePoint', fn ($tq) => $tq->where('name', 'like', "%{$search}%"));
                        });
                    }),

                Tables\Columns\IconColumn::make('active')
                    ->label('Aktywny')
                    ->boolean(),

                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Cena jedn.')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(fn (EventProgramPoint $record) => ' '.$record->currency?->symbol),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Ilość')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('total_price')
                    ->label('Suma punktu')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(fn (EventProgramPoint $record) => ' '.$record->currency?->symbol),

                Tables\Columns\TextColumn::make('settlement_planned_pln')
                    ->label('Plan rozliczenia')
                    ->state(function (EventProgramPoint $record): string {
                        $cost = $this->findProgramPointCost($record);

                        if (! $cost) {
                            return '—';
                        }

                        return number_format((float) $cost->planned_amount_pln, 2, ',', ' ').' PLN';
                    })
                    ->color(fn (EventProgramPoint $record): string => $this->findProgramPointCost($record) ? 'gray' : 'warning'),

                Tables\Columns\BadgeColumn::make('settlement_payment_status')
                    ->label('Status płatności')
                    ->state(fn (EventProgramPoint $record): ?string => $this->findProgramPointCost($record)?->payment_status)
                    ->formatStateUsing(fn (?string $state) => EventSettlementCost::$paymentStatuses[$state ?? ''] ?? ($state ?: 'Brak kosztu'))
                    ->colors([
                        'gray' => fn (?string $state) => blank($state),
                        'warning' => ['planned', 'reservation_required', 'advance_required'],
                        'info' => 'reserved',
                        'success' => 'paid',
                        'danger' => 'cancelled',
                    ]),
            ])
            ->defaultSort('day')
            ->striped()
            ->filters([
                Tables\Filters\TernaryFilter::make('active')
                    ->label('Aktywny')
                    ->default(true),

                Tables\Filters\TernaryFilter::make('include_in_calculation')
                    ->label('W kalkulacji')
                    ->default(null),

                Tables\Filters\Filter::make('missing_settlement_cost')
                    ->label('Bez pozycji w rozliczeniu')
                    ->toggle()
                    ->query(function ($query) {
                        $settlementId = $this->getOwnerRecord()->id;

                        return $query->whereNotIn('id', function ($sub) use ($settlementId) {
                            $sub->select('source_id')
                                ->from('event_settlement_costs')
                                ->where('settlement_id', $settlementId)
                                ->where('source_type', 'program_point')
                                ->whereNotNull('source_id');
                        });
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('sync_program_costs')
                    ->label('Utwórz / odśwież koszty punktów')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalDescription('Dla każdego aktywnego punktu w kalkulacji utworzy lub zaktualizuje pozycję kosztu w tym rozliczeniu.')
                    ->action(function (): void {
                        $settlement = $this->getOwnerRecord();
                        $event = $settlement->event()->with('programPoints.currency', 'programPoints.templatePoint')->first();
                        $synced = 0;

                        foreach ($event?->programPoints ?? [] as $point) {
                            if (! $point->active || ! $point->include_in_calculation) {
                                continue;
                            }

                            $settlement->upsertCostFromProgramPoint($point);
                            $synced++;
                        }

                        $settlement->recalculateTotals();

                        Notification::make()
                            ->title("Zsynchronizowano {$synced} punktów programu")
                            ->success()
                            ->send();

                        $this->dispatchSettlementDataChanged();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('edit_point_cost')
                    ->label('Edytuj koszt')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->button()
                    ->size('sm')
                    ->modalHeading(fn (EventProgramPoint $record): string => 'Koszt punktu: '.($record->name ?: $record->templatePoint?->name ?? ('#'.$record->id)))
                    ->modalDescription('Zmiany ceny punktu programu są od razu widoczne w pozycji rozliczenia (plan).')
                    ->modalWidth('3xl')
                    ->fillForm(function (EventProgramPoint $record): array {
                        $settlement = $this->getOwnerRecord();
                        $event = $settlement->event;
                        $participantCount = max(1, (int) ($event?->participant_count ?? 1));
                        $cost = $this->findProgramPointCost($record);

                        return [
                            'unit_price' => (float) ($record->unit_price ?? 0),
                            'quantity' => (int) ($record->quantity ?? 1),
                            'group_size' => $record->group_size,
                            'currency_id' => $record->currency_id,
                            'convert_to_pln' => (bool) $record->convert_to_pln,
                            'total_price_preview' => $record->resolveEffectiveTotalPrice($participantCount),
                            'contractor_id' => $cost?->contractor_id ?? $record->contractor_id,
                            'paid_by' => $cost?->paid_by ?? 'office',
                            'payment_status' => $cost?->payment_status ?? 'planned',
                            'advance_type' => $cost?->advance_type ?? 'full',
                            'planned_amount' => $cost?->planned_amount,
                            'planned_currency_id' => $cost?->planned_currency_id ?? $record->currency_id,
                            'planned_rate' => $cost?->planned_rate ?? ($record->currency?->exchange_rate ?? 1),
                            'planned_amount_pln' => $cost?->planned_amount_pln,
                            'actual_amount' => $cost?->actual_amount,
                            'actual_currency_id' => $cost?->actual_currency_id,
                            'actual_rate' => $cost?->actual_rate,
                            'actual_amount_pln' => $cost?->actual_amount_pln,
                            'advance_amount' => $cost?->advance_amount,
                            'advance_due_date' => $cost?->advance_due_date,
                            'notes' => $cost?->notes,
                        ];
                    })
                    ->form([
                        Forms\Components\Section::make('Cena punktu programu (impreza)')
                            ->columns(3)
                            ->schema([
                                Forms\Components\TextInput::make('unit_price')
                                    ->label('Cena jednostkowa')
                                    ->numeric()
                                    ->step(0.01)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get): void {
                                        $qty = max(1, (int) ($get('quantity') ?: 1));
                                        $set('total_price_preview', round((float) $state * $qty, 2));
                                    }),

                                Forms\Components\TextInput::make('quantity')
                                    ->label('Ilość')
                                    ->numeric()
                                    ->minValue(1)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get): void {
                                        $unit = (float) ($get('unit_price') ?: 0);
                                        $set('total_price_preview', round($unit * max(1, (int) $state), 2));
                                    }),

                                Forms\Components\TextInput::make('group_size')
                                    ->label('Wielkość grupy')
                                    ->numeric()
                                    ->minValue(1)
                                    ->nullable(),

                                Forms\Components\Select::make('currency_id')
                                    ->label('Waluta')
                                    ->options(fn () => Currency::orderBy('name')->pluck('name', 'id'))
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Forms\Set $set): void {
                                        if ($state) {
                                            $set('planned_rate', Currency::find($state)?->exchange_rate ?? 1);
                                        }
                                    }),

                                Forms\Components\Toggle::make('convert_to_pln')
                                    ->label('Przelicz na PLN')
                                    ->inline(false),

                                Forms\Components\TextInput::make('total_price_preview')
                                    ->label('Suma punktu (podgląd)')
                                    ->numeric()
                                    ->suffix('PLN')
                                    ->readOnly()
                                    ->dehydrated(false),
                            ]),

                        Forms\Components\Section::make('Pozycja w rozliczeniu')
                            ->columns(3)
                            ->schema([
                                ParticipantPricingFields::settlementAmountBasisSelect('planned_amount_basis'),
                                ParticipantPricingFields::settlementPlannedScopeSelect('planned_participant_scope'),
                                Forms\Components\Select::make('contractor_id')
                                    ->label('Kontrahent')
                                    ->options(fn () => Contractor::orderBy('name')->pluck('name', 'id'))
                                    ->searchable()
                                    ->nullable(),

                                Forms\Components\Select::make('paid_by')
                                    ->label('Płaci')
                                    ->options(EventSettlementCost::$paidByOptions)
                                    ->required(),

                                Forms\Components\Select::make('payment_status')
                                    ->label('Status płatności')
                                    ->options(EventSettlementCost::$paymentStatuses)
                                    ->required(),

                                Forms\Components\Select::make('advance_type')
                                    ->label('Typ płatności')
                                    ->options(EventSettlementCost::$advanceTypes)
                                    ->required(),

                                Forms\Components\TextInput::make('planned_amount')
                                    ->label('Kwota planowana')
                                    ->numeric()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get): void {
                                        $rate = (float) ($get('planned_rate') ?: 1);
                                        $set('planned_amount_pln', round((float) $state * $rate, 2));
                                    }),

                                Forms\Components\Select::make('planned_currency_id')
                                    ->label('Waluta planu')
                                    ->options(fn () => Currency::orderBy('name')->pluck('name', 'id'))
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Forms\Set $set): void {
                                        if ($state) {
                                            $set('planned_rate', Currency::find($state)?->exchange_rate ?? 1);
                                        }
                                    }),

                                Forms\Components\TextInput::make('planned_rate')
                                    ->label('Kurs planu')
                                    ->numeric()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get): void {
                                        $amount = (float) ($get('planned_amount') ?: 0);
                                        $set('planned_amount_pln', round($amount * (float) $state, 2));
                                    }),

                                Forms\Components\TextInput::make('planned_amount_pln')
                                    ->label('Plan = PLN')
                                    ->numeric()
                                    ->suffix('PLN'),

                                Forms\Components\TextInput::make('actual_amount')
                                    ->label('Kwota zapłacona')
                                    ->numeric()
                                    ->nullable()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get): void {
                                        if ($state === null || $state === '') {
                                            return;
                                        }
                                        $rate = (float) ($get('actual_rate') ?: $get('planned_rate') ?: 1);
                                        $set('actual_amount_pln', round((float) $state * $rate, 2));
                                    }),

                                Forms\Components\Select::make('actual_currency_id')
                                    ->label('Waluta wpłaty')
                                    ->options(fn () => Currency::orderBy('name')->pluck('name', 'id'))
                                    ->searchable()
                                    ->nullable(),

                                Forms\Components\TextInput::make('actual_rate')
                                    ->label('Kurs wpłaty')
                                    ->numeric()
                                    ->nullable(),

                                Forms\Components\TextInput::make('actual_amount_pln')
                                    ->label('Wpłata = PLN')
                                    ->numeric()
                                    ->suffix('PLN')
                                    ->nullable(),

                                Forms\Components\TextInput::make('advance_amount')
                                    ->label('Zaliczka')
                                    ->numeric()
                                    ->nullable(),

                                Forms\Components\DateTimePicker::make('advance_due_date')
                                    ->label('Termin zaliczki')
                                    ->nullable(),

                                Forms\Components\Textarea::make('notes')
                                    ->label('Uwagi')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->action(function (EventProgramPoint $record, array $data): void {
                        $settlement = $this->getOwnerRecord();

                        $record->update([
                            'unit_price' => $data['unit_price'],
                            'quantity' => max(1, (int) ($data['quantity'] ?? 1)),
                            'group_size' => $data['group_size'] ?: null,
                            'currency_id' => $data['currency_id'] ?? null,
                            'convert_to_pln' => (bool) ($data['convert_to_pln'] ?? false),
                            'contractor_id' => $data['contractor_id'] ?? $record->contractor_id,
                        ]);

                        $record->refresh()->loadMissing(['currency', 'templatePoint', 'event', 'reservations']);

                        $cost = $settlement->upsertCostFromProgramPoint($record);

                        $cost->update([
                            'contractor_id' => $data['contractor_id'] ?? $cost->contractor_id,
                            'paid_by' => $data['paid_by'] ?? $cost->paid_by,
                            'payment_status' => $data['payment_status'] ?? $cost->payment_status,
                            'advance_type' => $data['advance_type'] ?? $cost->advance_type,
                            'planned_amount' => $data['planned_amount'] ?? $cost->planned_amount,
                            'planned_currency_id' => $data['planned_currency_id'] ?? $cost->planned_currency_id,
                            'planned_rate' => $data['planned_rate'] ?? $cost->planned_rate,
                            'planned_amount_pln' => $data['planned_amount_pln'] ?? $cost->planned_amount_pln,
                            'actual_amount' => $data['actual_amount'] ?? $cost->actual_amount,
                            'actual_currency_id' => $data['actual_currency_id'] ?? $cost->actual_currency_id,
                            'actual_rate' => $data['actual_rate'] ?? $cost->actual_rate,
                            'actual_amount_pln' => $data['actual_amount_pln'] ?? $cost->actual_amount_pln,
                            'advance_amount' => $data['advance_amount'] ?? $cost->advance_amount,
                            'advance_due_date' => $data['advance_due_date'] ?? $cost->advance_due_date,
                            'notes' => $data['notes'] ?? $cost->notes,
                        ]);

                        $settlement->recalculateTotals();

                        Notification::make()
                            ->title('Zapisano koszt punktu')
                            ->success()
                            ->send();

                        $this->dispatchSettlementDataChanged();
                    }),
            ])
            ->emptyStateHeading('Brak punktów programu')
            ->emptyStateDescription('Dodaj punkty programu w imprezie, a następnie użyj „Utwórz / odśwież koszty punktów”.');
    }

    private function findProgramPointCost(EventProgramPoint $record): ?EventSettlementCost
    {
        return $this->getOwnerRecord()
            ->costs()
            ->where('source_type', 'program_point')
            ->where('source_id', $record->id)
            ->first();
    }
}
