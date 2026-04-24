<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReservationResource\Pages;
use App\Models\EventProgramPoint;
use App\Models\Reservation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReservationResource extends Resource
{
    protected static ?string $model = Reservation::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Rezerwacje';

    protected static ?string $navigationGroup = 'Finanse';

    protected static ?int $navigationSort = 11;

    protected static ?string $modelLabel = 'Rezerwacja';

    protected static ?string $pluralModelLabel = 'Rezerwacje';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Podstawowe informacje')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('event_id')
                            ->label('Impreza')
                            ->relationship('event', 'name')
                            ->searchable()
                            ->required(),

                        Forms\Components\Select::make('contractor_id')
                            ->label('Kontrahent')
                            ->relationship('contractor', 'name')
                            ->searchable()
                            ->nullable(),

                        Forms\Components\TextInput::make('booking_reference')
                            ->label('Numer rezerwacji')
                            ->unique(ignoreRecord: true)
                            ->nullable(),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(Reservation::$statuses)
                            ->required()
                            ->default('pending'),

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
                            ->options(function (Forms\Get $get) {
                                return EventProgramPoint::query()
                                    ->when($get('event_id'), fn ($q, $eventId) => $q->where('event_id', $eventId))
                                    ->with('templatePoint')
                                    ->orderBy('day')
                                    ->orderBy('order')
                                    ->limit(400)
                                    ->get()
                                    ->mapWithKeys(fn (EventProgramPoint $point) => [
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

                                $point = EventProgramPoint::query()->with('event')->find($state);
                                if (! $point) {
                                    return;
                                }

                                $set('event_id', $point->event_id);

                                $costId = \App\Models\EventSettlementCost::query()
                                    ->where('source_type', 'program_point')
                                    ->where('source_id', $point->id)
                                    ->whereHas('settlement', fn ($q) => $q->whereIn('status', ['draft', 'active', 'pilot_settled']))
                                    ->orderByDesc('id')
                                    ->value('id');

                                if ($costId) {
                                    $set('settlement_cost_id', $costId);
                                }
                            }),
                    ]),

                Forms\Components\Section::make('Daty i powiązania')
                    ->columns(2)
                    ->schema([
                        Forms\Components\DateTimePicker::make('reserved_at')
                            ->label('Data rezerwacji')
                            ->required(),

                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Wygasa')
                            ->nullable()
                            ->helperText('Pozostaw puste, aby rezerwacja była ważna bezterminowo'),

                        Forms\Components\Select::make('settlement_cost_id')
                            ->label('Koszt rozliczenia')
                            ->relationship('settlementCost', 'name')
                            ->searchable()
                            ->nullable(),
                    ]),

                Forms\Components\Section::make('Uwagi')
                    ->schema([
                        Forms\Components\RichEditor::make('notes')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
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

                Tables\Columns\TextColumn::make('event.name')
                    ->label('Impreza')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('programPoint.name')
                    ->label('Punkt programu')
                    ->state(fn (Reservation $record) => $record->programPoint?->templatePoint?->name ?? $record->programPoint?->name)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('contractor.name')
                    ->label('Kontrahent')
                    ->sortable()
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => Reservation::$statuses[$state] ?? $state)
                    ->colors([
                        'warning' => 'pending',
                        'success' => ['confirmed', 'completed'],
                        'danger' => 'cancelled',
                        'gray' => 'partially_confirmed',
                    ]),

                Tables\Columns\TextColumn::make('participant_count')
                    ->label('Uczestnicy')
                    ->sortable(),

                Tables\Columns\TextColumn::make('reserved_amount')
                    ->label('Kwota')
                    ->money('PLN')
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Wygasa')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->placeholder('∞'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(Reservation::$statuses),

                Tables\Filters\SelectFilter::make('event_id')
                    ->label('Impreza')
                    ->relationship('event', 'name')
                    ->searchable(),

                Tables\Filters\SelectFilter::make('contractor_id')
                    ->label('Kontrahent')
                    ->relationship('contractor', 'name')
                    ->searchable(),

                Tables\Filters\Filter::make('expired')
                    ->label('Wygasłe rezerwacje')
                    ->query(fn (Builder $query) => $query->where('expires_at', '<', now()))
                    ->toggle(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReservations::route('/'),
            'create' => Pages\CreateReservation::route('/create'),
            'edit' => Pages\EditReservation::route('/{record}/edit'),
        ];
    }
}
