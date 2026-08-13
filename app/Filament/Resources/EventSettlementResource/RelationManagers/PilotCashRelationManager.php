<?php

namespace App\Filament\Resources\EventSettlementResource\RelationManagers;

use App\Filament\Resources\EventSettlementResource\Traits\DispatchesSettlementDataChanged;
use App\Models\Currency;
use App\Models\CurrencyRateSnapshot;
use App\Models\PilotCashPreparation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class PilotCashRelationManager extends RelationManager
{
    use DispatchesSettlementDataChanged;

    protected static string $relationship = 'pilotCashPreparations';

    protected static ?string $title = 'Gotówka pilota';

    protected static ?string $recordTitleAttribute = 'currency_id';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Waluta i kwoty')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\Select::make('currency_id')
                        ->label('Waluta')
                        ->options(fn () => Currency::orderBy('name')->pluck('name', 'id'))
                        ->required()
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                            if ($state) {
                                $snap = CurrencyRateSnapshot::latestFor($state);
                                if ($snap) {
                                    $set('rate_snapshot_id', $snap->id);
                                    $set('rate_used', $snap->rate);
                                } else {
                                    $c = Currency::find($state);
                                    $set('rate_used', $c?->exchange_rate ?? 1);
                                }
                            }
                        }),

                    Forms\Components\TextInput::make('calculated_amount')
                        ->label('Kwota obliczona przez system')
                        ->numeric()
                        ->default(0)
                        ->readOnly(),

                    Forms\Components\TextInput::make('approved_amount')
                        ->label('Kwota zatwierdzona do wypłaty')
                        ->numeric()
                        ->nullable()
                        ->helperText('Biuro akceptuje i wpisuje ile daje pilotowi'),

                    Forms\Components\TextInput::make('provided_amount')
                        ->label('Kwota wydana pilotowi')
                        ->numeric()
                        ->nullable(),

                    Forms\Components\TextInput::make('spent_amount')
                        ->label('Kwota wydana przez pilota')
                        ->numeric()
                        ->nullable()
                        ->helperText('Pilot raportuje ile faktycznie wydał'),

                    Forms\Components\TextInput::make('returned_amount')
                        ->label('Kwota zwrócona')
                        ->numeric()
                        ->nullable(),

                    Forms\Components\TextInput::make('balance')
                        ->label('Saldo (auto)')
                        ->numeric()
                        ->readOnly()
                        ->suffix('(waluta)')
                        ->helperText('= wypłacono - wydano + zwrócono'),
                ]),

            Forms\Components\Section::make('Kurs walutowy')
                ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                ->schema([
                    Forms\Components\Select::make('rate_snapshot_id')
                        ->label('Kurs z dnia')
                        ->options(fn (Forms\Get $get) => CurrencyRateSnapshot::when(
                            $get('currency_id'),
                            fn ($q, $id) => $q->where('currency_id', $id)
                        )
                            ->orderByDesc('rate_date')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn ($s) => [
                                $s->id => "{$s->rate_date?->format('d.m.Y')} | zakup: {$s->purchase_rate} sprzedaż: {$s->sale_rate} ({$s->source})",
                            ]))
                        ->nullable()
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                            if ($state) {
                                $snap = CurrencyRateSnapshot::find($state);
                                if ($snap) {
                                    $set('rate_used', $snap->purchase_rate ?? $snap->rate);
                                    $amount = (float) ($get('calculated_amount') ?? 0);
                                    $rate = $snap->purchase_rate ?? $snap->rate;
                                    $set('pln_equivalent', round($amount * $rate, 2));
                                }
                            }
                        }),

                    Forms\Components\TextInput::make('rate_used')
                        ->label('Kurs zakupu użyty do przeliczenia')
                        ->numeric()
                        ->nullable(),

                    Forms\Components\TextInput::make('pln_equivalent')
                        ->label('Równowartość PLN')
                        ->numeric()
                        ->nullable()
                        ->suffix('PLN'),
                ]),

            Forms\Components\Section::make('Status')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options(PilotCashPreparation::$statuses)
                        ->default('calculated')
                        ->required(),

                    Forms\Components\DateTimePicker::make('provided_at')
                        ->label('Data wydania pilotowi')
                        ->nullable(),

                    Forms\Components\DateTimePicker::make('settled_at')
                        ->label('Data rozliczenia')
                        ->nullable(),

                    \FilamentTiptapEditor\TiptapEditor::make('notes'),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('currency.name')
                    ->label('Waluta')
                    ->description(fn ($record) => $record->currency?->symbol),

                Tables\Columns\TextColumn::make('calculated_amount')
                    ->label('Obliczona')
                    ->numeric(2)
                    ->description('wg systemu'),

                Tables\Columns\TextColumn::make('approved_amount')
                    ->label('Zatwierdzona')
                    ->numeric(2)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('provided_amount')
                    ->label('Wydano pilotowi')
                    ->numeric(2)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('spent_amount')
                    ->label('Wydane przez pilota')
                    ->numeric(2)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('returned_amount')
                    ->label('Już zwrócono')
                    ->numeric(2)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('to_return_amount')
                    ->label('Do zwrotu')
                    ->state(fn ($record) => $record->to_return_amount)
                    ->numeric(2)
                    ->color('warning'),

                Tables\Columns\TextColumn::make('to_pay_pilot_amount')
                    ->label('Do dopłaty pilotowi')
                    ->state(fn ($record) => $record->to_pay_pilot_amount)
                    ->numeric(2)
                    ->color('danger'),

                Tables\Columns\TextColumn::make('balance')
                    ->label('Saldo końcowe')
                    ->numeric(2)
                    ->color(fn ($state) => $state > 0 ? 'warning' : ($state < 0 ? 'danger' : 'success')),

                Tables\Columns\TextColumn::make('rate_used')
                    ->label('Kurs')
                    ->numeric(4)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('pln_equivalent')
                    ->label('= PLN')
                    ->money('PLN')
                    ->placeholder('—'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => PilotCashPreparation::$statuses[$state] ?? $state)
                    ->colors([
                        'gray' => 'calculated',
                        'info' => 'approved',
                        'warning' => 'provided',
                        'success' => 'settled',
                    ]),

                Tables\Columns\BadgeColumn::make('approval_status')
                    ->label('Kontrola')
                    ->formatStateUsing(fn (?string $state) => PilotCashPreparation::$approvalStatuses[$state ?? 'pending'] ?? $state)
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ]),

                Tables\Columns\TextColumn::make('reviewed_at')
                    ->label('Zweryfikowano')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('approval_pending')
                    ->label('Niezaakceptowane')
                    ->query(fn ($query) => $query->where('approval_status', 'pending')),

                Tables\Filters\Filter::make('approval_approved')
                    ->label('Zaakceptowane')
                    ->query(fn ($query) => $query->where('approval_status', 'approved')),

                Tables\Filters\Filter::make('approval_rejected')
                    ->label('Odrzucone')
                    ->query(fn ($query) => $query->where('approval_status', 'rejected')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Dodaj walutę')
                    ->after(fn () => $this->dispatchSettlementDataChanged()),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Zatwierdź')
                    ->icon('heroicon-o-check')
                    ->color('info')
                    ->form([
                        Forms\Components\TextInput::make('approved_amount')
                            ->label('Kwota do zatwierdzenia')
                            ->numeric()
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'approved_amount' => $data['approved_amount'],
                            'status' => 'approved',
                        ]);
                        $this->dispatchSettlementDataChanged();
                    })
                    ->visible(fn ($record) => $record->status === 'calculated'),

                Tables\Actions\Action::make('mark_provided')
                    ->label('Wydano pilotowi')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('provided_amount')
                            ->label('Kwota wydana pilotowi')
                            ->numeric()
                            ->required(),
                        Forms\Components\DateTimePicker::make('provided_at')
                            ->label('Data wydania')
                            ->default(now()),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'provided_amount' => $data['provided_amount'],
                            'provided_at' => $data['provided_at'],
                            'status' => 'provided',
                        ]);
                        $this->dispatchSettlementDataChanged();
                    })
                    ->visible(fn ($record) => in_array($record->status, ['calculated', 'approved'])),

                Tables\Actions\Action::make('settle')
                    ->label('Rozlicz')
                    ->icon('heroicon-o-document-check')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('spent_amount')
                            ->label('Wydano przez pilota')
                            ->numeric()
                            ->required(),
                        Forms\Components\TextInput::make('returned_amount')
                            ->label('Zwrócono przez pilota')
                            ->numeric()
                            ->default(0)
                            ->helperText('Kwota, którą pilot oddał po wyjeździe.'),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'spent_amount' => $data['spent_amount'],
                            'returned_amount' => $data['returned_amount'],
                            'status' => 'settled',
                            'settled_at' => now(),
                        ]);
                        $this->dispatchSettlementDataChanged();
                    })
                    ->visible(fn ($record) => $record->status === 'provided'),

                Tables\Actions\Action::make('create_task')
                    ->label('Dodaj zadanie')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('primary')
                    ->url(fn (PilotCashPreparation $record): string => \App\Support\Tasks\TaskNavigation::createUrl(
                        \App\Models\PilotCashPreparation::class,
                        $record->getKey(),
                    ))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('approve_item')
                    ->label('Akceptuj pozycję')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (PilotCashPreparation $record) {
                        $record->update([
                            'approval_status' => 'approved',
                            'reviewed_by' => Auth::id(),
                            'reviewed_at' => now(),
                        ]);
                    })
                    ->visible(fn (PilotCashPreparation $record) => $record->approval_status !== 'approved'),

                Tables\Actions\Action::make('reject_item')
                    ->label('Odrzuć pozycję')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        \FilamentTiptapEditor\TiptapEditor::make('notes')
                            ->required(),
                    ])
                    ->action(function (PilotCashPreparation $record, array $data) {
                        $record->update([
                            'approval_status' => 'rejected',
                            'review_notes' => $data['review_notes'] ?? null,
                            'reviewed_by' => Auth::id(),
                            'reviewed_at' => now(),
                        ]);
                    })
                    ->visible(fn (PilotCashPreparation $record) => $record->approval_status !== 'rejected'),

                Tables\Actions\Action::make('reset_approval')
                    ->label('Cofnij akceptację')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(function (PilotCashPreparation $record) {
                        $record->update([
                            'approval_status' => 'pending',
                            'review_notes' => null,
                            'reviewed_by' => null,
                            'reviewed_at' => null,
                        ]);
                    })
                    ->visible(fn (PilotCashPreparation $record) => $record->approval_status !== 'pending'),

                Tables\Actions\EditAction::make()
                    ->after(fn () => $this->dispatchSettlementDataChanged()),
                Tables\Actions\DeleteAction::make()
                    ->after(fn () => $this->dispatchSettlementDataChanged()),
            ]);
    }
}
