<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Actions\Reservations\UpsertReservationAction;
use App\Data\UpsertReservationData;
use App\Filament\Forms\ReservationFormFields;
use App\Filament\Forms\ReservationFormOptions;
use App\Filament\Resources\ReservationResource;
use App\Models\EventProgramPoint;
use App\Models\Reservation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
            ->columns(['default' => 1, 'md' => 2]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'event',
                'programPoint.templatePoint',
                'programPoint.contractor',
                'contractor',
                'settlementCost',
            ]))
            ->columns(ReservationResource::sharedTableColumns(includeEvent: false))
            ->searchPlaceholder('Szukaj: nr rezerwacji, kontrahent, punkt programu, daty, uwagi…')
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
                    ->fillForm(fn (): array => ReservationFormFields::defaultModalData(new ReservationFormOptions(
                        eventId: $this->getOwnerRecord()->id,
                        event: $this->getOwnerRecord(),
                        defaultParticipantCount: (int) ($this->getOwnerRecord()->participant_count ?? 1),
                    )))
                    ->using(function (array $data): Reservation {
                        return app(UpsertReservationAction::class)(UpsertReservationData::fromForm(
                            formData: $data,
                            attributeOverrides: ['event_id' => $this->getOwnerRecord()->id],
                        ));
                    }),
            ])
            ->emptyStateHeading('Brak rezerwacji')
            ->emptyStateDescription('To ta sama lista co w programie imprezy i w „Wszystkie rezerwacje”. Dodaj rezerwację tutaj albo przy punkcie programu.')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Dodaj rezerwację')
                    ->icon('heroicon-m-plus')
                    ->modalWidth(ReservationFormFields::MODAL_WIDTH)
                    ->fillForm(fn (): array => ReservationFormFields::defaultModalData(new ReservationFormOptions(
                        eventId: $this->getOwnerRecord()->id,
                        event: $this->getOwnerRecord(),
                        defaultParticipantCount: (int) ($this->getOwnerRecord()->participant_count ?? 1),
                    )))
                    ->using(function (array $data): Reservation {
                        return app(UpsertReservationAction::class)(UpsertReservationData::fromForm(
                            formData: $data,
                            attributeOverrides: ['event_id' => $this->getOwnerRecord()->id],
                        ));
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->modalWidth(ReservationFormFields::MODAL_WIDTH)
                    ->using(function (Reservation $record, array $data): Reservation {
                        return app(UpsertReservationAction::class)(UpsertReservationData::fromForm(
                            formData: $data,
                            reservation: $record,
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
