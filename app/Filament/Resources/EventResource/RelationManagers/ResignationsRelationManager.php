<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Models\Contract;
use App\Models\EventAgreement;
use App\Models\EventParticipantResignation;
use App\Models\EventProgramPoint;
use App\Services\ParticipantResignationSettlementSync;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class ResignationsRelationManager extends RelationManager
{
    protected static string $relationship = 'participantResignations';

    protected static ?string $title = 'Rezygnacje uczestników';

    protected static ?string $recordTitleAttribute = 'participant_name';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Uczestnik')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('contract_id')
                        ->label('Umowa / kontrakt')
                        ->options(fn () => $this->contractOptions())
                        ->searchable()
                        ->nullable()
                        ->live()
                        ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                            if (blank($state)) {
                                return;
                            }

                            $contract = Contract::query()->find($state);
                            if (! $contract) {
                                return;
                            }

                            $set('participant_name', $contract->participant_name ?: $contract->signer_name ?: $contract->customer_name);
                            $set('amount_due_pln', (float) $contract->total_price);
                            $set('amount_paid_pln', (float) $contract->amount_paid);
                            $set('price_per_person_pln', $contract->event?->resolvedPricePerPerson((int) ($contract->participant_count ?: 1)));
                            $set('participant_payment_id', $contract->participant_payment_id);
                        }),

                    Forms\Components\Select::make('event_agreement_id')
                        ->label('Umowa (legacy)')
                        ->options(fn () => $this->agreementOptions())
                        ->searchable()
                        ->nullable()
                        ->visible(fn () => Schema::hasTable('event_agreements') && ! Schema::hasTable('contracts'))
                        ->live()
                        ->afterStateUpdated(function (?string $state, Set $set): void {
                            if (blank($state)) {
                                return;
                            }

                            $agreement = EventAgreement::query()->find($state);
                            if (! $agreement) {
                                return;
                            }

                            $set('participant_name', $agreement->participant_name ?: $agreement->customer_name);
                            $set('amount_due_pln', (float) $agreement->amount_due);
                            $set('amount_paid_pln', (float) $agreement->amount_paid);
                            $set('participant_payment_id', $agreement->participant_payment_id);
                        }),

                    Forms\Components\TextInput::make('participant_name')
                        ->label('Imię i nazwisko uczestnika')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Forms\Components\Select::make('resignation_type')
                        ->label('Typ rezygnacji')
                        ->options(EventParticipantResignation::$types)
                        ->default('contractual')
                        ->required()
                        ->live(),

                    Forms\Components\DatePicker::make('resigned_at')
                        ->label('Data rezygnacji')
                        ->default(now())
                        ->required(),

                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options(EventParticipantResignation::$statuses)
                        ->default('draft')
                        ->disabled(fn (?EventParticipantResignation $record) => $record?->status === 'settled'),
                ]),

            Forms\Components\Section::make('Kwoty')
                ->columns(3)
                ->schema([
                    Forms\Components\Placeholder::make('paying_participants_hint')
                        ->label('Liczba płacących w imprezie')
                        ->content(function (): string {
                            $event = $this->getOwnerRecord();
                            $total = max(1, (int) ($event->participant_count ?? 1));
                            $gratis = 0;

                            try {
                                $variant = $event->qtyVariants()
                                    ->where('qty', $total)
                                    ->first();

                                if ($variant) {
                                    $gratis = (int) ($variant->gratis ?? 0);
                                }
                            } catch (\Throwable) {
                                $gratis = 0;
                            }

                            $paying = max(1, $total - $gratis);

                            return "Uczestnicy: {$total}, gratis: {$gratis}, płacących (szacunek): {$paying}. Cena za osobę dotyczy zwykle płacących uczestników.";
                        })
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('price_per_person_pln')
                        ->label('Cena za osobę (PLN)')
                        ->numeric()
                        ->suffix('PLN'),

                    Forms\Components\TextInput::make('amount_due_pln')
                        ->label('Należność przed rezygnacją')
                        ->numeric()
                        ->default(0)
                        ->suffix('PLN'),

                    Forms\Components\TextInput::make('amount_paid_pln')
                        ->label('Wpłacono')
                        ->numeric()
                        ->default(0)
                        ->suffix('PLN'),

                    Forms\Components\TextInput::make('refund_amount_pln')
                        ->label('Kwota do zwrotu uczestnikowi')
                        ->numeric()
                        ->default(0)
                        ->suffix('PLN')
                        ->visible(fn (Get $get) => $get('resignation_type') !== 'insurance'),

                    Forms\Components\TextInput::make('retention_amount_pln')
                        ->label('Potrącenie / opłata manipulacyjna')
                        ->numeric()
                        ->default(0)
                        ->suffix('PLN')
                        ->visible(fn (Get $get) => $get('resignation_type') !== 'insurance'),
                ]),

            Forms\Components\Section::make('Ubezpieczenie kosztów rezygnacji')
                ->visible(fn (Get $get) => $get('resignation_type') === 'insurance')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('insurance_policy_number')
                        ->label('Nr polisy / zgłoszenia')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('insurance_refund_pln')
                        ->label('Zwrot od ubezpieczyciela (PLN)')
                        ->numeric()
                        ->suffix('PLN'),
                ]),

            Forms\Components\Section::make('Rozbicie na świadczenia')
                ->description('Określ, za które elementy imprezy zwracamy wpłatę, a które zatrzymujemy (np. bilet, nocleg, opłata manipulacyjna).')
                ->visible(fn (Get $get) => $get('resignation_type') === 'contractual')
                ->schema([
                    Forms\Components\Repeater::make('lines')
                        ->relationship()
                        ->label('')
                        ->schema([
                            Forms\Components\Select::make('program_point_id')
                                ->label('Punkt programu')
                                ->options(fn () => $this->programPointOptions())
                                ->searchable()
                                ->nullable()
                                ->live()
                                ->afterStateUpdated(function (?string $state, Set $set): void {
                                    if (blank($state)) {
                                        return;
                                    }

                                    $point = EventProgramPoint::query()->with('templatePoint')->find($state);
                                    if ($point) {
                                        $set('description', (string) ($point->name ?: $point->templatePoint?->name ?: 'Świadczenie'));
                                    }
                                }),

                            Forms\Components\TextInput::make('description')
                                ->label('Świadczenie')
                                ->required()
                                ->maxLength(255),

                            Forms\Components\TextInput::make('refunded_amount_pln')
                                ->label('Zwrot (PLN)')
                                ->numeric()
                                ->default(0),

                            Forms\Components\TextInput::make('retained_amount_pln')
                                ->label('Potrącenie (PLN)')
                                ->numeric()
                                ->default(0),

                            Forms\Components\Textarea::make('notes')
                                ->label('Uwagi')
                                ->rows(2)
                                ->columnSpanFull(),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->addActionLabel('Dodaj świadczenie')
                        ->collapsible(),
                ]),

            Forms\Components\Textarea::make('reason')
                ->label('Powód rezygnacji')
                ->rows(2)
                ->columnSpanFull(),

            Forms\Components\Textarea::make('notes')
                ->label('Uwagi wewnętrzne')
                ->rows(2)
                ->columnSpanFull(),

            Forms\Components\Hidden::make('participant_payment_id'),
            Forms\Components\Hidden::make('created_by')
                ->default(fn () => Auth::id()),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('participant_name')
                    ->label('Uczestnik')
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('resignation_type')
                    ->label('Typ')
                    ->formatStateUsing(fn (?string $state) => EventParticipantResignation::$types[$state] ?? $state)
                    ->colors([
                        'info' => 'insurance',
                        'warning' => 'contractual',
                        'gray' => 'other',
                    ]),

                Tables\Columns\TextColumn::make('resigned_at')
                    ->label('Data')
                    ->date('d.m.Y'),

                Tables\Columns\TextColumn::make('refund_amount_pln')
                    ->label('Do zwrotu')
                    ->money('PLN'),

                Tables\Columns\TextColumn::make('retention_amount_pln')
                    ->label('Potrącenie')
                    ->money('PLN'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (?string $state) => EventParticipantResignation::$statuses[$state] ?? $state)
                    ->colors([
                        'gray' => 'draft',
                        'warning' => 'confirmed',
                        'success' => 'settled',
                        'danger' => 'cancelled',
                    ]),

                Tables\Columns\TextColumn::make('synced_at')
                    ->label('Sync. rozliczenia')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['created_by'] = Auth::id();

                        return $data;
                    })
                    ->after(function (EventParticipantResignation $record): void {
                        $record->recalculateTotalsFromLines();
                        $record->saveQuietly();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(function (EventParticipantResignation $record): void {
                        $record->recalculateTotalsFromLines();
                        $record->saveQuietly();
                    }),
                Tables\Actions\Action::make('sync_settlement')
                    ->label('Synchronizuj z rozliczeniem')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalDescription('Zaktualizuje wpłatę uczestnika w rozliczeniu: ustawi brak udziału w imprezie, należność po potrąceniach i notatkę ze szczegółami zwrotu.')
                    ->visible(fn (EventParticipantResignation $record) => $record->status !== 'cancelled')
                    ->action(function (EventParticipantResignation $record): void {
                        try {
                            app(ParticipantResignationSettlementSync::class)->sync($record);
                            Notification::make()
                                ->title('Rezygnacja zsynchronizowana z rozliczeniem')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            report($e);
                            Notification::make()
                                ->title('Błąd synchronizacji')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (EventParticipantResignation $record) => $record->status !== 'settled'),
            ])
            ->defaultSort('resigned_at', 'desc');
    }

    /** @return array<int|string, string> */
    private function contractOptions(): array
    {
        if (! Schema::hasTable('contracts')) {
            return [];
        }

        return Contract::query()
            ->where('event_id', $this->getOwnerRecord()->getKey())
            ->whereNotIn('status', ['template', 'cancelled'])
            ->orderByDesc('id')
            ->get()
            ->mapWithKeys(fn (Contract $contract) => [
                $contract->id => trim(sprintf(
                    '#%d %s — %s PLN',
                    $contract->id,
                    $contract->participant_name ?: $contract->signer_name ?: $contract->customer_name ?: 'Uczestnik',
                    number_format((float) $contract->total_price, 2, ',', ' '),
                )),
            ])
            ->all();
    }

    /** @return array<int|string, string> */
    private function agreementOptions(): array
    {
        return EventAgreement::query()
            ->where('event_id', $this->getOwnerRecord()->getKey())
            ->whereNotIn('status', ['template', 'cancelled'])
            ->orderByDesc('id')
            ->get()
            ->mapWithKeys(fn (EventAgreement $agreement) => [
                $agreement->id => trim(sprintf(
                    '#%d %s — %s PLN',
                    $agreement->id,
                    $agreement->participant_name ?: $agreement->customer_name ?: 'Uczestnik',
                    number_format((float) $agreement->amount_due, 2, ',', ' '),
                )),
            ])
            ->all();
    }

    /** @return array<int|string, string> */
    private function programPointOptions(): array
    {
        return EventProgramPoint::query()
            ->where('event_id', $this->getOwnerRecord()->getKey())
            ->with('templatePoint')
            ->orderBy('day')
            ->orderBy('order')
            ->get()
            ->mapWithKeys(fn (EventProgramPoint $point) => [
                $point->id => sprintf(
                    'D%d — %s',
                    (int) $point->day,
                    (string) ($point->name ?: $point->templatePoint?->name ?: 'Punkt #'.$point->id),
                ),
            ])
            ->all();
    }
}
