<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Filament\Forms\ReservationFormFields;
use App\Filament\Forms\ReservationFormOptions;
use App\Filament\Resources\ReservationResource;
use App\Models\Reservation;
use App\Support\ReservationPricingLabel;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ProgramPointReservationsRelationManager extends RelationManager
{
    protected static string $relationship = 'reservations';

    protected static ?string $title = 'Rezerwacje dla punktu';

    protected static ?string $recordTitleAttribute = 'booking_reference';

    public function form(Form $form): Form
    {
        $point = $this->getOwnerRecord();
        $event = $point->event;

        return $form
            ->schema(ReservationFormFields::schema(new ReservationFormOptions(
                eventId: $event?->id,
                event: $event,
                settlementId: $event?->settlements()
                    ->whereIn('status', ['draft', 'active', 'pilot_settled'])
                    ->latest('id')
                    ->value('id'),
                defaultProgramPointId: $point->id,
                defaultContractorId: $point->contractor_id,
                isHotelContext: (bool) $point->is_hotel,
                showHotelNotes: (bool) $point->is_hotel,
                showSettlementCost: true,
            )))
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('booking_reference')
                    ->label('Nr rezerwacji')
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('participant_count')
                    ->label('Osób')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('reserved_amount')
                    ->label('Kwota')
                    ->formatStateUsing(fn (Reservation $record): string => ReservationPricingLabel::format($record)),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => Reservation::$statuses[$state] ?? $state),

                Tables\Columns\TextColumn::make('reserved_at')
                    ->label('Data')
                    ->dateTime('d.m.Y H:i'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('create_reservation')
                    ->label('Utwórz')
                    ->icon('heroicon-o-plus')
                    ->url(fn (): string => ReservationResource::getUrl('create').'?'
                        .http_build_query([
                            'event_id' => $this->getOwnerRecord()->event_id,
                            'program_point_id' => $this->getOwnerRecord()->id,
                        ])),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->modalWidth(ReservationFormFields::MODAL_WIDTH),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
