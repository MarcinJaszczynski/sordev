<?php

namespace App\Filament\Resources\EventSettlementResource\RelationManagers;

use App\Filament\Resources\EventSettlementResource\Traits\DispatchesSettlementDataChanged;
use App\Filament\Resources\TaskResource;
use App\Models\EventSettlementParticipantPayment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ParticipantPaymentsRelationManager extends RelationManager
{
    use DispatchesSettlementDataChanged;

    protected static string $relationship = 'participantPayments';

    protected static ?string $title = 'Wpłaty uczestników';

    protected static ?string $recordTitleAttribute = 'participant_name';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Uczestnik')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('participant_name')
                        ->label('Imię i nazwisko uczestnika')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('booking_reference')
                        ->label('Nr rezerwacji')
                        ->nullable(),

                    Forms\Components\Toggle::make('attended')
                        ->label('Pojechał na imprezę')
                        ->default(true),
                ]),

            Forms\Components\Section::make('Płatność')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('due_amount_pln')
                        ->label('Należna kwota (PLN)')
                        ->numeric()
                        ->required()
                        ->default(0)
                        ->suffix('PLN'),

                    Forms\Components\TextInput::make('paid_amount_pln')
                        ->label('Wpłacona kwota (PLN)')
                        ->numeric()
                        ->default(0)
                        ->suffix('PLN'),

                    Forms\Components\TextInput::make('discount_amount_pln')
                        ->label('Zniżka / korekta (PLN)')
                        ->numeric()
                        ->default(0)
                        ->suffix('PLN'),

                    Forms\Components\Select::make('payment_status')
                        ->label('Status')
                        ->options(EventSettlementParticipantPayment::$paymentStatuses)
                        ->default('pending'),

                    Forms\Components\DateTimePicker::make('payment_date')
                        ->label('Data wpłaty')
                        ->nullable(),

                    Forms\Components\Select::make('payment_method')
                        ->label('Forma płatności')
                        ->options(EventSettlementParticipantPayment::$paymentMethods)
                        ->nullable(),

                    Forms\Components\TextInput::make('document_number')
                        ->label('Nr dokumentu')
                        ->nullable(),
                ]),

            Forms\Components\RichEditor::make('notes')
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('participant_name')
                    ->label('Uczestnik')
                    ->searchable()
                    ->description(fn ($record) => $record->booking_reference),

                Tables\Columns\IconColumn::make('attended')
                    ->label('Pojechał')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('due_amount_pln')
                    ->label('Należne')
                    ->money('PLN'),

                Tables\Columns\TextColumn::make('paid_amount_pln')
                    ->label('Wpłacono')
                    ->money('PLN'),

                Tables\Columns\TextColumn::make('balance')
                    ->label('Saldo')
                    ->state(fn ($record) => $record->paid_amount_pln - $record->due_amount_pln)
                    ->money('PLN')
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger'),

                Tables\Columns\BadgeColumn::make('payment_status')
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => EventSettlementParticipantPayment::$paymentStatuses[$state] ?? $state)
                    ->colors([
                        'gray' => 'pending',
                        'warning' => 'partial',
                        'success' => 'paid',
                        'info' => 'overpaid',
                        'danger' => 'cancelled',
                    ]),

                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Data wpłaty')
                    ->date('d.m.Y')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('document_number')
                    ->label('Dokument')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\BadgeColumn::make('approval_status')
                    ->label('Kontrola')
                    ->formatStateUsing(fn (?string $state) => EventSettlementParticipantPayment::$approvalStatuses[$state ?? 'pending'] ?? $state)
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
                    ->label('Dodaj uczestnika')
                    ->after(fn () => $this->dispatchSettlementDataChanged()),
            ])
            ->actions([
                Tables\Actions\Action::make('mark_paid')
                    ->label('Pełna wpłata')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update([
                            'paid_amount_pln' => $record->due_amount_pln,
                            'payment_status' => 'paid',
                            'payment_date' => $record->payment_date ?? now(),
                        ]);
                        $this->dispatchSettlementDataChanged();
                    })
                    ->visible(fn ($record) => $record->payment_status !== 'paid'),

                Tables\Actions\Action::make('create_task')
                    ->label('Dodaj zadanie')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('primary')
                    ->url(fn (EventSettlementParticipantPayment $record): string => TaskResource::getUrl('create', [
                        'taskable_type' => EventSettlementParticipantPayment::class,
                        'taskable_id' => $record->getKey(),
                    ]))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('approve_item')
                    ->label('Akceptuj wpłatę')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (EventSettlementParticipantPayment $record) {
                        $record->update([
                            'approval_status' => 'approved',
                            'reviewed_by' => Auth::id(),
                            'reviewed_at' => now(),
                        ]);
                    })
                    ->visible(fn (EventSettlementParticipantPayment $record) => $record->approval_status !== 'approved'),

                Tables\Actions\Action::make('reject_item')
                    ->label('Odrzuć wpłatę')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Forms\Components\RichEditor::make('notes')
                            ->required(),
                    ])
                    ->action(function (EventSettlementParticipantPayment $record, array $data) {
                        $record->update([
                            'approval_status' => 'rejected',
                            'review_notes' => $data['review_notes'] ?? null,
                            'reviewed_by' => Auth::id(),
                            'reviewed_at' => now(),
                        ]);
                    })
                    ->visible(fn (EventSettlementParticipantPayment $record) => $record->approval_status !== 'rejected'),

                Tables\Actions\Action::make('reset_approval')
                    ->label('Cofnij akceptację')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(function (EventSettlementParticipantPayment $record) {
                        $record->update([
                            'approval_status' => 'pending',
                            'review_notes' => null,
                            'reviewed_by' => null,
                            'reviewed_at' => null,
                        ]);
                    })
                    ->visible(fn (EventSettlementParticipantPayment $record) => $record->approval_status !== 'pending'),

                Tables\Actions\EditAction::make()
                    ->after(fn () => $this->dispatchSettlementDataChanged()),
                Tables\Actions\DeleteAction::make()
                    ->after(fn () => $this->dispatchSettlementDataChanged()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
