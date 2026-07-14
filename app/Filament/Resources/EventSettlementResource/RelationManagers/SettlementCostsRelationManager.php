<?php

namespace App\Filament\Resources\EventSettlementResource\RelationManagers;

use App\Filament\Forms\CurrencyConversionFields;
use App\Filament\Forms\ParticipantPricingFields;
use App\Filament\Forms\ReservationFormFields;
use App\Filament\Forms\ReservationFormOptions;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventSettlementResource\Traits\DispatchesSettlementDataChanged;
use App\Filament\Resources\TaskResource;
use App\Models\Contractor;
use App\Models\Currency;
use App\Models\CurrencyRateSnapshot;
use App\Models\EventDocument;
use App\Models\EventProgramPoint;
use App\Models\EventSettlementCost;
use App\Models\Reservation;
use App\Services\SettlementPaymentHealthService;
use App\Support\StoragePath;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class SettlementCostsRelationManager extends RelationManager
{
    use DispatchesSettlementDataChanged;

    protected static string $relationship = 'costs';

    protected static ?string $title = 'Koszty';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Pozycja')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nazwa kosztu')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Forms\Components\Select::make('source_type')
                        ->label('Źródło')
                        ->options([
                            'manual' => 'Ręczny wpis',
                            'program_point' => 'Punkt programu',
                            'program_point_payment' => 'Wpłata punktu programu',
                        ])
                        ->default('manual')
                        ->required(),

                    Forms\Components\Select::make('advance_type')
                        ->label('Typ płatności')
                        ->options(EventSettlementCost::$advanceTypes)
                        ->default('full')
                        ->live()
                        ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                            $current = $get('payment_status');
                            if (! in_array($current, ['planned', 'reservation_required', 'advance_required', null], true)) {
                                return;
                            }

                            if ($state === 'deposit') {
                                $set('payment_status', 'reservation_required');
                            } elseif ($state === 'advance') {
                                $set('payment_status', 'advance_required');
                            } else {
                                $set('payment_status', 'planned');
                            }
                        })
                        ->required(),

                    Forms\Components\Select::make('paid_by')
                        ->label('Płaci')
                        ->options(EventSettlementCost::$paidByOptions)
                        ->default('office')
                        ->required(),

                    Forms\Components\Select::make('contractor_id')
                        ->label('Kontrahent')
                        ->relationship('contractor', 'name')
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')
                                ->label('Nazwa kontrahenta')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('nip')
                                ->label('NIP')
                                ->nullable()
                                ->maxLength(20),
                            Forms\Components\TextInput::make('email')
                                ->label('Email')
                                ->email()
                                ->nullable(),
                            Forms\Components\TextInput::make('phone')
                                ->label('Telefon')
                                ->nullable(),
                        ])
                        ->createOptionUsing(fn (array $data): int => Contractor::create($data)->id),

                    Forms\Components\Select::make('payment_status')
                        ->label('Status płatności')
                        ->options(EventSettlementCost::$paymentStatuses)
                        ->default('planned')
                        ->helperText('Obsługiwane etapy: rezerwacja, zaliczka, częściowa płatność, pełna płatność.')
                        ->required(),
                ]),

            Forms\Components\Section::make('Koszt planowany')
                ->columns(3)
                ->schema([
                    ParticipantPricingFields::settlementAmountBasisSelect(),
                    ParticipantPricingFields::settlementPlannedScopeSelect(),

                    Forms\Components\TextInput::make('planned_unit_amount')
                        ->label(fn (Forms\Get $get): string => $get('planned_amount_basis') === 'per_person'
                            ? 'Stawka za osobę'
                            : 'Kwota łączna za grupę')
                        ->numeric()
                        ->nullable()
                        ->live(onBlur: true)
                        ->helperText(fn (Forms\Get $get): string => $get('planned_amount_basis') === 'per_person'
                            ? 'Mnożone przez liczbę uczestników/płacących z imprezy.'
                            : 'Kwota za całą grupę — bez mnożenia.')
                        ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get): void {
                            $count = max(1, (int) ($this->getOwnerRecord()->event?->participant_count ?? 1));
                            $unit = (float) ($state ?? 0);
                            $basis = (string) ($get('planned_amount_basis') ?? 'per_person');

                            if ($unit <= 0) {
                                return;
                            }

                            $total = $basis === 'per_person' ? round($unit * $count, 2) : round($unit, 2);
                            $set('planned_amount', $total);
                            CurrencyConversionFields::recalculatePlannedPln($set, $get);
                        }),

                    Forms\Components\TextInput::make('planned_amount')
                        ->label('Kwota planowana')
                        ->numeric()
                        ->required()
                        ->default(0)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => CurrencyConversionFields::recalculatePlannedPln($set, $get)),

                    CurrencyConversionFields::currencySelect('planned_currency_id')
                        ->live()
                        ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get): void {
                            if ($state) {
                                $c = Currency::find($state);
                                $set('planned_rate', $c?->exchange_rate ?? 1);
                            }

                            CurrencyConversionFields::recalculatePlannedPln($set, $get);
                        }),

                    CurrencyConversionFields::convertToggle('planned_convert_to_pln', 'planned_currency_id')
                        ->live()
                        ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => CurrencyConversionFields::recalculatePlannedPln($set, $get)),

                    Forms\Components\TextInput::make('planned_rate')
                        ->label('Kurs do PLN')
                        ->numeric()
                        ->default(1)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => CurrencyConversionFields::recalculatePlannedPln($set, $get)),

                    Forms\Components\TextInput::make('planned_amount_pln')
                        ->label(fn (Forms\Get $get): string => self::isForeignCurrency($get('planned_currency_id')) && ! (bool) ($get('planned_convert_to_pln') ?? true)
                            ? 'Suma w PLN'
                            : '= PLN')
                        ->numeric()
                        ->readOnly()
                        ->suffix('PLN')
                        ->placeholder(fn (Forms\Get $get): ?string => self::isForeignCurrency($get('planned_currency_id')) && ! (bool) ($get('planned_convert_to_pln') ?? true)
                            ? 'Bez przeliczenia'
                            : null),

                    Forms\Components\DateTimePicker::make('advance_due_date')
                        ->label('Termin zaliczki/rezerwacji')
                        ->nullable(),

                    Forms\Components\TextInput::make('advance_amount')
                        ->label('Kwota zaliczki')
                        ->numeric()
                        ->nullable(),
                ]),

            Forms\Components\Section::make('Płatność rzeczywista')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('actual_amount')
                        ->label('Kwota zapłacona')
                        ->numeric()
                        ->nullable()
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                            if ($state !== null) {
                                $rate = (float) ($get('actual_rate') ?: $get('planned_rate') ?: 1);
                                $set('actual_amount_pln', round($state * $rate, 2));
                            }
                        }),

                    Forms\Components\Select::make('actual_currency_id')
                        ->label('Waluta')
                        ->options(fn () => Currency::orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->nullable()
                        ->live()
                        ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                            if ($state) {
                                // Spróbuj znaleźć ostatni snapshot kursu
                                $snap = CurrencyRateSnapshot::latestFor($state);
                                if ($snap) {
                                    $set('rate_snapshot_id', $snap->id);
                                    $set('actual_rate', $snap->rate);
                                } else {
                                    $c = Currency::find($state);
                                    $set('actual_rate', $c?->exchange_rate ?? 1);
                                }
                            }
                        }),

                    Forms\Components\Select::make('rate_snapshot_id')
                        ->label('Snapshot kursu')
                        ->options(fn (Forms\Get $get) => CurrencyRateSnapshot::when(
                            $get('actual_currency_id'),
                            fn ($q, $id) => $q->where('currency_id', $id)
                        )
                            ->orderByDesc('rate_date')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn ($s) => [
                                $s->id => "{$s->rate_date?->format('d.m.Y')} | {$s->rate} ({$s->source})",
                            ]))
                        ->nullable()
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                            if ($state) {
                                $snap = CurrencyRateSnapshot::find($state);
                                if ($snap) {
                                    $set('actual_rate', $snap->rate);
                                }
                            }
                        }),

                    Forms\Components\TextInput::make('actual_rate')
                        ->label('Kurs zakupu')
                        ->numeric()
                        ->nullable()
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                            $amount = (float) ($get('actual_amount') ?: 0);
                            if ($amount > 0) {
                                $set('actual_amount_pln', round($amount * $state, 2));
                            }
                        }),

                    Forms\Components\TextInput::make('actual_amount_pln')
                        ->label('= PLN')
                        ->numeric()
                        ->nullable()
                        ->suffix('PLN'),

                    Forms\Components\Select::make('payment_method')
                        ->label('Forma płatności')
                        ->options(EventSettlementCost::$paymentMethods)
                        ->nullable(),

                    Forms\Components\TextInput::make('invoice_number')
                        ->label('Numer faktury')
                        ->nullable(),

                    Forms\Components\TextInput::make('receipt_number')
                        ->label('Numer paragonu')
                        ->nullable(),

                    Forms\Components\TextInput::make('document_number')
                        ->label('Nr dokumentu (legacy)')
                        ->nullable(),

                    Forms\Components\DateTimePicker::make('paid_at')
                        ->label('Data płatności')
                        ->nullable(),

                    Forms\Components\Select::make('paid_by_user_id')
                        ->label('Płatnik (użytkownik)')
                        ->options(fn () => \App\Models\User::orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->nullable(),
                ]),

            \FilamentTiptapEditor\TiptapEditor::make('notes')
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withCount('documents')
                ->withoutGlobalScopes([
                    SoftDeletingScope::class,
                ]))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Pozycja')
                    ->html()
                    ->searchable()
                    ->wrap()
                    ->state(function (EventSettlementCost $record): string {
                        $source = match ($record->source_type) {
                            'program_point' => 'Punkt programu',
                            'program_point_payment' => 'Wpłata punktu programu',
                            'insurance_day' => 'Ubezpieczenie',
                            'transport' => 'Transport',
                            'accommodation' => 'Nocleg',
                            'manual' => 'Ręczny wpis',
                            default => (string) ($record->source_type ?? '—'),
                        };

                        $paidBy = EventSettlementCost::$paidByOptions[$record->paid_by] ?? (string) $record->paid_by;
                        $contractor = $record->contractor?->name ?? '—';
                        $name = htmlspecialchars((string) ($record->name ?? '—'));
                        $source = htmlspecialchars($source);
                        $paidBy = htmlspecialchars($paidBy);
                        $contractor = htmlspecialchars($contractor);

                        return "<div class='admin-table-stack'>"
                            ."<div class='admin-table-title'>{$name}</div>"
                            ."<div class='admin-table-meta'>Źródło: {$source}</div>"
                            ."<div class='admin-table-meta'>Płaci: {$paidBy}</div>"
                            ."<div class='admin-table-meta'>Kontrahent: {$contractor}</div>"
                            .'</div>';
                    }),

                Tables\Columns\TextColumn::make('contractor.name')
                    ->label('Kontrahent')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('paid_by')
                    ->label('Płatność')
                    ->html()
                    ->state(function (EventSettlementCost $record): string {
                        $badge = static fn (string $t, string $bg, string $fg): string => "<span class='admin-table-pill' style='background:{$bg};color:{$fg}'>".htmlspecialchars($t).'</span>';

                        $typeLabel = EventSettlementCost::$advanceTypes[$record->advance_type] ?? $record->advance_type;
                        $typeBadge = $badge($typeLabel, '#f3f4f6', '#374151');

                        $statusLabel = EventSettlementCost::$paymentStatuses[$record->payment_status] ?? $record->payment_status;
                        [$sBg, $sFg] = match ($record->payment_status) {
                            'planned' => ['#f3f4f6', '#374151'],
                            'reservation_required' => ['#fde68a', '#92400e'],
                            'reserved' => ['#e0f2fe', '#0369a1'],
                            'advance_required' => ['#ffedd5', '#9a3412'],
                            'advance_paid' => ['#fde68a', '#92400e'],
                            'partially_paid' => ['#dbeafe', '#1e40af'],
                            'paid' => ['#dcfce7', '#166534'],
                            'cancelled' => ['#fee2e2', '#991b1b'],
                            default => ['#f3f4f6', '#374151'],
                        };
                        $statusBadge = $badge($statusLabel, $sBg, $sFg);

                        return "<div class='admin-table-stack admin-table-stack-compact'>{$typeBadge}{$statusBadge}</div>";
                    }),

                Tables\Columns\TextColumn::make('amounts_summary')
                    ->label('Kwoty (PLN)')
                    ->html()
                    ->state(function (EventSettlementCost $record): string {
                        $planned = (float) ($record->planned_amount_pln ?? 0);
                        $actual = $record->actual_amount_pln !== null
                            ? (float) $record->actual_amount_pln
                            : null;
                        $diff = $actual !== null ? $actual - $planned : null;

                        $plannedLabel = number_format($planned, 2, ',', ' ').' PLN';
                        $actualLabel = $actual !== null
                            ? number_format($actual, 2, ',', ' ').' PLN'
                            : '—';
                        $diffLabel = $diff !== null
                            ? number_format($diff, 2, ',', ' ').' PLN'
                            : '—';

                        $diffColor = $diff === null
                            ? '#6b7280'
                            : ($diff > 0 ? '#b91c1c' : ($diff < 0 ? '#166534' : '#374151'));

                        return "<div class='admin-table-stack admin-table-stack-compact'>"
                            ."<span class='admin-table-value'>Plan: {$plannedLabel}</span>"
                            ."<span class='admin-table-value'>Rzecz.: {$actualLabel}</span>"
                            ."<span class='admin-table-value-strong' style='color:{$diffColor}'>Różnica: {$diffLabel}</span>"
                            .'</div>';
                    })
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy('planned_amount_pln', $direction)),

                Tables\Columns\TextColumn::make('planned_amount')
                    ->label('Plan')
                    ->money(fn ($record) => $record->plannedCurrency?->symbol ?? 'PLN')
                    ->sortable(query: fn ($query, string $direction) => $query
                        ->orderBy('planned_amount_pln', $direction)
                        ->orderBy('planned_amount', $direction))
                    ->description(fn ($record) => $record->planned_amount_pln ? number_format($record->planned_amount_pln, 2).' PLN' : null)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('actual_amount')
                    ->label('Rzeczywiste')
                    ->money(fn ($record) => $record->actualCurrency?->symbol ?? $record->plannedCurrency?->symbol ?? 'PLN')
                    ->sortable(query: fn ($query, string $direction) => $query
                        ->orderByRaw("COALESCE(actual_amount_pln, 0) {$direction}")
                        ->orderByRaw("COALESCE(actual_amount, 0) {$direction}"))
                    ->placeholder('—')
                    ->description(fn ($record) => $record->actual_amount_pln ? number_format($record->actual_amount_pln, 2).' PLN' : null)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('diff_pln')
                    ->label('Różnica PLN')
                    ->state(fn ($record) => $record->diff_pln)
                    ->numeric(2)
                    ->suffix(' PLN')
                    ->placeholder('—')
                    ->sortable(query: fn ($query, string $direction) => $query->orderByRaw("(COALESCE(actual_amount_pln, 0) - COALESCE(planned_amount_pln, 0)) {$direction}"))
                    ->color(fn ($state) => $state === null ? 'gray' : ($state > 0 ? 'danger' : ($state < 0 ? 'success' : 'gray')))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('advance_due_date')
                    ->label('Termin / Kwota zaliczki')
                    ->date('d.m.Y')
                    ->placeholder('—')
                    ->description(fn ($record) => $record->advance_amount
                        ? number_format((float) $record->advance_amount, 2).' PLN'
                        : null)
                    ->extraAttributes(['style' => 'font-size: 116.6667%;']),

                Tables\Columns\TextColumn::make('linked_documents_label')
                    ->label('Dokumenty')
                    ->html()
                    ->state(function (EventSettlementCost $record): string {
                        $badge = static fn (string $t, string $bg, string $fg): string => "<span class='admin-table-pill' style='background:{$bg};color:{$fg}'>".htmlspecialchars($t).'</span>';

                        $faktury = (string) ($record->linked_documents_label ?? '—');
                        $dokCount = (int) ($record->documents_count ?? 0);
                        $dokBadge = $badge($dokCount.' dok.', $dokCount > 0 ? '#dcfce7' : '#f3f4f6', $dokCount > 0 ? '#166534' : '#374151');

                        $scanStatus = (string) ($record->document_scan_status ?? 'Brak dokumentu');
                        [$scanBg, $scanFg] = match ($scanStatus) {
                            'Skan OK' => ['#dcfce7', '#166534'],
                            'Dokument bez skanu' => ['#fef3c7', '#92400e'],
                            'Brak dokumentu' => ['#fee2e2', '#991b1b'],
                            default => ['#f3f4f6', '#374151'],
                        };
                        $scanBadge = $badge($scanStatus, $scanBg, $scanFg);

                        return "<div class='admin-table-stack admin-table-stack-compact'>"
                            ."<span class='admin-table-value'>".htmlspecialchars($faktury).'</span>'
                            .$dokBadge
                            .$scanBadge
                            .'</div>';
                    }),

                Tables\Columns\TextColumn::make('coverage_status')
                    ->label('Semafor')
                    ->html()
                    ->state(function (EventSettlementCost $record): string {
                        $health = app(SettlementPaymentHealthService::class);
                        $settlement = $record->settlement ?? $this->getOwnerRecord();
                        $allCosts = $settlement?->relationLoaded('costs')
                            ? $settlement->costs
                            : ($settlement?->costs()->get() ?? collect());

                        if (! $health->isEvaluablePlanCost($record)) {
                            return "<span class='admin-table-pill' style='background:#f3f4f6;color:#6b7280'>—</span>";
                        }

                        $evaluation = $health->evaluatePlanCost($record, $allCosts);

                        return $health->statusBadgeHtml((string) ($evaluation['coverage_status'] ?? SettlementPaymentHealthService::STATUS_SHORTFALL));
                    }),

                Tables\Columns\BadgeColumn::make('approval_status')
                    ->label('Kontrola')
                    ->formatStateUsing(fn (?string $state) => EventSettlementCost::$approvalStatuses[$state ?? 'pending'] ?? $state)
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ])
                    ->extraAttributes(['style' => 'font-size: 116.6667%;']),

                Tables\Columns\TextColumn::make('document_number')
                    ->label('Nr dokumentu')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('reviewed_at')
                    ->label('Zweryfikowano')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('order')
            ->reorderable('order')
            ->striped()
            ->filters([
                Tables\Filters\SelectFilter::make('payment_status')
                    ->label('Status płatności')
                    ->options(EventSettlementCost::$paymentStatuses)
                    ->multiple()
                    ->searchable(),

                Tables\Filters\SelectFilter::make('coverage_status')
                    ->label('Semafor')
                    ->options(SettlementPaymentHealthService::$statusLabels)
                    ->query(function (Builder $query, array $data): Builder {
                        $status = $data['value'] ?? null;

                        if (blank($status)) {
                            return $query;
                        }

                        $settlement = $this->getOwnerRecord();
                        $health = app(SettlementPaymentHealthService::class);
                        $allCosts = $settlement->costs()->get();
                        $ids = $health->listPlanCosts($allCosts)
                            ->filter(function (EventSettlementCost $cost) use ($health, $allCosts, $status): bool {
                                $evaluation = $health->evaluatePlanCost($cost, $allCosts);

                                return ($evaluation['coverage_status'] ?? '') === $status;
                            })
                            ->pluck('id')
                            ->all();

                        if ($ids === []) {
                            return $query->whereRaw('1 = 0');
                        }

                        return $query->whereIn('id', $ids);
                    }),

                Tables\Filters\SelectFilter::make('approval_status')
                    ->label('Kontrola')
                    ->options(EventSettlementCost::$approvalStatuses)
                    ->multiple(),

                Tables\Filters\SelectFilter::make('advance_type')
                    ->label('Typ płatności')
                    ->options(EventSettlementCost::$advanceTypes)
                    ->multiple(),

                Tables\Filters\SelectFilter::make('paid_by')
                    ->label('Płatnik')
                    ->options(EventSettlementCost::$paidByOptions)
                    ->placeholder('Wszyscy'),

                Tables\Filters\SelectFilter::make('contractor_id')
                    ->label('Kontrahent')
                    ->relationship('contractor', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('source_type')
                    ->label('Źródło')
                    ->options([
                        'manual' => 'Ręczny wpis',
                        'program_point' => 'Punkt programu',
                        'insurance_day' => 'Ubezpieczenie dzienne',
                        'transport' => 'Transport',
                        'accommodation' => 'Nocleg',
                    ]),

                Tables\Filters\Filter::make('planned_amount_range')
                    ->label('Planowana kwota')
                    ->form([
                        Forms\Components\TextInput::make('amount_from')
                            ->label('Od')
                            ->numeric()
                            ->suffix('PLN'),
                        Forms\Components\TextInput::make('amount_to')
                            ->label('Do')
                            ->numeric()
                            ->suffix('PLN'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['amount_from'] ?? null, fn ($q, $value) => $q->where('planned_amount_pln', '>=', (float) $value))
                            ->when($data['amount_to'] ?? null, fn ($q, $value) => $q->where('planned_amount_pln', '<=', (float) $value));
                    }),

                Tables\Filters\Filter::make('zero_amount')
                    ->label('Tylko 0 PLN')
                    ->toggle()
                    ->query(fn ($query) => $query->where(function ($subQuery) {
                        $subQuery
                            ->whereNull('planned_amount_pln')
                            ->orWhere('planned_amount_pln', '<=', 0);
                    })),

                Tables\Filters\Filter::make('exclude_zero_amount')
                    ->label('Ukryj 0 PLN')
                    ->toggle()
                    ->query(fn ($query) => $query->where(function ($subQuery) {
                        $subQuery
                            ->whereNotNull('planned_amount_pln')
                            ->where('planned_amount_pln', '>', 0);
                    })),

                Tables\Filters\Filter::make('has_documents')
                    ->label('Tylko z dokumentami')
                    ->query(fn ($query) => $query->whereHas('documents')),

                Tables\Filters\TrashedFilter::make()
                    ->label('Usunięte pozycje'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('open_event')
                    ->label('Przejdź do imprezy')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn () => EventResource::getUrl('edit', ['record' => $this->getOwnerRecord()->event_id]))
                    ->openUrlInNewTab(),

                Tables\Actions\CreateAction::make()
                    ->label('Dodaj pozycję')
                    ->after(fn () => $this->dispatchSettlementDataChanged()),
            ])
            ->actions([
                Tables\Actions\Action::make('add_reservation')
                    ->label('Rezerwacja')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->button()
                    ->size('sm')
                    ->modalHeading(fn (EventSettlementCost $record) => 'Nowa rezerwacja: '.$record->name)
                    ->modalWidth(ReservationFormFields::MODAL_WIDTH)
                    ->form(function (EventSettlementCost $record): array {
                        $fromProgramPoint = $record->source_type === 'program_point' && $record->source_id;

                        return ReservationFormFields::schema(new ReservationFormOptions(
                            eventId: $this->getOwnerRecord()->event_id,
                            event: $this->getOwnerRecord()->event,
                            settlementId: $this->getOwnerRecord()->id,
                            defaultContractorId: $record->contractor_id,
                            defaultProgramPointId: $fromProgramPoint ? $record->source_id : null,
                            defaultSettlementCostId: $record->id,
                            isHotelContext: $fromProgramPoint
                                && (bool) EventProgramPoint::query()->whereKey($record->source_id)->value('is_hotel'),
                            showHotelNotes: $fromProgramPoint
                                && (bool) EventProgramPoint::query()->whereKey($record->source_id)->value('is_hotel'),
                            simplified: (bool) $fromProgramPoint,
                            lockContractor: (bool) $fromProgramPoint,
                        ));
                    })
                    ->fillForm(fn (EventSettlementCost $record): array => ReservationFormFields::defaultModalData(new ReservationFormOptions(
                        eventId: $this->getOwnerRecord()->event_id,
                        event: $this->getOwnerRecord()->event,
                        defaultContractorId: $record->contractor_id,
                        defaultProgramPointId: $record->source_type === 'program_point' ? $record->source_id : null,
                        defaultSettlementCostId: $record->id,
                        defaultAmount: $record->planned_amount,
                        defaultCurrencyId: $record->planned_currency_id,
                    )))
                    ->action(function (array $data, EventSettlementCost $record): void {
                        $fromProgramPoint = $record->source_type === 'program_point' && $record->source_id;

                        $reservation = Reservation::create([
                            ...ReservationFormFields::normalizeSaveData($data),
                            'event_id' => $this->getOwnerRecord()->event_id,
                            'program_point_id' => $fromProgramPoint ? $record->source_id : null,
                            'settlement_cost_id' => $record->id,
                            'contractor_id' => $fromProgramPoint
                                ? EventProgramPoint::query()->whereKey($record->source_id)->value('contractor_id')
                                : ($data['contractor_id'] ?? $record->contractor_id),
                            'created_by' => auth()->id(),
                        ]);

                        ReservationFormFields::persistAttachments($reservation, $data);

                        $this->dispatchSettlementDataChanged();
                    }),

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('mark_reserved')
                        ->label('Zarezerwowana')
                        ->icon('heroicon-o-bookmark')
                        ->color('info')
                        ->requiresConfirmation()
                        ->action(function ($record) {
                            $record->update(['payment_status' => 'reserved']);
                            $this->dispatchSettlementDataChanged();
                        })
                        ->visible(fn ($record) => in_array($record->payment_status, ['reservation_required', 'planned'], true)),

                    Tables\Actions\Action::make('mark_advance_paid')
                        ->label('Zaliczka wpłacona')
                        ->icon('heroicon-o-currency-dollar')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function ($record) {
                            $record->update([
                                'payment_status' => 'advance_paid',
                                'paid_at' => $record->paid_at ?? now(),
                            ]);
                            $this->dispatchSettlementDataChanged();
                        })
                        ->visible(fn ($record) => in_array($record->payment_status, ['advance_required', 'planned', 'reservation_required', 'reserved'], true)),

                    Tables\Actions\Action::make('mark_paid')
                        ->label('Opłacona')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($record) {
                            $record->update([
                                'actual_amount' => $record->actual_amount ?? $record->planned_amount,
                                'actual_currency_id' => $record->actual_currency_id ?? $record->planned_currency_id,
                                'actual_rate' => $record->actual_rate ?? $record->planned_rate,
                                'actual_amount_pln' => $record->actual_amount_pln ?? $record->planned_amount_pln,
                                'payment_status' => 'paid',
                                'paid_at' => $record->paid_at ?? now(),
                            ]);
                            $this->dispatchSettlementDataChanged();
                        })
                        ->visible(fn ($record) => $record->payment_status !== 'paid'),

                    Tables\Actions\Action::make('mark_cancelled')
                        ->label('Anuluj')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($record) {
                            $record->update(['payment_status' => 'cancelled']);
                            $this->dispatchSettlementDataChanged();
                        })
                        ->visible(fn ($record) => $record->payment_status !== 'cancelled'),
                ])
                    ->label('Zmień status')
                    ->icon('heroicon-o-arrows-up-down')
                    ->color('primary')
                    ->button()
                    ->size('sm'),

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('approve_item')
                        ->label('Akceptuj')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (EventSettlementCost $record) {
                            $record->update([
                                'approval_status' => 'approved',
                                'reviewed_by' => Auth::id(),
                                'reviewed_at' => now(),
                            ]);
                        })
                        ->visible(fn (EventSettlementCost $record) => $record->approval_status !== 'approved'),

                    Tables\Actions\Action::make('reject_item')
                        ->label('Odrzuć')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->form([
                            \FilamentTiptapEditor\TiptapEditor::make('notes')
                                ->required(),
                        ])
                        ->action(function (EventSettlementCost $record, array $data) {
                            $record->update([
                                'approval_status' => 'rejected',
                                'review_notes' => $data['review_notes'] ?? null,
                                'reviewed_by' => Auth::id(),
                                'reviewed_at' => now(),
                            ]);
                        })
                        ->visible(fn (EventSettlementCost $record) => $record->approval_status !== 'rejected'),

                    Tables\Actions\Action::make('reset_approval')
                        ->label('Cofnij akceptację')
                        ->icon('heroicon-o-arrow-path')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(function (EventSettlementCost $record) {
                            $record->update([
                                'approval_status' => 'pending',
                                'review_notes' => null,
                                'reviewed_by' => null,
                                'reviewed_at' => null,
                            ]);
                        })
                        ->visible(fn (EventSettlementCost $record) => $record->approval_status !== 'pending'),

                    Tables\Actions\Action::make('create_task')
                        ->label('Dodaj zadanie')
                        ->icon('heroicon-o-clipboard-document-list')
                        ->color('primary')
                        ->url(fn (EventSettlementCost $record): string => TaskResource::getUrl('create', [
                            'taskable_type' => EventSettlementCost::class,
                            'taskable_id' => $record->getKey(),
                        ]))
                        ->openUrlInNewTab(),
                ])
                    ->label('Kontrola / Inne')
                    ->icon('heroicon-o-shield-check')
                    ->color('gray')
                    ->button()
                    ->size('sm'),

                Tables\Actions\EditAction::make()
                    ->action(function (EventSettlementCost $record, array $data): void {
                        // Handle document upload and updates only if documents were submitted
                        if (array_key_exists('documents', $data) && is_array($data['documents'])) {
                            $settlement = $this->getOwnerRecord();
                            $eventId = $settlement->event_id;
                            $existingIds = $record->documents()->pluck('id')->toArray();
                            $keptIds = [];

                            foreach ($data['documents'] as $item) {
                                $docId = $item['doc_id'] ?? null;
                                $targets = (array) ($item['pdf_targets'] ?? []);
                                $newFilePath = $item['file_path'] ?? null;
                                $existingPath = $item['existing_file_path'] ?? null;
                                $finalFilePath = $newFilePath ?: $existingPath;

                                $docData = [
                                    'event_id' => $eventId,
                                    'settlement_cost_id' => $record->id,
                                    'name' => $item['name'],
                                    'file_path' => $finalFilePath,
                                    'notes' => $item['notes'] ?? null,
                                    'is_invoice' => (bool) ($item['is_invoice'] ?? false),
                                    'attach_to_pilot_pdf' => in_array('attach_to_pilot_pdf', $targets, true),
                                    'attach_to_hotel_pdf' => in_array('attach_to_hotel_pdf', $targets, true),
                                    'attach_to_driver_pdf' => in_array('attach_to_driver_pdf', $targets, true),
                                    'attach_to_folder_pdf' => in_array('attach_to_folder_pdf', $targets, true),
                                ];

                                if ($docId && in_array((int) $docId, $existingIds, true)) {
                                    $existingDoc = EventDocument::find($docId);
                                    if ($existingDoc) {
                                        if ($newFilePath && $existingPath && $newFilePath !== $existingPath) {
                                            $normalizedPath = StoragePath::normalize($existingPath);
                                            if ($normalizedPath) {
                                                Storage::disk('public')->delete($normalizedPath);
                                            }
                                        }
                                        $existingDoc->update($docData);
                                        $keptIds[] = (int) $docId;
                                    }
                                } else {
                                    $doc = EventDocument::create($docData);
                                    $keptIds[] = $doc->id;
                                }
                            }

                            // Delete documents removed from repeater (only if documents were explicitly submitted)
                            $toDelete = array_diff($existingIds, $keptIds);
                            foreach ($toDelete as $delId) {
                                $doc = EventDocument::find($delId);
                                if ($doc) {
                                    $normalizedPath = StoragePath::normalize($doc->file_path);
                                    if ($normalizedPath) {
                                        Storage::disk('public')->delete($normalizedPath);
                                    }
                                    $doc->delete();
                                }
                            }

                            unset($data['documents']);
                        }

                        // Save the record
                        $record->update($data);
                    })
                    ->after(fn () => $this->dispatchSettlementDataChanged()),

                Tables\Actions\Action::make('manage_documents')
                    ->label('Dokumenty')
                    ->icon('heroicon-o-paper-clip')
                    ->color('gray')
                    ->button()
                    ->size('sm')
                    ->modalHeading(fn (EventSettlementCost $record): string => 'Dokumenty: '.$record->name)
                    ->modalDescription('Dodaj, edytuj lub usuń dokumenty tej pozycji.')
                    ->modalWidth('4xl')
                    ->form(function (EventSettlementCost $record): array {
                        $docs = $record->documents()->get();
                        $docState = $docs->map(fn ($doc) => [
                            'doc_id' => $doc->id,
                            'existing_file_path' => $doc->file_path,
                            'file_path' => null,
                            'name' => $doc->name,
                            'notes' => $doc->notes,
                            'is_invoice' => (bool) $doc->is_invoice,
                            'pdf_targets' => array_keys(array_filter([
                                'attach_to_pilot_pdf' => $doc->attach_to_pilot_pdf,
                                'attach_to_hotel_pdf' => $doc->attach_to_hotel_pdf,
                                'attach_to_driver_pdf' => $doc->attach_to_driver_pdf,
                                'attach_to_folder_pdf' => $doc->attach_to_folder_pdf,
                            ])),
                        ])->toArray();

                        return [
                            Forms\Components\Repeater::make('documents')
                                ->label('Dokumenty')
                                ->addActionLabel('Dodaj dokument')
                                ->default($docState)
                                ->collapsible()
                                ->schema([
                                    Forms\Components\Hidden::make('doc_id'),
                                    Forms\Components\Hidden::make('existing_file_path'),

                                    Forms\Components\TextInput::make('name')
                                        ->label('Nazwa')
                                        ->required()
                                        ->maxLength(255)
                                        ->columnSpan(2),

                                    Forms\Components\Placeholder::make('current_file_info')
                                        ->label('Plik')
                                        ->content(fn (Forms\Get $get): string => $get('existing_file_path')
                                            ? '📎 '.basename((string) $get('existing_file_path'))
                                            : '—')
                                        ->columnSpan(2),

                                    Forms\Components\FileUpload::make('file_path')
                                        ->label('Nowy plik')
                                        ->disk('public')
                                        ->directory('event-documents')
                                        ->preserveFilenames(false)
                                        ->acceptedFileTypes([
                                            'application/pdf',
                                            'image/*',
                                            'application/msword',
                                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                            'application/vnd.ms-excel',
                                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                            'text/plain',
                                        ])
                                        ->maxSize(20480)
                                        ->columnSpan(2),

                                    \FilamentTiptapEditor\TiptapEditor::make('notes')
                                        ->columnSpan(2),

                                    Forms\Components\Toggle::make('is_invoice')
                                        ->label('To jest faktura')
                                        ->inline(false)
                                        ->default(false)
                                        ->columnSpan(2),

                                    Forms\Components\CheckboxList::make('pdf_targets')
                                        ->label('Pakiety PDF')
                                        ->options([
                                            'attach_to_pilot_pdf' => '✈ Pilot',
                                            'attach_to_hotel_pdf' => '🏨 Hotel',
                                            'attach_to_driver_pdf' => '🚌 Kierowca',
                                            'attach_to_folder_pdf' => '📁 Teczka',
                                        ])
                                        ->columns(2)
                                        ->columnSpan(2),
                                ])
                                ->columns(2)
                                ->itemLabel(fn (array $state): ?string => $state['name'] ?? 'Nowy dokument')
                                ->columnSpanFull(),
                        ];
                    })
                    ->action(function (EventSettlementCost $record, array $data): void {
                        $settlement = $this->getOwnerRecord();
                        $eventId = $settlement->event_id;
                        $existingIds = $record->documents()->pluck('id')->toArray();
                        $keptIds = [];

                        foreach ($data['documents'] ?? [] as $item) {
                            $docId = $item['doc_id'] ?? null;
                            $targets = (array) ($item['pdf_targets'] ?? []);
                            $newFilePath = $item['file_path'] ?? null;
                            $existingPath = $item['existing_file_path'] ?? null;
                            $finalFilePath = $newFilePath ?: $existingPath;

                            if (! $finalFilePath || ! $item['name']) {
                                continue;
                            }

                            $docData = [
                                'event_id' => $eventId,
                                'settlement_cost_id' => $record->id,
                                'name' => $item['name'],
                                'file_path' => $finalFilePath,
                                'notes' => $item['notes'] ?? null,
                                'is_invoice' => (bool) ($item['is_invoice'] ?? false),
                                'attach_to_pilot_pdf' => in_array('attach_to_pilot_pdf', $targets, true),
                                'attach_to_hotel_pdf' => in_array('attach_to_hotel_pdf', $targets, true),
                                'attach_to_driver_pdf' => in_array('attach_to_driver_pdf', $targets, true),
                                'attach_to_folder_pdf' => in_array('attach_to_folder_pdf', $targets, true),
                            ];

                            if ($docId && in_array((int) $docId, $existingIds, true)) {
                                $existingDoc = EventDocument::find($docId);
                                if ($existingDoc) {
                                    if ($newFilePath && $existingPath && $newFilePath !== $existingPath) {
                                        $normalizedPath = StoragePath::normalize($existingPath);
                                        if ($normalizedPath) {
                                            Storage::disk('public')->delete($normalizedPath);
                                        }
                                    }
                                    $existingDoc->update($docData);
                                    $keptIds[] = (int) $docId;
                                }
                            } else {
                                $doc = EventDocument::create($docData);
                                $keptIds[] = $doc->id;
                            }
                        }

                        // Usuń dokumenty usunięte z repeatera
                        $toDelete = array_diff($existingIds, $keptIds);
                        foreach ($toDelete as $delId) {
                            $doc = EventDocument::find($delId);
                            if ($doc) {
                                $normalizedPath = StoragePath::normalize($doc->file_path);
                                if ($normalizedPath) {
                                    Storage::disk('public')->delete($normalizedPath);
                                }
                                $doc->delete();
                            }
                        }
                    })
                    ->after(fn () => $this->dispatchSettlementDataChanged()),

                Tables\Actions\DeleteAction::make()
                    ->after(fn () => $this->dispatchSettlementDataChanged()),
                Tables\Actions\RestoreAction::make()
                    ->after(fn () => $this->dispatchSettlementDataChanged()),
                Tables\Actions\ForceDeleteAction::make()
                    ->after(fn () => $this->dispatchSettlementDataChanged()),
            ]);
    }
}
