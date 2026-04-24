<?php

namespace App\Filament\Resources\EventSettlementResource\RelationManagers;

use App\Models\Reservation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ReservationsRelationManager extends RelationManager
{
    protected static string $relationship = 'reservations';

    protected static ?string $title = 'Rezerwacje';

    protected static ?string $recordTitleAttribute = 'booking_reference';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Rezerwacja')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('booking_reference')
                            ->label('Numer rezerwacji')
                            ->unique(ignoreRecord: true)
                            ->nullable(),

                        Forms\Components\Select::make('contractor_id')
                            ->label('Kontrahent')
                            ->relationship('contractor', 'name')
                            ->searchable()
                            ->nullable(),

                        Forms\Components\TextInput::make('participant_count')
                            ->label('Liczba uczestników')
                            ->numeric()
                            ->default(1)
                            ->required(),

                        Forms\Components\TextInput::make('reserved_amount')
                            ->label('Zarezerwowana kwota (PLN)')
                            ->numeric()
                            ->nullable()
                            ->suffix('PLN'),

                        Forms\Components\Select::make('program_point_id')
                            ->label('Punkt programu')
                            ->options(function () {
                                $eventId = $this->getOwnerRecord()->event_id;

                                return \App\Models\EventProgramPoint::query()
                                    ->where('event_id', $eventId)
                                    ->with('templatePoint')
                                    ->orderBy('day')
                                    ->orderBy('order')
                                    ->get()
                                    ->mapWithKeys(fn ($point) => [
                                        $point->id => sprintf(
                                            'Dzień %d • %s',
                                            (int) ($point->day ?? 1),
                                            $point->templatePoint?->name ?? $point->name ?? ('Punkt #'.$point->id)
                                        ),
                                    ]);
                            })
                            ->searchable()
                            ->nullable()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if (! $state) {
                                    return;
                                }

                                $costId = \App\Models\EventSettlementCost::query()
                                    ->where('settlement_id', $this->getOwnerRecord()->id)
                                    ->where('source_type', 'program_point')
                                    ->where('source_id', $state)
                                    ->value('id');

                                if ($costId) {
                                    $set('settlement_cost_id', $costId);
                                }
                            }),

                        Forms\Components\Select::make('settlement_cost_id')
                            ->label('Koszt rozliczenia')
                            ->options(function () {
                                return \App\Models\EventSettlementCost::query()
                                    ->where('settlement_id', $this->getOwnerRecord()->id)
                                    ->orderBy('order')
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->nullable(),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(Reservation::$statuses)
                            ->required()
                            ->default('pending'),

                        Forms\Components\DateTimePicker::make('reserved_at')
                            ->label('Data rezerwacji')
                            ->required(),

                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Wygasa')
                            ->nullable()
                            ->helperText('Pozostaw puste, aby rezerwacja była ważna bezterminowo'),

                        Forms\Components\RichEditor::make('notes')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->width(60),

                Tables\Columns\TextColumn::make('booking_reference')
                    ->label('Numer rezerwacji')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('contractor.name')
                    ->label('Kontrahent')
                    ->sortable()
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('programPoint.name')
                    ->label('Punkt programu')
                    ->state(fn (Reservation $record) => $record->programPoint?->templatePoint?->name ?? $record->programPoint?->name)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('participant_count')
                    ->label('Uczestnicy')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('reserved_amount')
                    ->label('Kwota')
                    ->money('PLN')
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => Reservation::$statuses[$state] ?? $state)
                    ->colors([
                        'gray' => 'pending',
                        'warning' => 'partially_confirmed',
                        'success' => ['confirmed', 'completed'],
                        'danger' => 'cancelled',
                    ]),

                Tables\Columns\TextColumn::make('reserved_at')
                    ->label('Data rezerwacji')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Wygasa')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(Reservation::$statuses),

                Tables\Filters\SelectFilter::make('contractor_id')
                    ->label('Kontrahent')
                    ->relationship('contractor', 'name'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('reserved_at', 'desc');
    }
}
