<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Actions\Reservations\UpsertReservationAction;
use App\Data\UpsertReservationData;
use App\Filament\Forms\ReservationFormFields;
use App\Filament\Forms\ReservationFormOptions;
use App\Models\EventProgramPoint;
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
        $event = $this->getOwnerRecord();

        return $form
            ->schema(ReservationFormFields::schema(new ReservationFormOptions(
                eventId: $event->id,
                event: $event,
                showProgramPoint: true,
                showHotelNotes: true,
                allowContractorCreate: true,
            )))
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
                    ->label('Nr potwierdzenia dostawcy')
                    ->sortable()
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('contractor.name')
                    ->label('Kontrahent')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('program_point_summary')
                    ->label('Punkt programu')
                    ->state(function (Reservation $record): string {
                        $point = $record->programPoint;
                        if (! $point) {
                            return '—';
                        }

                        $prefix = $point->is_hotel ? '🏨 ' : '';

                        return $prefix.'Dz.'.(int) ($point->day ?? 1).': '.($point->name ?? '—');
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas(
                            'programPoint',
                            fn (Builder $pointQuery): Builder => $pointQuery->where('name', 'like', '%'.$search.'%'),
                        );
                    }),

                Tables\Columns\TextColumn::make('participant_count')
                    ->label('Uczestnicy')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('reserved_amount')
                    ->label('Kwota')
                    ->formatStateUsing(fn (Reservation $record): string => \App\Support\ReservationPricingLabel::format($record))
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => Reservation::$statuses[$state] ?? $state)
                    ->colors([
                        'gray' => 'pending',
                        'warning' => 'partially_confirmed',
                        'success' => ['confirmed', 'completed'],
                        'danger' => 'cancelled',
                        'info' => 'not_required',
                    ]),

                Tables\Columns\TextColumn::make('reserved_at')
                    ->label('Data rezerwacji')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Wygasa')
                    ->date('d.m.Y')
                    ->sortable()
                    ->placeholder('—')
                    ->color(fn (Reservation $record): ?string => $record->expires_at?->isPast() ? 'danger' : null),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(Reservation::$statuses),

                Tables\Filters\SelectFilter::make('contractor_id')
                    ->label('Kontrahent')
                    ->relationship('contractor', 'name'),

                Tables\Filters\SelectFilter::make('program_point_id')
                    ->label('Punkt programu')
                    ->options(fn (): array => $this->programPointOptions()),

                Tables\Filters\TernaryFilter::make('hotel_only')
                    ->label('Tylko hotele')
                    ->placeholder('Wszystkie')
                    ->trueLabel('Hotele')
                    ->falseLabel('Pozostałe')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas(
                            'programPoint',
                            fn (Builder $pointQuery): Builder => $pointQuery->where('is_hotel', true),
                        ),
                        false: fn (Builder $query) => $query->whereHas(
                            'programPoint',
                            fn (Builder $pointQuery): Builder => $pointQuery->where('is_hotel', false),
                        ),
                        blank: fn (Builder $query) => $query,
                    ),

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
                                fn (Builder $q, $date) => $q->whereDate('reserved_at', '>=', $date),
                            )
                            ->when(
                                $data['reserved_until'],
                                fn (Builder $q, $date) => $q->whereDate('reserved_at', '<=', $date),
                            );
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Dodaj rezerwację')
                    ->modalWidth(ReservationFormFields::MODAL_WIDTH)
                    ->using(function (array $data): Reservation {
                        return app(UpsertReservationAction::class)(new UpsertReservationData(
                            attributes: [
                                ...$data,
                                'event_id' => $this->getOwnerRecord()->id,
                            ],
                            attachmentData: $data,
                            createdBy: auth()->id(),
                        ));
                    }),
            ])
            ->emptyStateHeading('Brak rezerwacji')
            ->emptyStateDescription('Dodaj pierwszą rezerwację u kontrahenta w kontekście tej imprezy.')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Dodaj rezerwację')
                    ->icon('heroicon-m-plus')
                    ->modalWidth(ReservationFormFields::MODAL_WIDTH)
                    ->using(function (array $data): Reservation {
                        return app(UpsertReservationAction::class)(new UpsertReservationData(
                            attributes: [
                                ...$data,
                                'event_id' => $this->getOwnerRecord()->id,
                            ],
                            attachmentData: $data,
                            createdBy: auth()->id(),
                        ));
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->modalWidth(ReservationFormFields::MODAL_WIDTH)
                    ->using(function (Reservation $record, array $data): Reservation {
                        return app(UpsertReservationAction::class)(new UpsertReservationData(
                            attributes: $data,
                            reservation: $record,
                            attachmentData: $data,
                            createdBy: auth()->id(),
                        ));
                    }),
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

    /**
     * @return array<int, string>
     */
    protected function programPointOptions(): array
    {
        return EventProgramPoint::query()
            ->where('event_id', $this->getOwnerRecord()->id)
            ->where('active', true)
            ->orderBy('day')
            ->orderBy('order')
            ->get()
            ->mapWithKeys(function (EventProgramPoint $point): array {
                $prefix = $point->is_hotel ? '🏨 ' : '';

                return [
                    $point->id => $prefix.'Dz.'.(int) ($point->day ?? 1).': '.($point->name ?? 'Punkt #'.$point->id),
                ];
            })
            ->all();
    }
}
