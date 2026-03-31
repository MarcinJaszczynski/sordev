<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Models\Reservation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Database\Query\Builder;

class ReservationsRelationManager extends RelationManager
{
    protected static string $relationship = 'reservations';
    protected static ?string $title = 'Rezerwacje';
    protected static ?string $recordTitleAttribute = 'booking_reference';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('booking_reference')
                    ->label('Nr rezerwacji')
                    ->maxLength(255),

                Forms\Components\Select::make('contractor_id')
                    ->label('Kontrahent')
                    ->relationship('contractor', 'name')
                    ->searchable()
                    ->preload(),

                Forms\Components\Select::make('program_point_id')
                    ->label('Punkt programu')
                    ->relationship('programPoint', 'name')
                    ->searchable()
                    ->preload(),

                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options(Reservation::$statuses)
                    ->required(),

                Forms\Components\TextInput::make('participant_count')
                    ->label('Liczba uczestników')
                    ->numeric()
                    ->minValue(0),

                Forms\Components\TextInput::make('reserved_amount')
                    ->label('Kwota rezerwacji')
                    ->numeric()
                    ->prefix('PLN'),

                Forms\Components\DateTimePicker::make('reserved_at')
                    ->label('Data rezerwacji'),

                Forms\Components\DatePicker::make('expires_at')
                    ->label('Wygasa'),

                Forms\Components\Textarea::make('notes')
                    ->label('Uwagi')
                    ->columnSpanFull(),
            ])
            ->columns(2);
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
                    ->label('Nr rezerwacji')
                    ->sortable()
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('contractor.name')
                    ->label('Kontrahent')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('programPoint.name')
                    ->label('Punkt programu')
                    ->sortable()
                    ->searchable()
                    ->placeholder('—'),

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
                    ->formatStateUsing(fn($state) => Reservation::$statuses[$state] ?? $state)
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
                    ->date('d.m.Y')
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

                Tables\Filters\Filter::make('reserved_at')
                    ->label('Data rezerwacji')
                    ->form([
                        Forms\Components\DatePicker::make('reserved_from')
                            ->label('Od'),
                        Forms\Components\DatePicker::make('reserved_until')
                            ->label('Do'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['reserved_from'],
                                fn(Builder $q, $date) => $q->whereDate('reserved_at', '>=', $date),
                            )
                            ->when(
                                $data['reserved_until'],
                                fn(Builder $q, $date) => $q->whereDate('reserved_at', '<=', $date),
                            );
                    }),
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
            ->defaultSort('reserved_at', 'desc')
            ->paginated([10, 25, 50]);
    }
}
