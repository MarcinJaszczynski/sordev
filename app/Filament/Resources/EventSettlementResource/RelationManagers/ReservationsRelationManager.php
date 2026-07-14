<?php

namespace App\Filament\Resources\EventSettlementResource\RelationManagers;

use App\Filament\Forms\ReservationFormFields;
use App\Filament\Forms\ReservationFormOptions;
use App\Filament\Resources\ReservationResource;
use App\Models\Reservation;
use App\Support\ReservationPricingLabel;
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
        $settlement = $this->getOwnerRecord();
        $event = $settlement->event;

        return $form
            ->schema(ReservationFormFields::schema(new ReservationFormOptions(
                eventId: $event?->id,
                event: $event,
                settlementId: $settlement->id,
                showProgramPoint: true,
                showSettlementCost: true,
                showHotelNotes: true,
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

                Tables\Columns\TextColumn::make('contractor.name')
                    ->label('Kontrahent')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('programPoint.templatePoint.name')
                    ->label('Punkt programu')
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
                    ->label('Data rezerwacji')
                    ->dateTime('d.m.Y H:i'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('create_reservation')
                    ->label('Utwórz')
                    ->icon('heroicon-o-plus')
                    ->url(function (): string {
                        $settlement = $this->getOwnerRecord();

                        return ReservationResource::getUrl('create').'?'.http_build_query([
                            'event_id' => $settlement->event_id,
                            'settlement_id' => $settlement->id,
                        ]);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->modalWidth(ReservationFormFields::MODAL_WIDTH),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
