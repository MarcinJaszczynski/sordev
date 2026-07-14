<?php

namespace App\Filament\Resources\ReservationResource\Pages;

use App\Filament\Forms\ReservationFormFields;
use App\Filament\Forms\ReservationFormOptions;
use App\Filament\Resources\ReservationResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;

class EditReservation extends EditRecord
{
    protected static string $resource = ReservationResource::class;

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Rezerwacja')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('event_id')
                            ->label('Impreza')
                            ->relationship('event', 'name')
                            ->searchable()
                            ->required()
                            ->live(),

                        ...ReservationFormFields::schema(new ReservationFormOptions(
                            showProgramPoint: true,
                            showSettlementCost: true,
                            showHotelNotes: true,
                            allowContractorCreate: true,
                            editingReservation: $this->getRecord(),
                        )),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /** @param array<string, mixed> $data */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return ReservationFormFields::normalizeSaveData($data);
    }

    protected function afterSave(): void
    {
        ReservationFormFields::persistAttachments($this->getRecord(), $this->data);
    }
}
